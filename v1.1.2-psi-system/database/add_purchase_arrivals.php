<?php
/**
 * Migration: Create purchase_arrivals table for partial arrivals support.
 * Run with:  php database/add_purchase_arrivals.php
 */
require __DIR__ . '/../config/config.php';
$config = require __DIR__ . '/../config/config.php';

$dbPath = $config['db']['sqlite_path'];
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create purchase_arrivals table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS purchase_arrivals (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        purchase_id   INTEGER NOT NULL,
        arrival_date  TEXT NOT NULL,
        qty           INTEGER NOT NULL,
        notes         TEXT DEFAULT '',
        created_by    INTEGER,
        created_at    TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )
");
echo "Created purchase_arrivals table.\n";

// Migrate existing data: move actual_arrival_date and actual_arrival_qty to arrivals table
$rows = $pdo->query("SELECT id, actual_arrival_date, actual_arrival_qty FROM purchases WHERE actual_arrival_qty > 0 AND actual_arrival_date IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);

$insertStmt = $pdo->prepare("INSERT INTO purchase_arrivals (purchase_id, arrival_date, qty, created_by, created_at) VALUES (?, ?, ?, ?, ?)");

$migrated = 0;
foreach ($rows as $row) {
    $insertStmt->execute([
        $row['id'],
        $row['actual_arrival_date'],
        $row['actual_arrival_qty'],
        null,
        $row['actual_arrival_date'] . ' 00:00:00'
    ]);
    $migrated++;
}
echo "Migrated $migrated existing arrival records.\n";

// Remove old columns from purchases table (SQLite doesn't support DROP COLUMN easily, so we'll just leave them)
echo "Note: Old columns (actual_arrival_date, actual_arrival_qty) still exist in purchases table for compatibility.\n";

echo "Migration complete.\n";
