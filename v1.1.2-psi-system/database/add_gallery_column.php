<?php
/**
 * Migration: Add gallery column to products table.
 * Run once: php database/add_gallery_column.php
 */
$dbPath = __DIR__ . '/database.sqlite';
if (!file_exists($dbPath)) {
    die("Database not found at {$dbPath}\n");
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check if column already exists
$columns = $pdo->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (in_array('gallery', $columns)) {
    echo "Column 'gallery' already exists. Skipping.\n";
    exit(0);
}

$pdo->exec("ALTER TABLE products ADD COLUMN gallery TEXT DEFAULT '[]'");
echo "Column 'gallery' added to products table.\n";
