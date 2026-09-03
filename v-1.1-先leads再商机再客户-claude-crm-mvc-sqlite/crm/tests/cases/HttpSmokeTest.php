<?php
/**
 * HTTP smoke tests — boots PHP's built-in server against an isolated DB and
 * verifies the whole request path (Router → Controller → View → autoloader)
 * returns HTTP 200 for every main page after a real login.
 *
 * This is the layer where the old "Class not found in view" bug used to
 * surface, so these tests would have caught it.
 */
require __DIR__ . '/../bootstrap.php';

function test_all_main_pages_respond_200(): void
{
    // resetData() wiped the seed rows; re-create the records the page URLs
    // reference so detail/edit pages find something (otherwise they 302).
    $c = new Customer();
    $c->create(['id' => 1, 'name' => 'Smoke Customer', 'status' => 'active']);
    $l = new Lead();
    $l->create(['id' => 1, 'title' => 'Smoke Lead', 'contact_name' => 'A', 'status' => 'new', 'owner_id' => 1]);
    $d = new Deal();
    $d->create(['id' => 1, 'title' => 'Smoke Deal', 'customer_id' => 1, 'stage' => 'open', 'owner_id' => 1]);
    $o = new Order();
    $o->create([
        'id' => 1, 'order_number' => 'ORD-SMOKE-0001', 'customer_id' => 1,
        'title' => 'Smoke Order', 'status' => 'pending', 'owner_id' => 1,
    ]);

    // Serve from public/. Point the web process at the same isolated DB by
    // writing a temporary .env (config.php reads BASE_PATH/.env; .env is
    // gitignored). Back up any existing file and restore it afterwards.
    $publicDir = BASE_PATH . '/public';
    $envFile = BASE_PATH . '/.env';
    $hadEnv = file_exists($envFile);
    $envBackup = $hadEnv ? file_get_contents($envFile) : '';
    file_put_contents($envFile, 'DB_PATH=' . $GLOBALS['TEST_DB_PATH'] . PHP_EOL);

    $restoreEnv = function () use ($envFile, $hadEnv, $envBackup): void {
        if ($hadEnv) {
            file_put_contents($envFile, $envBackup);
        } else {
            @unlink($envFile);
        }
    };

    // Kill the whole process tree. proc_terminate on Windows only kills the
    // cmd shell that proc_open spawns — the php -S child survives and locks
    // files/ports. taskkill /T handles the full tree.
    $killServer = function ($proc) {
        if (!is_resource($proc)) {
            return;
        }
        $pid = proc_get_status($proc)['pid'] ?? 0;
        if ($pid > 0) {
            @exec('taskkill /PID ' . (int) $pid . ' /T /F 2>&1');
        }
        proc_close($proc);
    };

    $proc = null;
    try {
        $port = random_int(18000, 18999);
        $uniq = bin2hex(random_bytes(3));
        $serverCmd = escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port
            . ' -t ' . escapeshellarg($publicDir);
        $proc = proc_open($serverCmd, [
            0 => ['pipe', 'r'],
            1 => ['file', sys_get_temp_dir() . "/crm_srv_{$uniq}.log", 'w'],
            2 => ['file', sys_get_temp_dir() . "/crm_srv_{$uniq}.err", 'w'],
        ], $pipes, $publicDir);
        assertTrue(is_resource($proc), 'built-in server started');
        $base = "http://127.0.0.1:{$port}";
        $up = false;
        for ($i = 0; $i < 30; $i++) {
            $ch = curl_init($base . '/login');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body !== false) {
                $up = true;
                break;
            }
            usleep(100000);
        }
        assertTrue($up, 'server became reachable');

        // Grab CSRF token from the login page and log in.
        $ch = curl_init($base . '/login');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, sys_get_temp_dir() . '/crm_test_cookies.txt');
        $loginPage = curl_exec($ch);
        curl_close($ch);
        assertTrue(is_string($loginPage) && str_contains($loginPage, 'csrf_token'), 'login page shows form');

        preg_match('/name="csrf_token" value="([^"]+)"/', $loginPage, $m);
        assertTrue(!empty($m[1]), 'csrf token found on login page');

        $ch = curl_init($base . '/login');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'csrf_token' => $m[1],
            'email' => 'admin@example.com',
            'password' => 'password',
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, sys_get_temp_dir() . '/crm_test_cookies.txt');
        curl_setopt($ch, CURLOPT_COOKIEJAR, sys_get_temp_dir() . '/crm_test_cookies.txt');
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_exec($ch);
        curl_close($ch);

        // After login we should land on the dashboard.
        $pages = [
            '/', '/customers', '/customers/create', '/customers/1',
            '/customers/1/edit', '/leads', '/leads/create', '/leads/1/edit',
            '/deals', '/deals/create', '/deals/1/edit', '/deals/archived',
            '/orders', '/orders/create', '/orders/1', '/orders/1/edit', '/help',
        ];
        $cookiesFile = sys_get_temp_dir() . '/crm_test_cookies.txt';
        foreach ($pages as $page) {
            $ch = curl_init($base . $page);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiesFile);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiesFile);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            assertTrue($code === 200, "{$page} returns HTTP 200 (got {$code})");
        }
    } finally {
        $restoreEnv();
        $killServer($proc);
        @unlink(sys_get_temp_dir() . '/crm_test_cookies.txt');
        @unlink(sys_get_temp_dir() . '/crm_test_server.log');
        @unlink(sys_get_temp_dir() . '/crm_test_server.err.log');
    }
}

runCase();
