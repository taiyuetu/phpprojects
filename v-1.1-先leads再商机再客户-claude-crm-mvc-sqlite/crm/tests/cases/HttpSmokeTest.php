<?php
/**
 * HTTP smoke tests — boots PHP's built-in server against an isolated DB and
 * verifies the whole request path (Router → Controller → View → autoloader).
 *
 * Two cases:
 *   1. every main page renders 200 for a signed-in user, plus the leads list
 *      column contract (流失原因 only on the 已流失 tab);
 *   2. the 设置 page: app information (admin) and profile editing, including the
 *      promise that a profile change is immediately visible as the 负责人 of
 *      customers — because business rows store users.id and JOIN the name back.
 *
 * This is also the layer where the old "Class not found in view" bug used to
 * surface, so these tests would have caught it.
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
require __DIR__ . '/../bootstrap.php';

/**
 * Start the app on a throwaway port against this process' DB, log in as the
 * seeded admin, hand ($http, $base, $csrf) to $fn, then always shut down.
 */
function withTestServer(string $tag, callable $fn): void
{
    $publicDir = BASE_PATH . '/public';
    $envFile = BASE_PATH . '/.env';
    $hadEnv = file_exists($envFile);
    $envBackup = $hadEnv ? file_get_contents($envFile) : '';
    // config.php reads BASE_PATH/.env, so the web process shares our test DB.
    file_put_contents($envFile, 'DB_PATH=' . $GLOBALS['TEST_DB_PATH'] . PHP_EOL);

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
            1 => ['file', sys_get_temp_dir() . "/crm_{$tag}.log", 'w'],
            2 => ['file', sys_get_temp_dir() . "/crm_{$tag}.err", 'w'],
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
        assertTrue($up, 'server became reachable');

        // Log in through the real form (CSRF protected).
        $loginPage = $http->get($base . '/login')['body'];
        assertTrue(str_contains($loginPage, 'csrf_token'), 'login page shows form');
        preg_match('/name="csrf_token" value="([^"]+)"/', $loginPage, $m);
        assertTrue(!empty($m[1]), 'csrf token found on login page');

        $login = $http->post($base . '/login', [
            'csrf_token' => $m[1],
            'email'      => 'admin@example.com',
            'password'   => 'password',
        ]);
        // After a successful login we end up on the dashboard, not back on /login.
        assertTrue(!str_contains($login['url'], '/login'),
            'login redirected away from /login (landed on ' . $login['url'] . ')');

        $fn($http, $base, $m[1]);
    } finally {
        if ($hadEnv) {
            file_put_contents($envFile, $envBackup);
        } else {
            @unlink($envFile);
        }
        $killServer($proc);
        @unlink(sys_get_temp_dir() . "/crm_{$tag}.log");
        @unlink(sys_get_temp_dir() . "/crm_{$tag}.err");
    }
}

function test_all_main_pages_respond_200(): void
{
    // resetData() wiped the seed rows; re-create the records the page URLs
    // reference so detail/edit pages find something (otherwise they 302).
    $c = new Customer();
    $c->create(['id' => 1, 'name' => 'Smoke Customer', 'status' => 'active', 'owner_id' => 1]);
    $l = new Lead();
    $l->create(['id' => 1, 'title' => 'Smoke Lead', 'contact_name' => 'A', 'status' => 'new', 'owner_id' => 1]);
    $d = new Deal();
    $d->create(['id' => 1, 'title' => 'Smoke Deal', 'customer_id' => 1, 'stage' => 'open', 'owner_id' => 1]);
    $o = new Order();
    $o->create([
        'id' => 1, 'order_number' => 'ORD-SMOKE-0001', 'customer_id' => 1,
        'title' => 'Smoke Order', 'status' => 'pending', 'owner_id' => 1,
    ]);

    withTestServer('smoke', function (TestHttp $http, string $base, string $csrf): void {
        // Pages expected to render 200 for a signed-in user (only real routes —
        // leads and deals have no show page, they go from list straight to edit).
        $pages = [
            '/', '/customers', '/customers/create', '/customers/1',
            '/customers/1/edit', '/leads', '/leads/create', '/leads/1/edit',
            '/deals', '/deals/create', '/deals/1/edit', '/deals/archived',
            '/orders', '/orders/create', '/orders/1', '/orders/1/edit', '/help',
            '/settings', '/settings?tab=app', '/settings?tab=profile', '/settings?tab=password',
            // The "已流失" tab is the only leads tab that carries the 流失原因
            // column — walk it so a th/td mismatch surfaces as a non-200 or via
            // the column assertions below.
            '/leads?status=new', '/leads?status=contacted', '/leads?status=qualified',
            '/leads?status=lost',
        ];
        foreach ($pages as $page) {
            $res = $http->get($base . $page);
            assertTrue($res['code'] === 200, "{$page} returns HTTP 200 (got {$res['code']})");
        }

        // Column contract of the leads list: 流失原因 only on the "已流失" tab.
        $lostTab = $http->get($base . '/leads?status=lost')['body'];
        $allTab = $http->get($base . '/leads')['body'];
        assertTrue(str_contains($lostTab, '<th>流失原因</th>'), 'lost tab shows the 流失原因 column');
        assertTrue(!str_contains($allTab, '<th>流失原因</th>'), 'all tab hides the 流失原因 column');
        assertTrue(!str_contains($http->get($base . '/leads?status=new')['body'], '<th>流失原因</th>'),
            'new tab hides the 流失原因 column');
    });
}

function test_settings_page_syncs_profile_into_customer_owner(): void
{
    $c = new Customer();
    $c->create(['id' => 1, 'name' => '张三的公司', 'status' => 'active', 'owner_id' => 1]);

    withTestServer('settings', function (TestHttp $http, string $base, string $csrf): void {
        // Baseline: the customer list shows the admin's current name as 负责人.
        $adminName = trim((string) (new User())->find(1)['name']);
        assertTrue($adminName !== '', 'seeded admin has a name');
        assertTrue(str_contains($http->get($base . '/customers')['body'], $adminName),
            'customer list shows the owner name before the edit');

        // 设置 page offers both halves of the feature.
        $page = $http->get($base . '/settings')['body'];
        assertContains('个人信息', $page, 'profile form rendered');
        assertContains('应用信息', $page, 'admin sees the app-info form');
        assertContains('信息同步范围', $page, 'the page states what a profile edit reaches');

        // 1) App information: system name + currency, applied to every page.
        $http->post($base . '/settings/app', [
            'csrf_token'      => $csrf,
            'app_name'        => '环球贸易 CRM',
            'app_tagline'     => '线索 → 商机 → 客户',
            'company_name'    => '环球贸易有限公司',
            'copyright_notice' => '© 2026 环球贸易有限公司',
            'currency_symbol' => 'NT$',
        ]);
        $dashboard = $http->get($base . '/')['body'];
        assertContains('环球贸易 CRM', $dashboard, 'sidebar/title use the stored app name');
        assertContains('线索 → 商机 → 客户', $dashboard, 'tagline is shown');
        assertContains('环球贸易有限公司', $dashboard, 'company name is shown');
        assertContains('© 2026 环球贸易有限公司', $dashboard, 'the copyright line is editable and shown in the sidebar');
        assertContains('NT$', $dashboard, 'money() uses the stored currency symbol');
        assertTrue(!str_contains($dashboard, APP_NAME), 'the default name is fully replaced');

        // 2) Profile information: rename the admin (who owns the customer).
        $http->post($base . '/settings/profile', [
            'csrf_token' => $csrf,
            'name'       => '李四',
            'email'      => 'admin@example.com',
            'job_title'  => '销售总监',
            'phone'      => '555-0188',
            'whatsapp'   => '+886900000000',
            'notes'      => '',
        ]);

        // The customer row was never touched — only users.id is stored on it, so
        // the 负责人 column re-resolves to the new name on the next read.
        $customers = $http->get($base . '/customers')['body'];
        assertContains('李四', $customers, 'customer list shows the new owner name');
        assertTrue(!str_contains($customers, $adminName), 'the old name is gone (no stale copy)');
        assertEquals('李四', (new Customer())->findWithOwner(1)['owner_name'], 'DB read resolves the new name too');
        assertEquals(1, (int) (new Customer())->find(1)['owner_id'], 'the customer still points at the same user id');

        // The detail page renders the owner's full profile, also live.
        $detail = $http->get($base . '/customers/1')['body'];
        assertContains('销售总监', $detail, 'owner job title from the profile is shown');
        assertContains('555-0188', $detail, "owner's phone from the profile is shown");

        // ...and the session snapshot followed the edit (topbar / forms).
        $profilePage = $http->get($base . '/settings?tab=profile')['body'];
        assertContains('李四', $profilePage, 'settings page shows the saved profile');
        assertContains('销售总监', $profilePage, 'with the new job title');

        // 3) A non-admin cannot change application-wide information.
        $http->post($base . '/logout', ['csrf_token' => $csrf]);
        $regPage = $http->get($base . '/register')['body'];
        preg_match('/name="csrf_token" value="([^"]+)"/', $regPage, $rm);
        $http->post($base . '/register', [
            'csrf_token'      => $rm[1] ?? '',
            'name'            => '小 sales',
            'email'           => 'lowbie@example.com',
            'password'        => 'password',
            'password_confirm' => 'password',
        ]);
        $salesSettings = $http->get($base . '/settings')['body'];
        assertTrue(!str_contains($salesSettings, 'name="app_name"'),
            'sales users are not offered the app-info form');
        $http->post($base . '/settings/app', [
            'csrf_token' => $rm[1] ?? '',
            'app_name'   => '劫持名称',
            'currency_symbol' => '£',
        ]);
        $afterHijack = $http->get($base . '/')['body'];
        assertContains('环球贸易 CRM', $afterHijack, 'a non-admin cannot overwrite app settings');
        assertTrue(!str_contains($afterHijack, '劫持名称'), 'the rejected value was not stored');
    });
}

runCase();
