<?php
/**
 * Migration: Add attributes (JSON custom fields) column to suppliers,
 * customers and categories tables.
 * Run once: php database/add_attributes_to_contacts.php
 */
$dbPath = __DIR__ . '/database.sqlite';
if (!file_exists($dbPath)) {
    die("Database not found at {$dbPath}\n");
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

foreach (['suppliers', 'customers', 'categories'] as $table) {
    $columns = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (in_array('attributes', $columns)) {
        echo "Column 'attributes' already exists on {$table}. Skipping.\n";
        continue;
    }
    $pdo->exec("ALTER TABLE {$table} ADD COLUMN attributes TEXT DEFAULT '{}'");
    echo "Column 'attributes' added to {$table}.\n";
}
