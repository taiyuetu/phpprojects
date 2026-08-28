<?php
/**
 * Migration: Add attributes (JSON custom fields) column to products table.
 * Run once: php database/add_attributes_column.php
 */
$dbPath = __DIR__ . '/database.sqlite';
if (!file_exists($dbPath)) {
    die("Database not found at {$dbPath}\n");
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$columns = $pdo->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (in_array('attributes', $columns)) {
    echo "Column 'attributes' already exists. Skipping.\n";
    exit(0);
}

$pdo->exec("ALTER TABLE products ADD COLUMN attributes TEXT DEFAULT '{}'");
echo "Column 'attributes' added to products table.\n";

// Seed example attributes for the existing demo products so the filter is
// immediately demonstrable. Harmless if these SKUs don't exist.
$seed = [
    'hubbearing' => ['brand' => 'Acme', 'color' => 'black', 'material' => 'Steel'],
    'sdasdfasf'  => ['brand' => 'Generic', 'color' => 'red', 'material' => 'Aluminum'],
];
$stmt = $pdo->prepare("UPDATE products SET attributes = ? WHERE name = ? AND (attributes IS NULL OR attributes = '{}')");
foreach ($seed as $sku => $attrs) {
    $stmt->execute([json_encode($attrs, JSON_UNESCAPED_UNICODE), $sku]);
}
echo "Example attributes seeded for demo products.\n";
