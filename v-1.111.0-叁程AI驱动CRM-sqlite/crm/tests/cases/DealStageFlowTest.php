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
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
require __DIR__ . '/../bootstrap.php';

function startServerAndLogin(TestHttp $http, string $base): string
{
    // Returns the CSRF token from the logged-in session.
    $loginPage = $http->get($base . '/login')['body'];
    preg_match('/name="csrf_token" value="([^"]+)"/', $loginPage, $m);
    assertTrue(!empty($m[1]), 'csrf token found on login page');

    $http->post($base . '/login', [
        'csrf_token' => $m[1],
        'email'      => 'admin@example.com',
        'password'   => 'password',
    ]);
    return $m[1];
}

/** Update /deals/{id} via the form method-override pattern; returns final HTTP code. */
function putDeal(TestHttp $http, string $base, string $csrf, int $dealId, array $fields): int
{
    // Real HTML forms POST with a hidden _method=PUT field — replicate that,
    // because PHP only populates $_POST for POST requests (not raw PUT bodies).
    return $http->post($base . '/deals/' . $dealId, array_merge(
        ['_method' => 'PUT', 'csrf_token' => $csrf],
        $fields
    ))['code'];
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
        $http = new TestHttp();
        $up = false;
        for ($i = 0; $i < 30; $i++) {
            if ($http->reachable($base . '/login')) {
                $up = true;
                break;
            }
            usleep(100000);
        }
        assertTrue($up, 'server reachable');

        $csrf = startServerAndLogin($http, $base);

        // Act: mark the deal as won (closed_won) with one item line.
        // v1.11 起明细必须引用商品库里的商品（页面与 AI 同一套规则），
        // 所以这里先建商品再选它——业务上也正是“先有目录，再开单”。
        $productId = (int) (new Product())->create(['name' => 'Widget', 'sku' => 'WGT-01',
            'unit' => '件', 'price' => 100, 'status' => 'active', 'owner_id' => 1]);
        $productCode = (new Product())->codeOf((new Product())->find($productId));
        $code = putDeal($http, $base, $csrf, 1, [
            'title'       => 'Flow Deal',
            'customer_id' => 1,
            'value'       => 5000,
            'stage'       => 'closed_won',
            'close_date'  => '',
            'items'       => [
                ['product_id' => $productCode, 'product_name' => 'Widget',
                 'quantity' => 2, 'unit_price' => 100, 'unit' => '件'],
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
        assertEquals($productId, (int) (new OrderItem())->byOrder((int) $orders[0]['id'])[0]['product_id'], '明细链到商品库那条商品');
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
        $http = new TestHttp();
        $up = false;
        for ($i = 0; $i < 30; $i++) {
            if ($http->reachable($base . '/login')) {
                $up = true;
                break;
            }
            usleep(100000);
        }
        assertTrue($up, 'server reachable');

        $csrf = startServerAndLogin($http, $base);

        // Act: mark the deal as lost (closed_lost).
        $code = putDeal($http, $base, $csrf, 2, [
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

/**
 * 回归：新建/编辑“进行中”商机也能看到并保存。
 * 旧 bug：明细区在非成交阶段是 display:none，但里面的商品 select 仍是 required，
 * 浏览器以 “invalid form control … is not focusable” 拦住整张表单 → 商机根本存不了。
 */
function test_create_and_save_open_deal_without_products(): void
{
    $c = new Customer();
    $custId = $c->create(['id' => 9, 'name' => 'Open Deal Cust', 'status' => 'active']);

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
            1 => ['file', sys_get_temp_dir() . '/crm_open.log', 'w'],
            2 => ['file', sys_get_temp_dir() . '/crm_open.err', 'w'],
        ], $pipes, $publicDir);
        assertTrue(is_resource($proc), 'built-in server started');

        $base = "http://127.0.0.1:{$port}";
        $http = new TestHttp();
        $up = false;
        for ($i = 0; $i < 30; $i++) {
            if ($http->reachable($base . '/login')) {
                $up = true;
                break;
            }
            usleep(100000);
        }
        assertTrue($up, 'server reachable');

        $csrf = startServerAndLogin($http, $base);

        // 新建页：商品明细区可见（不再 display:none），行内商品 select 不设 required
        $createPage = $http->get($base . '/deals/create')['body'];
        assertContains('id="items-section"', $createPage, '新建页有商品明细区');
        assertContains('items[0][product_id]', $createPage, '有一行可填的商品选择框');
        assertTrue(!str_contains($createPage, 'id="items-section" class="mt-3" style="display:none;"'),
            '明细区不再被 display:none 藏起来');
        assertTrue(!str_contains($createPage, '> 成交 <') && !str_contains($createPage, '>成交<'),
            '新建阶段下拉不出现 成交/丢单（它们只能从编辑页推进）');

        // 保存一张“进行中”、不带任何商品行的商机 → 必须成功（旧 bug 会 400 拦在浏览器层）
        $code = $http->post($base . '/deals', [
            'csrf_token' => $csrf,
            'title' => '开放商机·不填商品',
            'customer_id' => (string) $custId,
            'value' => '888',
            'stage' => 'open',
            'close_date' => '',
        ])['code'];
        assertEquals(200, $code, '新建开放商机保存成功（200 跟完跳转）');
        $deals = (new Deal())->all('id ASC');
        assertEquals(1, count($deals), '库里有这张商机');
        assertEquals('开放商机·不填商品', $deals[0]['title']);
        assertEquals('open', $deals[0]['stage'], '阶段保持进行中');

        // 编辑这张“进行中”商机：明细区同样可见
        $editPage = $http->get($base . '/deals/' . (int) $deals[0]['id'] . '/edit')['body'];
        assertContains('id="items-section"', $editPage, '编辑开放商机也有商品明细区');
        assertContains('id="items-section" class="mt-3">', $editPage, 'items-section 自身不再带 display:none');
    } finally {
        $restoreEnv();
        if (is_resource($proc)) {
            $pid = proc_get_status($proc)['pid'] ?? 0;
            if ($pid > 0) {
                @exec('taskkill /PID ' . (int) $pid . ' /T /F 2>&1');
            }
            proc_close($proc);
        }
        @unlink(sys_get_temp_dir() . '/crm_open.log');
        @unlink(sys_get_temp_dir() . '/crm_open.err');
    }
}

/**
 * 回归：商机明细行“选了就保存”。
 * - 未成交的商机保存时行存入草稿，重新打开编辑页还在；
 * - 推进到成交时，行变成订单明细并清空草稿；
 * - 已成交的商机再改行（保持在成交状态）会同步进它名下那张订单。
 */
function test_open_deal_lines_persist_then_become_order_and_stay_in_sync(): void
{
    $c = new Customer();
    $custId = $c->create(['id' => 21, 'name' => '明细持久客户', 'status' => 'active']);
    $productId = (int) (new Product())->create(['name' => '轴承6206', 'sku' => 'B6206',
        'unit' => '件', 'price' => 55, 'status' => 'active', 'owner_id' => 1]);
    $dealId = (int) (new Deal())->create(['id' => 21, 'title' => '持久化商机', 'customer_id' => (int) $custId,
        'stage' => 'open', 'value' => 110, 'owner_id' => 1]);

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
            1 => ['file', sys_get_temp_dir() . '/crm_persist.log', 'w'],
            2 => ['file', sys_get_temp_dir() . '/crm_persist.err', 'w'],
        ], $pipes, $publicDir);
        assertTrue(is_resource($proc), 'built-in server started');

        $base = "http://127.0.0.1:{$port}";
        $http = new TestHttp();
        $up = false;
        for ($i = 0; $i < 30; $i++) {
            if ($http->reachable($base . '/login')) {
                $up = true;
                break;
            }
            usleep(100000);
        }
        assertTrue($up, 'server reachable');

        $csrf = startServerAndLogin($http, $base);
        $row = function (int $n, float $qty) use ($productId) {
            return [['product_id' => (string) $productId, 'product_name' => '轴承6206',
                'quantity' => (string) $qty, 'unit_price' => '55', 'unit' => '件']];
        };

        // 1) 未成交阶段：选一行商品保存（进行中，不开单）
        $code = putDeal($http, $base, $csrf, $dealId, [
            'title' => '持久化商机', 'customer_id' => 21, 'value' => '110',
            'stage' => 'open', 'close_date' => '', 'items' => $row(1, 2),
        ]);
        assertEquals(200, $code, '开放商机带明细保存成功');

        // 行必须进了草稿；重新打开编辑页要能看见选中了那件商品
        $deal = dbDeal($dealId);
        assertTrue(!empty($deal['draft_items']) && str_contains((string) $deal['draft_items'], (string) $productId),
            '明细行已存入草稿');
        assertTrue(!(new Order())->byDeal($dealId), '未成交不生成订单');
        $editAgain = $http->get($base . '/deals/' . $dealId . '/edit')['body'];
        assertContains('data-selected="' . $productId . '"', $editAgain, '重新打开时那件商品仍是选中的');

        // 2) 推进到成交：行变成订单明细，草稿清空
        $code = putDeal($http, $base, $csrf, $dealId, [
            'title' => '持久化商机', 'customer_id' => 21, 'value' => '110',
            'stage' => 'closed_won', 'close_date' => '', 'items' => $row(1, 2),
        ]);
        assertEquals(200, $code, '成交保存成功');
        $orders = (new Order())->byDeal($dealId);
        assertEquals(1, count($orders), '成交生成了一张订单');
        $orderItems = (new OrderItem())->byOrder((int) $orders[0]['id']);
        assertEquals(1, count($orderItems), '订单有一条明细');
        assertEquals(2.0, (float) $orderItems[0]['quantity'], '数量 2 同步到订单');
        $deal = dbDeal($dealId);
        assertTrue(empty($deal['draft_items']), '成交后草稿清空');

        // 3) 已成交商机继续改行（保持成交，不开新单）：订单明细必须跟着变
        $code = putDeal($http, $base, $csrf, $dealId, [
            'title' => '持久化商机', 'customer_id' => 21, 'value' => '110',
            'stage' => 'closed_won', 'close_date' => '', 'items' => $row(1, 7),
        ]);
        assertEquals(200, $code, '成交状态再保存成功');
        $orders = (new Order())->byDeal($dealId);
        assertEquals(1, count($orders), '不重复开单');
        $orderItems = (new OrderItem())->byOrder((int) $orders[0]['id']);
        assertEquals(7.0, (float) $orderItems[0]['quantity'], '成交后的行修改同步进订单（数量 2→7）');
        assertEquals(385.0, (float) $orders[0]['amount'], '订单金额按新明细重算');
    } finally {
        $restoreEnv();
        if (is_resource($proc)) {
            $pid = proc_get_status($proc)['pid'] ?? 0;
            if ($pid > 0) {
                @exec('taskkill /PID ' . (int) $pid . ' /T /F 2>&1');
            }
            proc_close($proc);
        }
        @unlink(sys_get_temp_dir() . '/crm_persist.log');
        @unlink(sys_get_temp_dir() . '/crm_persist.err');
    }
}

runCase();
