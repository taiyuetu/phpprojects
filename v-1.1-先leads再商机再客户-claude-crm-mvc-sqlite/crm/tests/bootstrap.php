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
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
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
 * Tiny HTTP client for the HTTP-level cases.
 *
 * Written on PHP streams (file_get_contents + stream_context) so the suite
 * needs no curl extension. If curl IS available we still use the streams path
 * — one code path means one behaviour to debug.
 *
 * Supports what the cases actually need: form POST, cookie persistence across
 * requests, and 3xx redirect following (POST -> GET on 301/302/303).
 */
final class TestHttp
{
    /** @var array<string,string> session cookie jar (name => value) */
    private array $cookies = [];

    /** @return array{code:int, body:string, url:string} */
    public function get(string $url): array
    {
        return $this->request($url, 'GET');
    }

    /** @param array<string,mixed> $fields @return array{code:int, body:string, url:string} */
    public function post(string $url, array $fields): array
    {
        return $this->request($url, 'POST', $fields);
    }

    /** Cheap "is the dev server up yet?" probe. */
    public function reachable(string $url): bool
    {
        return $this->raw($url, 'GET', null, 1.0) !== null;
    }

    /** @return array{code:int, body:string, url:string} */
    private function request(string $url, string $method, array $fields = [], int $maxRedirects = 5): array
    {
        $result = ['code' => 0, 'body' => '', 'url' => $url];
        for ($i = 0; ; $i++) {
            $raw = $this->raw($url, $method, $method === 'POST' ? $fields : null);
            if ($raw === null) {
                return $result;                       // connection refused / timeout
            }
            [$code, $headers, $body] = $raw;
            $this->absorbCookies($headers['set-cookie'] ?? []);
            $result = ['code' => $code, 'body' => $body, 'url' => $url];

            $location = $headers['location'][0] ?? '';
            $isRedirect = $code >= 300 && $code < 400 && $location !== '';
            if (!$isRedirect || $i >= $maxRedirects) {
                return $result;
            }
            $url = $this->resolve($location, $url);
            if ($code !== 307 && $code !== 308) {
                $method = 'GET';                      // mirror browser/curl behaviour
                $fields = [];
            }
        }
    }

    /** @return array{0:int,1:array<string,array<int,string>>,2:string}|null */
    private function raw(string $url, string $method, ?array $fields, float $timeout = 15.0): ?array
    {
        $headers = ['Accept: */*', 'Connection: close'];
        if ($this->cookies) {
            $pairs = [];
            foreach ($this->cookies as $k => $v) {
                $pairs[] = $k . '=' . $v;
            }
            $headers[] = 'Cookie: ' . implode('; ', $pairs);
        }

        $http = [
            'method'          => $method,
            'header'          => implode("\r\n", $headers),
            'ignore_errors'   => true,   // keep 4xx/5xx bodies instead of failing
            'follow_location' => 0,      // redirects handled in request()
            'timeout'         => $timeout,
            'protocol_version' => 1.0,
        ];
        if ($fields !== null) {
            $content = http_build_query($fields, '', '&');
            $http['content'] = $content;
            $http['header'] .= "\r\nContent-Type: application/x-www-form-urlencoded"
                             . "\r\nContent-Length: " . strlen($content);
        }

        $body = @file_get_contents($url, false, stream_context_create(['http' => $http]));
        if ($body === false) {
            return null;
        }

        $code = 0;
        $parsed = [];
        foreach (($http_response_header ?? []) as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $code = (int) $m[1];                    // last status wins
                continue;
            }
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $parsed[strtolower(trim($parts[0]))][] = trim($parts[1]);
            }
        }
        return [$code, $parsed, $body];
    }

    /** @param array<int,string> $setCookieHeaders */
    private function absorbCookies(array $setCookieHeaders): void
    {
        foreach ($setCookieHeaders as $line) {
            $nameValue = explode(';', $line)[0];
            if (!str_contains($nameValue, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $nameValue, 2);
            $name = trim($name);
            $value = trim($value);
            if ($value === '' || $value === 'deleted') {
                unset($this->cookies[$name]);
            } else {
                $this->cookies[$name] = $value;
            }
        }
    }

    private function resolve(string $location, string $from): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $parts = parse_url($from);
        $base = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '')
              . (isset($parts['port']) ? ':' . $parts['port'] : '');
        return $base . (str_starts_with($location, '/') ? $location : '/' . $location);
    }
}

/**
 * Wipe all business data so each test starts from a clean slate.
 * Keeps the seeded admin user (id=1). Order matters for FK constraints.
 */
function resetData(): void
{
    // 每个用例都会清库重灌：进程内的上下文 memo 必须一起失效，
    // 否则上一个用例的“历史”会漏进下一个用例（真跑过一次，症状是计数永远停在 1）。
    if (class_exists('Ai')) {
        Ai::flushHistoryCache();
    }
    $db = Database::connection();
    foreach (['order_items', 'orders', 'attachments', 'follow_ups', 'activities', 'deals', 'leads', 'customers', 'ai_actions'] as $table) {
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
