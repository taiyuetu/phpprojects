<?php
/**
 * One-time setup script.
 * Run with:  php database/setup.php
 * Creates the SQLite DB, applies the schema, and seeds a default
 * admin user + a couple of demo records so the app is usable immediately.
 */
require __DIR__ . '/../config/config.php';
$config = require __DIR__ . '/../config/config.php';

$dbPath = $config['db']['sqlite_path'];
$isNew = !file_exists($dbPath);

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

$schema = file_get_contents(__DIR__ . '/schema.sql');
$pdo->exec($schema);

echo "Schema applied.\n";

// Seed default admin (only if users table is empty)
$count = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count == 0) {
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)');
    $stmt->execute(['Administrator', 'admin@psi.local', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
    echo "Default admin created -> email: admin@psi.local / password: admin123\n";
}

// Seed a couple of categories/suppliers/customers for a nicer first run
$cat = $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
if ($cat == 0) {
    $pdo->exec("INSERT INTO categories (name) VALUES ('General'), ('Electronics'), ('Stationery')");
    $pdo->exec("INSERT INTO suppliers (name, phone, email) VALUES ('Default Supplier Co.', '555-0100', 'sales@supplier.example')");
    $pdo->exec("INSERT INTO customers (name, phone, email) VALUES ('Walk-in Customer', '', '')");
    echo "Demo reference data seeded.\n";
}

echo "Setup complete. Database file: {$dbPath}\n";
