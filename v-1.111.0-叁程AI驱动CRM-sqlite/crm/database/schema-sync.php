<?php
/**
 * 叁程 CRM (Triphase CRM) 结构同步工具：注册表 → 缺列 → ALTER。
 *
 * 用法：
 *   php database/schema-sync.php                     # 列出所有注册表里声明但库里还没有的列
 *   php database/schema-sync.php --table=products    # 只看某张表
 *   php database/schema-sync.php --table=products --apply   # 缺的列直接加进当前库（幂等）
 *   php database/schema-sync.php --db=/path/x.sqlite
 *
 * 只做“声明了但库里没有”的加列，绝不改已有列。对齐当前库后请照常提交增量迁移，
 * 并把 schemaLine 提示的列定义同步进 database/schema.sql（老库/新库结构一致原则，
 * 与 database/migrate.php 的注释约定相同）。
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

$opts = getopt('', ['table::', 'apply', 'db::', 'help']);
if (isset($opts['help'])) {
    echo "usage: php database/schema-sync.php [--table=T] [--apply] [--db=PATH]\n";
    exit(0);
}

// ---- 常量与类路径（不依赖 bootstrap，便于在任何环境跑） ----
$baseDir = dirname(__DIR__);
define('BASE_PATH', $baseDir);
define('APP_PATH', $baseDir . '/app');

$dbPath = $opts['db'] ?? '';
if ($dbPath === '') {
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
    if ($dbPath[0] !== '/' && strpos($dbPath, ':') === false) {
        $dbPath = $baseDir . '/' . ltrim($dbPath, '/');
    }
}
$dbPath = str_replace('\\', '/', $dbPath);
define('DB_PATH', $dbPath);

require APP_PATH . '/core/autoloader.php';

$wantTable = (string) ($opts['table'] ?? '');
$apply = isset($opts['apply']);

// ---- 跑 diff ----
$tables = $wantTable !== '' ? [$wantTable] : SchemaSync::tables();
$allStatements = [];
foreach ($tables as $table) {
    $missing = SchemaSync::missing($table);
    $label = $table . (Fields::declaredFor($table) ? '（已注册）' : '（无注册表，跳过）');
    if (Fields::declaredFor($table) === []) {
        echo "  {$label}\n";
        continue;
    }
    if ($missing === []) {
        echo "  {$table}：已同步（无缺列）\n";
        continue;
    }
    echo "  {$table} 缺 " . count($missing) . " 列：\n";
    foreach ($missing as $name => $meta) {
        echo '    - ' . SchemaSync::addColumnClause($table, $name, $meta) . "\n";
        echo '      schema.sql 列定义（提示）：' . SchemaSync::schemaLine($name, $meta) . "\n";
        $allStatements[] = SchemaSync::addColumnClause($table, $name, $meta);
    }
}

if ($apply && $allStatements) {
    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "\n-- apply --\n";
    foreach ($allStatements as $stmt) {
        $pdo->exec($stmt);
        echo '  applied: ' . $stmt . "\n";
    }
    echo '完成。记得把 schemaLine 提示同步进 schema.sql，并提交增量迁移（migrate.php 会为老库重放）。' . "\n";
} elseif ($apply) {
    echo "\n没有需要执行的 ALTER。\n";
}
