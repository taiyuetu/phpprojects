<?php
/**
 * 叁程 CRM (Triphase CRM) 统一数据库迁移入口 (single source of truth for DB setup)
 *
 * 用法 (运行一次即可，可重复执行，幂等)：
 *   php database/migrate.php             # 创建/升级数据库
 *   php database/migrate.php --status    # 只查看当前迁移状态
 *   php database/migrate.php --db=/path/to/other.sqlite
 *
 * 工作机制：
 *   1. 基线 (schema.sql)：
 *      schema.sql 是"结构 + 索引 + 触发器 + 种子数据"的唯一权威文件，
 *      并且完全幂等 (全部使用 CREATE ... IF NOT EXISTS / INSERT OR IGNORE)。
 *      因此每次运行本脚本都会重新执行它 —— 旧数据库缺任何表/列/索引都会被自动补齐 (自愈)。
 *   2. 增量迁移 (database/migrations/*.sql)：
 *      对现有表做无法幂等表达的变更 (如 ALTER TABLE ... ADD COLUMN) 时，
 *      在 database/migrations/ 目录新增一个 NNN_名称.sql 文件。
 *      本脚本按文件名顺序执行一次并记录到 _migrations 表，之后不会再重复执行。
 *      若某个增量文件通篇只有 ADD COLUMN 语句，而其中的列在基线里已经存在
 *      （全新数据库就是这种情况），则自动跳过执行、仅登记，避免 duplicate column name。
 *
 * 约定：
 *   - 新增"整张新表/索引/触发器"→ 直接写进 schema.sql 即可（不需要增量文件）。
 *   - 修改"已有表的结构"(加列等) → 新建增量文件，并同步更新 schema.sql，
 *     这样全新数据库与旧数据库最终结构一致（增量文件会因基线已含该列而自动跳过）。
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

// ---------- 参数解析 ----------
$opts = getopt('', ['db::', 'status', 'help', 'no-demo']);
$statusOnly = isset($opts['status']);
$dbPath = $opts['db'] ?? null;
if ($dbPath !== null) {
    $dbPath = str_replace('\\', '/', $dbPath);
}

if (isset($opts['help'])) {
    echo <<<TXT
Triphase CRM database migrate tool

Usage:
  php database/migrate.php                 create or upgrade the database
  php database/migrate.php --status        show applied migrations and tables
  php database/migrate.php --db=PATH       use a specific sqlite file
  php database/migrate.php --no-demo       skip demo sample data (products/customers/leads/deals/orders/follow-ups)
TXT;
    exit(0);
}

$baseDir = dirname(__DIR__);          // project root
$dbDir   = __DIR__;                   // database/
$schemaFile = __DIR__ . '/schema.sql';
$migDir  = __DIR__ . '/migrations';

// ---------- 定位数据库文件 ----------
if ($dbPath === null) {
    // 读 .env 里的 DB_PATH（与 app/config/config.php 逻辑一致，但本脚本不依赖 bootstrap）
    $dbPath = getenv('DB_PATH') ?: '';
    $envFile = $baseDir . '/.env';
    if ($dbPath === '' && is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'DB_PATH=')) {
                $dbPath = trim(substr($line, 8), " \t\"'");
                break;
            }
        }
    }
    if ($dbPath === '') {
        $dbPath = 'database/crm.sqlite';
    }
    // 相对路径基于项目根目录解析
    if ($dbPath[0] !== '/' && strpos($dbPath, ':') === false) {
        $dbPath = $baseDir . '/' . ltrim($dbPath, '/');
    }
}
$dbPath = str_replace('\\', '/', $dbPath);

if (!file_exists($schemaFile)) {
    fwrite(STDERR, "Error: schema.sql not found at {$schemaFile}\n");
    exit(1);
}

// 确保数据库文件所在目录存在
$dir = dirname($dbPath);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

echo 'Database : ' . $dbPath . PHP_EOL;

// ---------- 连接 ----------
try {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, 'Cannot open database: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/** 标识符是否已是数据库中真实存在的列（表不存在时返回 false） */
function columnExists(PDO $pdo, string $table, string $column): bool
{
    // pragma_table_info 的表名可以参数化，无需拼接标识符
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM pragma_table_info(:t) WHERE lower(name) = lower(:c)');
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * 解析"纯加列"增量文件。
 *
 * 返回 [[表名, 列名], ...]；若文件除注释/空白/分号外还含其它语句，返回 []，
 * 表示不做跳过判定，交由正常执行流程处理。
 *
 * @return array<int, array{0:string,1:string}>
 */
function addedColumnsOfPureAddColumnFile(string $sql): array
{
    // SQLite 的 ADD COLUMN 子句内不会出现分号，用 [^;]* 截到语句末尾即可
    $re = '/ALTER\s+TABLE\s+[`"\[]?([A-Za-z_][A-Za-z0-9_$]*)[`"\]]?\s+ADD\s+(?:COLUMN\s+)?[`"\[]?([A-Za-z_][A-Za-z0-9_$]*)[`"\]]?[^;]*/i';
    if (!preg_match_all($re, $sql, $m, PREG_SET_ORDER)) {
        return [];
    }
    $rest = preg_replace('/--[^\r\n]*/', ' ', $sql); // 去行注释
    $rest = preg_replace($re, ' ', $rest);           // 移除加列语句本身
    $rest = trim(preg_replace('/\s+/', ' ', str_replace(';', ' ', (string) $rest)));
    if ($rest !== '') {
        return []; // 含其它变更（建表、改 CHECK 等）——不可跳过
    }
    $cols = [];
    foreach ($m as $row) {
        $cols[] = [$row[1], $row[2]];
    }
    return $cols;
}

/** 整文件执行；尝试包事务；返回抛错前已执行部分的风险由"执行成功才记录"兜底 */
function execFile(PDO $pdo, string $file, string $label): void
{
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Cannot read {$file}");
    }
    execSql($pdo, $sql, $label);
}

/** 执行一段 SQL；尝试包事务；返回抛错前已执行部分的风险由"执行成功才记录"兜底 */
function execSql(PDO $pdo, string $sql, string $label): void
{
    // PRAGMA journal_mode / foreign_keys 不能在事务内执行
    $canTx = stripos($sql, 'journal_mode') === false && stripos($sql, 'PRAGMA ') === false;
    if ($canTx) {
        $pdo->exec('BEGIN');
    }
    try {
        $pdo->exec($sql);
        if ($canTx) {
            $pdo->exec('COMMIT');
        }
    } catch (Throwable $e) {
        if ($canTx) {
            try { $pdo->exec('ROLLBACK'); } catch (Throwable $ignored) {}
        }
        throw $e;
    }
    echo '  applied: ' . $label . PHP_EOL;
}

// ---------- 迁移记录表 ----------
$pdo->exec('CREATE TABLE IF NOT EXISTS _migrations (
    name       TEXT PRIMARY KEY,
    applied_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
)');

$getApplied = function (): array {
    global $pdo;
    $rows = $pdo->query('SELECT name FROM _migrations')->fetchAll();
    return array_column($rows, 'name');
};
$markApplied = function (string $name) use ($pdo): void {
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO _migrations (name) VALUES (:n)');
    $stmt->bindValue(':n', $name);
    $stmt->execute();
};

// ---------- --status 模式 ----------
if ($statusOnly) {
    echo PHP_EOL . '-- Status --' . PHP_EOL;
    $applied = $getApplied();
    $files = glob($migDir . '/*.sql') ?: [];
    sort($files);
    echo 'Schema baseline (schema.sql): always re-applied (idempotent)' . PHP_EOL;
    if ($applied) {
        echo 'Applied incremental migrations:' . PHP_EOL;
        foreach ($applied as $name) {
            echo '  [x] ' . $name . PHP_EOL;
        }
    } else {
        echo 'Applied incremental migrations: (none yet)' . PHP_EOL;
    }
    if ($files) {
        echo 'Pending files in migrations/:' . PHP_EOL;
        foreach ($files as $f) {
            $base = basename($f);
            echo '  ' . (in_array($base, $applied, true) ? '[applied] ' : '[pending] ') . $base . PHP_EOL;
        }
    }
    echo PHP_EOL . 'Tables in database:' . PHP_EOL;
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name <> '_migrations' ORDER BY name")->fetchAll();
    foreach ($tables as $t) {
        echo '  - ' . $t['name'] . PHP_EOL;
    }
    exit(0);
}

// ---------- 演示数据开关 ----------
// 默认带一套演示业务数据（本地跑起来直接有货可看）；生产新库建议用
// `--no-demo` 或环境变量 CRM_DEMO_DATA=0 跳过（管理员账号与系统设置始终会建）。
$demoData = true;
if (array_key_exists('no-demo', $opts)) {
    $demoData = false;
} else {
    $envDemo = getenv('CRM_DEMO_DATA');
    if ($envDemo === false || trim($envDemo) === '') {
        $envFile = $baseDir . '/.env';
        if (is_readable($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if (str_starts_with($line, 'CRM_DEMO_DATA=')) {
                    $envDemo = trim(substr($line, strlen('CRM_DEMO_DATA=')), " \t\"'");
                    break;
                }
            }
        }
    }
    $envDemo = trim((string) $envDemo);
    if ($envDemo !== '') {
        $demoData = in_array(strtolower($envDemo), ['1', 'true', 'yes', 'on'], true);
    }
}

// ---------- 1) 应用基线 schema.sql (幂等，每次都跑 => 自愈) ----------
echo PHP_EOL . '== Baseline ==' . PHP_EOL;
$pdo->exec('PRAGMA foreign_keys = ON');
$schemaSql = (string) file_get_contents($schemaFile);
if (!$demoData) {
    $schemaSql = (string) preg_replace('/-- >>> DEMO_DATA_BEGIN >>>.*?-- >>> DEMO_DATA_END >>>/s', '', $schemaSql);
    echo '  note: demo sample data skipped (CRM_DEMO_DATA=0 / --no-demo)' . PHP_EOL;
}
execSql($pdo, $schemaSql, 'schema.sql (baseline)');

// ---------- 2) 应用未执行的增量迁移 ----------
echo '== Incremental migrations ==' . PHP_EOL;
$applied = $getApplied();
$files = glob($migDir . '/*.sql') ?: [];
sort($files);
$ran = 0;
$skipped = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        continue; // 已执行过
    }
    // 基线 schema.sql 是结构的唯一权威来源，且每次运行都会自愈式重放；
    // 因此"只加列"的增量文件在基线已含该列的数据库上是多余的，跳过即可。
    $added = addedColumnsOfPureAddColumnFile((string) file_get_contents($file));
    if ($added) {
        $missing = [];
        foreach ($added as [$table, $column]) {
            if (!columnExists($pdo, $table, $column)) {
                $missing[] = $table . '.' . $column;
            }
        }
        if (!$missing) {
            echo '  skipped: ' . $name . ' (column(s) already present in baseline)' . PHP_EOL;
            $markApplied($name); // 效果已具备，登记以免每次重判
            $skipped++;
            continue;
        }
    }
    execFile($pdo, $file, $name);
    $markApplied($name);
    $ran++;
}
if ($ran === 0 && $skipped === 0) {
    echo '  nothing pending (migrations/ has no unapplied files)' . PHP_EOL;
}

// ---------- 3) 自检：期望表是否齐全 ----------
echo '== Verify ==' . PHP_EOL;
$expected = ['users', 'app_settings', 'customers', 'products', 'leads', 'deals', 'orders', 'order_items', 'follow_ups', 'activities', 'attachments'];
$actual = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name <> '_migrations'")->fetchAll();
$actualNames = array_column($actual, 'name');
$missing = array_diff($expected, $actualNames);
if ($missing) {
    fwrite(STDERR, '  Missing tables: ' . implode(', ', $missing) . PHP_EOL);
    echo PHP_EOL . 'Done (with warnings).' . PHP_EOL;
    exit(1);
}
echo '  All ' . count($expected) . ' expected tables present. OK' . PHP_EOL;
echo PHP_EOL . 'Migration complete.' . PHP_EOL;
