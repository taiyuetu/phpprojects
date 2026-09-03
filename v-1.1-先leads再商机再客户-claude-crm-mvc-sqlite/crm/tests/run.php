<?php
/**
 * MiniCRM test runner (zero dependencies — plain PHP CLI).
 *
 * Usage:
 *   php tests/run.php                  # run all cases
 *   php tests/run.php CustomerTest     # run one case (name filter, no .php)
 *
 * Each case in tests/cases/ runs in its own PHP process with an isolated DB.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (PHP_SAPI !== 'cli') {
    die('Tests must run from the CLI.');
}

$casesDir = __DIR__ . '/cases';
$files = glob($casesDir . '/*Test.php') ?: [];
sort($files);

$filter = $argv[1] ?? '';
if ($filter !== '') {
    $files = array_values(array_filter(
        $files,
        fn(string $f) => str_contains(basename($f, '.php'), $filter)
    ));
    if (!$files) {
        echo "No test case matches '{$filter}'.\n";
        exit(1);
    }
}

$start = microtime(true);
$totalPass = 0;
$totalFail = 0;
$failures = [];

foreach ($files as $file) {
    $name = basename($file, '.php');
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1';
    $output = []; // exec() APPENDS to the array; reset it per case
    exec($cmd, $output, $code);

    $passLine = '';
    $caseFails = 0;
    foreach ($output as $line) {
        if (str_starts_with($line, 'not ok')) {
            $caseFails++;
        }
        if (str_starts_with($line, '# ')) {
            $passLine = $line;
        }
    }

    if ($code === 0 && $caseFails === 0) {
        echo "[PASS] {$name}\n";
    } else {
        echo "[FAIL] {$name} (exit {$code})\n";
        $failures[$name] = $output;
    }

    // Echo every assertion line (ok / not ok) — nice for reading a single case.
    foreach ($output as $line) {
        if (str_starts_with($line, 'ok ') || str_starts_with($line, 'not ok') || str_starts_with($line, '# ')) {
            echo '    ' . $line . "\n";
        }
    }
    $totalFail += $caseFails;
    $totalPass += (int) preg_match('/^# (\d+) passed/', $passLine, $m) ? $m[1] : 0;
}

echo "\n====================\n";
echo "Cases: " . count($files) . " total, " . (count($files) - count($failures)) . " passed, " . count($failures) . " failed\n";
echo "Assertions: {$totalPass} passed, {$totalFail} failed\n";
echo 'Time: ' . round(microtime(true) - $start, 2) . "s\n";

if ($failures) {
    echo "\nFailed cases:\n";
    foreach (array_keys($failures) as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}
exit(0);
