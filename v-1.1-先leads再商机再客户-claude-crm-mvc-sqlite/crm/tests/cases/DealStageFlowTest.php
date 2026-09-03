<?php
/**
 * Deal stage-transition flow tests over real HTTP.
 *
 * Business rules under test:
 *   1. stage -> closed_won (成交): an order is auto-created AND the deal is
 *      NOT archived — it stays visible on the board.
 *   2. stage -> closed_lost (丢单): the deal IS archived and leaves the board.
 *
 * Verifies both the HTTP response path and the resulting DB state.
 */
require __DIR__ . '/../bootstrap.php';

function startServerAndLogin(string $base, string $cookieFile): string
{
    // Returns the CSRF token from the logged-in session.
    $ch = curl_init($base . '/login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    $loginPage = curl_exec($ch);
    curl_close($ch);
    preg_match('/name="csrf_token" value="([^"]+)"/', (string) $loginPage, $m);
    assertTrue(!empty($m[1]), 'csrf token found on login page');

    $ch = curl_init($base . '/login');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'csrf_token' => $m[1],
        'email'      => 'admin@example.com',
        'password'   => 'password',
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_exec($ch);
    curl_close($ch);
    return $m[1];
}

/** Update /deals/{id} via the form method-override pattern; returns final HTTP code. */
function putDeal(string $base, string $cookieFile, string $csrf, int $dealId, array $fields): int
{
    // Real HTML forms POST with a hidden _method=PUT field — replicate that,
    // because PHP only populates $_POST for POST requests (not raw PUT bodies).
    $ch = curl_init($base . '/deals/' . $dealId);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array_merge(
        ['_method' => 'PUT', 'csrf_token' => $csrf],
        $fields
    )));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code;
}

function dbDeal(int $id): array
{
    return (new Deal())->find($id) ?: [];
}

function test_won_creates_order_but_does_not_archive(): void
{
    // Arrange: customer + open deal.
    $c = new Customer();
    $custId = $c->create(['id' => 1, 'name' => 'Flow Cust', 'status' => 'active']);
    $d = new Deal();
    $d->create(['id' => 1, 'title' => 'Flow Deal', 'customer_id' => (int) $custId, 'stage' => 'open', 'owner_id' => 1]);

    // Serve the app against the shared test DB.
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

    $proc = null;
    try {
        $port = random_int(18000, 18999);
        $serverCmd = escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port
            . ' -t ' . escapeshellarg($publicDir);
        $proc = proc_open($serverCmd, [
            0 => ['pipe', 'r'],
            1 => ['file', sys_get_temp_dir() . '/crm_flow.log', 'w'],
            2 => ['file', sys_get_temp_dir() . '/crm_flow.err', 'w'],
        ], $pipes, $publicDir);
        assertTrue(is_resource($proc), 'built-in server started');

        $base = "http://127.0.0.1:{$port}";
        $up = false;
        for ($i = 0; $i < 30; $i++) {
            $ch = curl_init($base . '/login');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            $body = curl_exec($ch);
            curl_close($ch);
            if ($body !== false) {
                $up = true;
                break;
            }
            usleep(100000);
        }
        assertTrue($up, 'server reachable');

        $cookieFile = sys_get_temp_dir() . '/crm_flow_cookies.txt';
        $csrf = startServerAndLogin($base, $cookieFile);

        // Act: mark the deal as won (closed_won) with one item line.
        $code = putDeal($base, $cookieFile, $csrf, 1, [
            'title'       => 'Flow Deal',
            'customer_id' => 1,
            'value'       => 5000,
            'stage'       => 'closed_won',
            'close_date'  => '',
            'items'       => [
                ['product_name' => 'Widget', 'quantity' => 2, 'unit_price' => 100, 'unit' => '件'],
            ],
        ]);
        assertEquals(200, $code, 'won update redirects to a 200 page');

        // Assert DB state.
        $deal = dbDeal(1);
        assertEquals('closed_won', $deal['stage'], 'stage is closed_won');
        assertEquals(0, (int) $deal['archived'], 'won deal is NOT archived');

        $orders = (new Order())->byDeal(1);
        assertEquals(1, count($orders), 'one order auto-created from the won deal');
        assertEquals(200.0, (float) $orders[0]['amount'], 'order amount = item subtotal');
        assertEquals(1, count((new OrderItem())->byOrder((int) $orders[0]['id'])), 'order item saved');

        @unlink($cookieFile);
    } finally {
        $restoreEnv();
        if (is_resource($proc)) {
            $pid = proc_get_status($proc)['pid'] ?? 0;
            if ($pid > 0) {
                @exec('taskkill /PID ' . (int) $pid . ' /T /F 2>&1');
            }
            proc_close($proc);
        }
        @unlink(sys_get_temp_dir() . '/crm_flow.log');
        @unlink(sys_get_temp_dir() . '/crm_flow.err');
    }
}

function test_lost_archives_the_deal(): void
{
    // Arrange: customer + open deal.
    $c = new Customer();
    $custId = $c->create(['id' => 2, 'name' => 'Lost Cust', 'status' => 'active']);
    $d = new Deal();
    $d->create(['id' => 2, 'title' => 'Lost Deal', 'customer_id' => (int) $custId, 'stage' => 'open', 'owner_id' => 1]);

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

    $proc = null;
    try {
        $port = random_int(18000, 18999);
        $serverCmd = escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port
            . ' -t ' . escapeshellarg($publicDir);
        $proc = proc_open($serverCmd, [
            0 => ['pipe', 'r'],
            1 => ['file', sys_get_temp_dir() . '/crm_flow2.log', 'w'],
            2 => ['file', sys_get_temp_dir() . '/crm_flow2.err', 'w'],
        ], $pipes, $publicDir);
        assertTrue(is_resource($proc), 'built-in server started');

        $base = "http://127.0.0.1:{$port}";
        $up = false;
        for ($i = 0; $i < 30; $i++) {
            $ch = curl_init($base . '/login');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            $body = curl_exec($ch);
            curl_close($ch);
            if ($body !== false) {
                $up = true;
                break;
            }
            usleep(100000);
        }
        assertTrue($up, 'server reachable');

        $cookieFile = sys_get_temp_dir() . '/crm_flow2_cookies.txt';
        $csrf = startServerAndLogin($base, $cookieFile);

        // Act: mark the deal as lost (closed_lost).
        $code = putDeal($base, $cookieFile, $csrf, 2, [
            'title'       => 'Lost Deal',
            'customer_id' => 2,
            'value'       => 3000,
            'stage'       => 'closed_lost',
            'close_date'  => '',
        ]);
        assertEquals(200, $code, 'lost update redirects to a 200 page');

        // Assert DB state.
        $deal = dbDeal(2);
        assertEquals('closed_lost', $deal['stage'], 'stage is closed_lost');
        assertEquals(1, (int) $deal['archived'], 'lost deal IS archived');
        assertTrue(!empty($deal['archived_at']), 'archived_at recorded');

        // No order should be created for a lost deal.
        assertEquals(0, count((new Order())->byDeal(2)), 'no order for a lost deal');

        @unlink($cookieFile);
    } finally {
        $restoreEnv();
        if (is_resource($proc)) {
            $pid = proc_get_status($proc)['pid'] ?? 0;
            if ($pid > 0) {
                @exec('taskkill /PID ' . (int) $pid . ' /T /F 2>&1');
            }
            proc_close($proc);
        }
        @unlink(sys_get_temp_dir() . '/crm_flow2.log');
        @unlink(sys_get_temp_dir() . '/crm_flow2.err');
    }
}

runCase();
