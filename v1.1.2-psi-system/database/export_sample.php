<?php
/**
 * One-time script: dump the current database as a sample SQL file.
 * Run: php database/export_sample.php
 */
$config = require __DIR__ . '/../config/config.php';
$dbPath = $config['db']['sqlite_path'];
if (!file_exists($dbPath)) {
    die("Database not found at {$dbPath}. Run php database/setup.php first.\n");
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$out = fopen(__DIR__ . '/sample_data.sql', 'w');

fwrite($out, "-- PSI System — Sample Data Dump\n");
fwrite($out, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
fwrite($out, "-- This file contains the full schema + seed data for a working demo.\n");
fwrite($out, "-- Usage: sqlite3 database/database.sqlite < database/sample_data.sql\n\n");

// Include schema
fwrite($out, file_get_contents(__DIR__ . '/schema.sql'));
fwrite($out, "\n\n");

$tables = [
    'users', 'categories', 'suppliers', 'customers', 'products',
    'purchases', 'purchase_arrivals', 'purchase_items',
    'sales', 'sale_items', 'inventory_transactions', 'change_logs',
];

foreach ($tables as $table) {
    $rows = $pdo->query("SELECT * FROM {$table}")->fetchAll();
    if (empty($rows)) continue;

    $cols = array_keys($rows[0]);
    fwrite($out, "-- Table: {$table} (" . count($rows) . " rows)\n");

    foreach ($rows as $r) {
        $vals = array_map(function ($v) {
            if ($v === null) return 'NULL';
            return "'" . str_replace("'", "''", (string)$v) . "'";
        }, array_values($r));
        fwrite($out, "INSERT OR IGNORE INTO {$table} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n");
    }
    fwrite($out, "\n");
}

fclose($out);
echo "Sample data exported to database/sample_data.sql\n";
