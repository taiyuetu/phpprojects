<?php
/**
 * Migration: Create attachments table
 * Run: php database/migrate_attachments.php
 */

$dbPath = __DIR__ . '/crm.sqlite';
if (!file_exists($dbPath)) {
    die("Database not found: $dbPath\n");
}

$pdo = new PDO("sqlite:$dbPath", null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS attachments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        related_type TEXT NOT NULL CHECK(related_type IN ('deal', 'order')),
        related_id INTEGER NOT NULL,
        filename TEXT NOT NULL,
        original_name TEXT NOT NULL,
        mime_type TEXT NOT NULL,
        file_size INTEGER NOT NULL DEFAULT 0,
        uploaded_by INTEGER NOT NULL,
        created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
    )
");

$pdo->exec("CREATE INDEX IF NOT EXISTS idx_attachments_related ON attachments(related_type, related_id)");

echo "✅ attachments table created successfully.\n";
