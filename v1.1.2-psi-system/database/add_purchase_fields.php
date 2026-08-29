<?php
/**
 * Migration: Add expected_arrival_date, actual_arrival_date, actual_arrival_qty, notes
 * to the purchases table.
 * Run with:  php database/add_purchase_fields.php
 */
require __DIR__ . '/../config/config.php';
$config = require __DIR__ . '/../config/config.php';

$dbPath = $config['db']['sqlite_path'];
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check existing columns
$existing = [];
$rows = $pdo->query('PRAGMA table_info(purchases)')->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $existing[] = $r['name'];
}

$columns = [
    'expected_arrival_date' => 'TEXT',
    'actual_arrival_date'   => 'TEXT',
    'actual_arrival_qty'    => 'INTEGER DEFAULT 0',
    'notes'                 => "TEXT DEFAULT ''",
];

foreach ($columns as $col => $type) {
    if (!in_array($col, $existing)) {
        $pdo->exec("ALTER TABLE purchases ADD COLUMN $col $type");
        echo "Added column: $col ($type)\n";
    } else {
        echo "Column already exists: $col\n";
    }
}

echo "Migration complete.\n";
