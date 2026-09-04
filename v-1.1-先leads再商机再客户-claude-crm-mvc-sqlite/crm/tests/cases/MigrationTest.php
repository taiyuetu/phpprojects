<?php
/**
 * Migration tooling tests (database/migrate.php).
 *
 * These guard the invariant that made the whole suite unrunnable once before:
 * schema.sql is the authoritative, self-healing baseline AND ALSO carries columns
 * that older one-off migrations add with ALTER TABLE ... ADD COLUMN. A fresh
 * database must therefore skip those redundant increments instead of dying with
 * "duplicate column name", while a legacy database (columns missing) must still
 * have them applied for real.
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
require __DIR__ . '/../bootstrap.php';

const MIGRATE_PHP = BASE_PATH . '/database/migrate.php';

/** Column names of a table in the given sqlite file. */
function dbColumns(string $dbFile, string $table): array
{
    $pdo = new PDO('sqlite:' . $dbFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->prepare('SELECT name FROM pragma_table_info(:t)');
    $stmt->execute([':t' => $table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/** Run migrate.php against $dbFile; returns [exitCode, output]. */
function runMigrate(string $dbFile): array
{
    $out = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(MIGRATE_PHP)
        . ' --db=' . escapeshellarg($dbFile) . ' 2>&1', $out, $code);
    return [$code, implode("\n", $out)];
}

function tempDb(string $tag): string
{
    return sys_get_temp_dir() . '/crm_mig_' . $tag . '_' . getmypid() . '_'
         . bin2hex(random_bytes(3)) . '.sqlite';
}

function test_bootstrapped_db_matches_baseline_columns(): void
{
    // The DB this very process runs on was built by migrate.php — so if the tool
    // is healthy, the baseline-only columns must already be present.
    $cols = dbColumns($GLOBALS['TEST_DB_PATH'], 'customers');
    assertTrue(in_array('wechat', $cols, true), 'customers.wechat present after migrate');
    assertTrue(in_array('shipping_address', $cols, true), 'customers.shipping_address present after migrate');

    $pdo = new PDO('sqlite:' . $GLOBALS['TEST_DB_PATH']);
    $recorded = $pdo->query('SELECT name FROM _migrations ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    foreach (glob(BASE_PATH . '/database/migrations/*.sql') ?: [] as $file) {
        assertTrue(in_array(basename($file), $recorded, true),
            basename($file) . ' recorded in _migrations');
    }
}

function test_fresh_database_builds_without_duplicate_column_error(): void
{
    $db = tempDb('fresh');
    try {
        [$code, $out] = runMigrate($db);
        assertEquals(0, $code, "migrate.php on an empty database must succeed:\n{$out}");
        assertContains('schema.sql (baseline)', $out, 'baseline applied');
        // Increments whose columns the baseline already provides are skipped, not run.
        assertContains('skipped: 002_add_wechat_to_customers.sql', $out, 'redundant 002 skipped');
        assertContains('skipped: 004_add_shipping_address_to_customers.sql', $out, 'redundant 004 skipped');
        assertTrue(!str_contains($out, 'duplicate column name'), 'no duplicate column error');
        assertTrue(in_array('wechat', dbColumns($db, 'customers'), true), 'wechat present on fresh db');
    } finally {
        @unlink($db);
    }
}

function test_second_run_is_a_no_op(): void
{
    $db = tempDb('again');
    try {
        [$first, ] = runMigrate($db);
        assertEquals(0, $first, 'first run ok');
        [$second, $out] = runMigrate($db);
        assertEquals(0, $second, "second run ok:\n{$out}");
        assertContains('nothing pending', $out, 'second run has nothing left to apply');
    } finally {
        @unlink($db);
    }
}

/** Remove the given columns from ONE table's CREATE statement in a schema string. */
function stripColumnsFromTable(string $sql, string $table, array $columns): string
{
    $pattern = '/CREATE TABLE IF NOT EXISTS ' . preg_quote($table, '/')
             . ' \((.*?)\n\);/s';
    $quoted = implode('|', array_map(fn($c) => preg_quote($c, '/'), $columns));
    return preg_replace_callback($pattern, static function (array $m) use ($table, $quoted): string {
        $lines = [];
        foreach (preg_split("/\r?\n/", $m[1]) as $line) {
            $trim = trim($line);
            if ($trim === '' || preg_match('/^\s*(' . $quoted . ')\s+[A-Za-z][^,]*,?\s*$/i', $trim)) {
                continue;   // blank, or one of the columns under test
            }
            $lines[] = $trim;
        }
        // The block may now end on a comment or on a line with a dangling comma.
        while ($lines && str_starts_with(end($lines), '--')) {
            array_pop($lines);
        }
        if ($lines) {
            $lines[count($lines) - 1] = rtrim($lines[count($lines) - 1], ' ,');
        }
        return "CREATE TABLE IF NOT EXISTS {$table} (\n    " . implode("\n    ", $lines) . "\n);";
    }, $sql, 1) ?? $sql;
}

function test_legacy_database_still_gets_real_columns(): void
{
    // Simulate a pre-v1.2.0 / pre-v1.3.0 database: the current baseline minus the
    // columns the one-off increments add. Because schema.sql uses
    // CREATE TABLE IF NOT EXISTS, re-applying it will NOT add the missing columns
    // — the increments must.
    $legacy = tempDb('legacy');
    try {
        $sql = file_get_contents(BASE_PATH . '/database/schema.sql');
        $sql = stripColumnsFromTable($sql, 'customers', ['wechat', 'shipping_address']);
        $sql = stripColumnsFromTable($sql, 'users',
            ['phone', 'whatsapp', 'job_title', 'notes', 'updated_at']);

        $pdo = new PDO('sqlite:' . $legacy, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec($sql);
        unset($pdo);

        assertTrue(!in_array('wechat', dbColumns($legacy, 'customers'), true),
            'fixture really lacks customers.wechat');
        assertTrue(!in_array('job_title', dbColumns($legacy, 'users'), true),
            'fixture really lacks users.job_title');
        assertTrue(in_array('phone', dbColumns($legacy, 'customers'), true),
            'other tables are untouched (customers.phone still there)');

        [$code, $out] = runMigrate($legacy);
        assertEquals(0, $code, "upgrade of a legacy database must succeed:\n{$out}");
        assertContains('applied: 002_add_wechat_to_customers.sql', $out, '002 actually applied on legacy db');
        assertContains('applied: 005_add_profile_fields_to_users.sql', $out, '005 actually applied on legacy db');

        $cols = dbColumns($legacy, 'customers');
        assertTrue(in_array('wechat', $cols, true), 'customers.wechat added by the increment');
        assertTrue(in_array('shipping_address', $cols, true), 'customers.shipping_address added by the increment');

        $userCols = dbColumns($legacy, 'users');
        foreach (['phone', 'whatsapp', 'job_title', 'notes', 'updated_at'] as $column) {
            assertTrue(in_array($column, $userCols, true), "users.{$column} added by the increment");
        }
        // app_settings is a brand-new table, so it comes from the baseline — an
        // upgraded database must have it too, not only a freshly built one.
        $pdo = new PDO('sqlite:' . $legacy);
        assertTrue(in_array('app_settings',
            $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN), true),
            'app_settings created by the baseline during the upgrade');
        $rows = (int) $pdo->query('SELECT COUNT(*) FROM app_settings')->fetchColumn();
        assertTrue($rows >= 4, 'setting defaults are seeded on upgrade (got ' . $rows . ')');
        unset($pdo);
    } finally {
        @unlink($legacy);
    }
}

function test_skipped_increments_are_recorded_once(): void
{
    // A skipped increment is still registered in _migrations, so the next run
    // reports "nothing pending" instead of re-deciding every time.
    $db = tempDb('log');
    try {
        [$code, ] = runMigrate($db);
        assertEquals(0, $code, 'first run ok');
        $pdo = new PDO('sqlite:' . $db);
        $recorded = $pdo->query('SELECT name FROM _migrations ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
        assertTrue(in_array('002_add_wechat_to_customers.sql', $recorded, true),
            'skipped 002 is still marked applied');
        $count = (int) $pdo->query('SELECT COUNT(*) FROM _migrations')->fetchColumn();
        unset($pdo);

        [$second, $out] = runMigrate($db);
        assertEquals(0, $second, 'second run ok');
        $pdo = new PDO('sqlite:' . $db);
        assertEquals($count, (int) $pdo->query('SELECT COUNT(*) FROM _migrations')->fetchColumn(),
            'no duplicate _migrations rows on re-run');
        unset($pdo);
    } finally {
        @unlink($db);
    }
}

runCase();
