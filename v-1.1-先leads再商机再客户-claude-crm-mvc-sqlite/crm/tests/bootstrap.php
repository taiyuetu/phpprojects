<?php
/**
 * Test bootstrap — runs the REAL application core (config, autoloader,
 * Database, Model) against an isolated throwaway SQLite database.
 *
 * Each test case file runs in its own PHP process, so cases cannot pollute
 * each other. The database is built by the real `database/migrate.php`, which
 * means every test run also exercises the migration tooling.
 *
 * Usage: php tests/cases/FooTest.php   (prints "ok/not ok", exit code 0/1)
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (PHP_SAPI !== 'cli') {
    die('Tests must run from the CLI.');
}

define('BASE_PATH', dirname(__DIR__));  // project root
define('APP_PATH', BASE_PATH . '/app');

date_default_timezone_set('Asia/Shanghai');

// ---- Isolated database (unique per process) ----
$testDbPath = sys_get_temp_dir() . '/crm_test_' . getmypid() . '_' . bin2hex(random_bytes(3)) . '.sqlite';
@unlink($testDbPath);

$migrateScript = BASE_PATH . '/database/migrate.php';
$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($migrateScript)
     . ' --db=' . escapeshellarg($testDbPath) . ' 2>&1';
exec($cmd, $migOutput, $migCode);
if ($migCode !== 0) {
    fwrite(STDERR, "migrate.php failed to build test DB:\n" . implode("\n", $migOutput) . "\n");
    exit(1);
}

putenv('DB_PATH=' . $testDbPath);
$_ENV['DB_PATH'] = $testDbPath;
$GLOBALS['TEST_DB_PATH'] = $testDbPath;

// ---- Load the app exactly like bootstrap.php does (minus routing/dispatch) ----
require APP_PATH . '/core/autoloader.php';
require APP_PATH . '/config/config.php';

// URL_ROOT is normally defined by bootstrap.php from SCRIPT_NAME.
if (!defined('URL_ROOT')) {
    define('URL_ROOT', '');
}

require APP_PATH . '/core/helpers.php';
require APP_PATH . '/core/Database.php';
require APP_PATH . '/core/Model.php';
require APP_PATH . '/core/Controller.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ---- Tiny assertion helpers ----
function assertTrue(bool $cond, string $msg = ''): void
{
    if (!$cond) {
        throw new RuntimeException('assertTrue failed' . ($msg ? ': ' . $msg : ''));
    }
}

function assertEquals($expected, $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            'assertEquals failed%s: expected %s, got %s',
            $msg ? ' — ' . $msg : '',
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assertContains(string $needle, string $haystack, string $msg = ''): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException(sprintf(
            'assertContains failed%s: "%s" not found in "%s"',
            $msg ? ' — ' . $msg : '',
            $needle,
            $haystack
        ));
    }
}

/**
 * Wipe all business data so each test starts from a clean slate.
 * Keeps the seeded admin user (id=1). Order matters for FK constraints.
 */
function resetData(): void
{
    $db = Database::connection();
    foreach (['order_items', 'orders', 'attachments', 'follow_ups', 'activities', 'deals', 'leads', 'customers'] as $table) {
        $db->exec("DELETE FROM {$table}");
    }
}

/**
 * Case runner: finds every global function named test_* in this process and
 * runs it with a clean database. Prints ok/not ok lines; exits 1 on failure.
 */
function runCase(): void
{
    $all = get_defined_functions()['user'];
    $tests = array_values(array_filter($all, fn(string $f) => str_starts_with($f, 'test_')));

    if (!$tests) {
        fwrite(STDERR, "No test_* functions found.\n");
        exit(1);
    }

    $pass = 0;
    $fail = 0;
    foreach ($tests as $t) {
        try {
            resetData();
            $t();
            echo "ok      - {$t}\n";
            $pass++;
        } catch (Throwable $e) {
            echo "not ok  - {$t}: {$e->getMessage()}\n";
            $fail++;
        }
    }

    echo "# {$pass} passed, {$fail} failed\n";
    @unlink($GLOBALS['TEST_DB_PATH'] ?? '');
    exit($fail ? 1 : 0);
}
