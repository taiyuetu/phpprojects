<?php
/**
 * Migration: Add attributes (JSON custom fields) column to sales, purchases,
 * users, and inventory_transactions tables.
 * Run once: php database/add_attributes_to_transactions.php
 */
$config = require __DIR__ . '/../config/config.php';

if ($config['db']['driver'] === 'mysql') {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass']);
} else {
    $dbPath = $config['db']['sqlite_path'];
    if (!file_exists($dbPath)) {
        die("Database not found at {$dbPath}\n");
    }
    $pdo = new PDO('sqlite:' . $dbPath);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tables = ['sales', 'purchases', 'users', 'inventory_transactions'];

foreach ($tables as $table) {
    if ($config['db']['driver'] === 'mysql') {
        $columns = $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN, 0);
    } else {
        $columns = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_COLUMN, 1);
    }

    if (in_array('attributes', $columns)) {
        echo "Column 'attributes' already exists on {$table}. Skipping.\n";
        continue;
    }

    $default = '{}';
    if ($config['db']['driver'] === 'mysql') {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN attributes TEXT DEFAULT '{$default}'");
    } else {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN attributes TEXT DEFAULT '{$default}'");
    }
    echo "Column 'attributes' added to {$table}.\n";
}

echo "Migration complete.\n";
