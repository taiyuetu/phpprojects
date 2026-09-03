<?php
/**
 * MiniCRM 统一数据库迁移入口 (single source of truth for DB setup)
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
 *
 * 约定：
 *   - 新增"整张新表/索引/触发器"→ 直接写进 schema.sql 即可（不需要增量文件）。
 *   - 修改"已有表的结构"(加列等) → 新建增量文件，并(可选)同步更新 schema.sql，
 *     这样全新数据库与旧数据库最终结构一致。
 */

// ---------- 参数解析 ----------
$opts = getopt('', ['db::', 'status', 'help']);
$statusOnly = isset($opts['status']);
$dbPath = $opts['db'] ?? null;
if ($dbPath !== null) {
    $dbPath = str_replace('\\', '/', $dbPath);
}

if (isset($opts['help'])) {
    echo <<<TXT
MiniCRM database migrate tool

Usage:
  php database/migrate.php                 create or upgrade the database
  php database/migrate.php --status        show applied migrations and tables
  php database/migrate.php --db=PATH       use a specific sqlite file
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

/** 整文件执行；尝试包事务；返回抛错前已执行部分的风险由"执行成功才记录"兜底 */
function execFile(PDO $pdo, string $file, string $label): void
{
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Cannot read {$file}");
    }
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

// ---------- 1) 应用基线 schema.sql (幂等，每次都跑 => 自愈) ----------
echo PHP_EOL . '== Baseline ==' . PHP_EOL;
$pdo->exec('PRAGMA foreign_keys = ON');
execFile($pdo, $schemaFile, 'schema.sql (baseline)');

// ---------- 2) 应用未执行的增量迁移 ----------
echo '== Incremental migrations ==' . PHP_EOL;
$applied = $getApplied();
$files = glob($migDir . '/*.sql') ?: [];
sort($files);
$ran = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        continue; // 已执行过
    }
    execFile($pdo, $file, $name);
    $markApplied($name);
    $ran++;
}
if ($ran === 0) {
    echo '  nothing pending (migrations/ has no unapplied files)' . PHP_EOL;
}

// ---------- 3) 自检：期望表是否齐全 ----------
echo '== Verify ==' . PHP_EOL;
$expected = ['users', 'customers', 'leads', 'deals', 'orders', 'order_items', 'follow_ups', 'activities', 'attachments'];
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
