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
        $loginPageRes = $http->get($base . '/login');
        $loginPage = $loginPageRes['body'];
        assertTrue(str_contains($loginPage, 'csrf_token'), 'login page shows form');

        // First session_start is what issues the session cookie, so its attributes
        // (set in bootstrap.php before session_start) must be visible right here.
        $cookie = implode(' ', $http->lastHeaders['set-cookie'] ?? []);
        assertContains('HttpOnly', $cookie, 'session cookie is HttpOnly');
        assertContains('SameSite=Lax', $cookie, 'session cookie is SameSite=Lax');

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
            '/settings?tab=ai', '/ai', '/ai/history',
            // The "已流失" tab is the only leads tab that carries the 流失原因
            // column — walk it so a th/td mismatch surfaces as a non-200 or via
            // the column assertions below.
            '/leads?status=new', '/leads?status=contacted', '/leads?status=qualified',
            '/leads?status=lost',
            // 带关键词搜索的列表页：搜索态视图分支（含清除按钮、翻页链接拼接）必须也能 200
            '/customers?q=xyz', '/leads?q=xyz', '/leads?status=new&q=xyz',
            '/orders?q=xyz', '/orders?status=shipped&q=xyz',
            '/deals?q=xyz', '/deals/archived?q=xyz',
            // 商品 CSV 导出：真下载（BOM+表头），必须 200 且不泄露 PHP 源
            '/products/export',
        ];
        foreach ($pages as $page) {
            $res = $http->get($base . $page);
            assertTrue($res['code'] === 200, "{$page} returns HTTP 200 (got {$res['code']})");
            // A file header that escaped php mode shows up as a stray doc block
            // comment — this actually happened once, hence the explicit guard.
            assertTrue(!preg_match('~^\s*/\*\*\s*\R\s*\*\s*Copyright~m', $res['body']),
                "{$page} does not leak a source-file header comment into the page");
        }

        // 使用说明页的技术参考区必须是实时生成的真数据，且不能漏掉文档承诺的内容。
        $help = $http->get($base . '/help')['body'];
        foreach (['技术参考' => 'tech section', '数据字典' => 'data dictionary heading',
                  'leads.status' => 'live enum from CHECK', 'ai_actions' => 'AI audit table documented',
                  'deepseek-v4-flash' => 'AI preset model ids',
                  'canManageResource' => 'ownership/permission rule spelled out',
                  'faqAccordion' => 'FAQ block intact', 'help/context' => 'link to the text map'] as $needle => $what) {
            assertContains($needle, $help, "help page documents the {$what}");
        }
        assertTrue(substr_count($help, '<div') === substr_count($help, '</div>'),
            'help page HTML is balanced (' . substr_count($help, '<div') . '/' . substr_count($help, '</div') . ')');
        assertTrue(!str_contains($help, '<?'), 'help page never leaks raw PHP');

        // The plain-text map an AI can be handed.
        $ctx = $http->get($base . '/help/context');
        assertEquals(200, $ctx['code'], 'context endpoint answers');
        assertContains('## 业务与流程', $ctx['body'], 'context opens with the business flows');
        assertContains('线索 lead → 商机 deal', $ctx['body'], 'the flow chain is stated');
        foreach (['customers', 'leads', 'deals', 'orders', 'ai_actions', '/customers/{id}', 'POST', 'CSRF'] as $needle) {
            assertContains($needle, $ctx['body'], "context covers {$needle}");
        }
        assertTrue(strpos($ctx['body'], 'sk-') === false, 'context contains no API keys');

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

/**
 * The delete rails over real HTTP, using the offline demo model so no key and no
 * network is involved: queries answer immediately, deletes wait for a human,
 * show their blast radius, and only then take data down.
 */
function test_ai_deletes_wait_for_a_human_and_show_what_they_take(): void
{
    withTestServer('ai', function (TestHttp $http, string $base, string $csrf): void {
        (new Setting())->setMany(['ai_enabled' => '1', 'ai_provider' => 'mock', 'ai_mode' => 'preview',
            'ai_allow_delete' => '1'], 1);

        $custId = (int) (new Customer())->create(['name' => '联盛机械采购部', 'company' => '联盛机械',
            'status' => 'active', 'owner_id' => 1]);
        $leadId = (int) (new Lead())->create(['title' => '轴承询盘', 'company' => '联盛机械',
            'contact_name' => '林小姐', 'status' => 'new', 'owner_id' => 1, 'customer_id' => $custId]);
        $db = Database::connection();

        // 1) a pure query: runs at once, changes nothing, and the answer is on the page
        $http->post($base . '/ai/plan', ['csrf_token' => $csrf, 'instruction' => '查一下 联盛']);
        $q = $db->query("SELECT * FROM ai_actions ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        assertEquals('executed', $q['status'], '只查不改的请求当场就结束了');
        $page = $http->get($base . '/ai?plan=' . $q['id'])['body'];
        assertContains('未改动任何数据', $page, '页面说清楚这只是查询');
        assertContains('LEAD-' . sprintf('%06d', $leadId), $page, '结果里带真实编号');
        assertEquals(1, (int) $db->query('SELECT COUNT(*) FROM leads')->fetchColumn(), '一行数据都没动');

        // 2) a delete: it must NOT execute itself
        $http->post($base . '/ai/plan', ['csrf_token' => $csrf, 'instruction' => '删掉线索 #' . $leadId]);
        $d = $db->query("SELECT * FROM ai_actions ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        assertEquals('pending', $d['status'], '删除停在待确认');
        assertTrue((int) $db->query('SELECT COUNT(*) FROM leads WHERE id = ' . $leadId)->fetchColumn() === 1,
            '计划生成阶段绝对不删数据');
        $preview = $http->get($base . '/ai?plan=' . $d['id'])['body'];
        assertContains('将删除 1 条记录', $preview, '页顶橙色警告给出条数');
        assertContains('合计约', $preview, '并给出合计影响');
        assertContains('将删除', $preview, '并列出要删哪一条');
        assertContains('轴承询盘', $preview, '带标题，不只是一串 ID');
        assertContains('确认执行（含删除）', $preview, '按钮上写明含删除');

        // 3) auto 模式也拦不住？一一拦得住：删除不参与自动执行
        (new Setting())->setMany(['ai_mode' => 'auto'], 1);
        $http->post($base . '/ai/plan', ['csrf_token' => $csrf, 'instruction' => '删掉线索 #' . $leadId]);
        $auto = $db->query("SELECT * FROM ai_actions ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        assertEquals('pending', $auto['status'], '开了自动执行，删除依旧等人工确认');
        assertTrue((int) $db->query('SELECT COUNT(*) FROM leads WHERE id = ' . $leadId)->fetchColumn() === 1,
            '数据仍然完好');
        (new Setting())->setMany(['ai_mode' => 'preview'], 1);

        // 4) the master switch refuses it outright
        (new Setting())->setMany(['ai_allow_delete' => '0'], 1);
        $http->post($base . '/ai/plan', ['csrf_token' => $csrf, 'instruction' => '删掉线索 #' . $leadId]);
        $off = $db->query("SELECT * FROM ai_actions ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        assertEquals('failed', $off['status'], '总开关关掉后不会生成可执行计划');
        assertContains('删除权限已关闭', (string) $off['error']);
        $blocked = $http->get($base . '/ai?plan=' . $off['id'])['body'];
        assertTrue(!str_contains($blocked, '确认执行（含删除）'), '被拒的删除不给确认按钮');
        (new Setting())->setMany(['ai_allow_delete' => '1'], 1);

        // 5) the human confirms -> it goes, with a snapshot left behind
        $http->post($base . '/ai/apply', ['csrf_token' => $csrf, 'id' => $d['id']]);
        assertEquals(0, (int) $db->query('SELECT COUNT(*) FROM leads WHERE id = ' . $leadId)->fetchColumn(),
            '确认之后才真的删');
        $done = $db->query('SELECT status, result_json FROM ai_actions WHERE id = ' . (int) $d['id'])->fetch(PDO::FETCH_ASSOC);
        assertEquals('executed', $done['status']);
        assertContains('轴承询盘', (string) $done['result_json'], '被删内容留了快照');
        assertContains('title=', (string) $done['result_json'], '快照存的是被删那一行的字段');
        assertContains('理由', (string) $done['result_json'], '连由一起存着');

        // 6) the audit row itself can be dropped afterwards, by a human this time
        $history = $http->get($base . '/ai/history')['body'];
        assertContains('/ai/history/' . (int) $d['id'] . '/delete', $history, '记录页有删除入口');
        $http->post($base . '/ai/history/' . (int) $d['id'] . '/delete', ['csrf_token' => $csrf]);
        assertEquals(0, (int) $db->query('SELECT COUNT(*) FROM ai_actions WHERE id = ' . (int) $d['id'])->fetchColumn(),
            '确认过的记录能被删掉');

        (new Setting())->setMany(['ai_enabled' => '0', 'ai_provider' => 'mock', 'ai_mode' => 'preview',
            'ai_allow_delete' => '1'], 1);
    });
}


/**
 * The AI safety model over the real HTTP stack: enabling it is an admin action,
 * 预览确认 writes nothing until 确认执行 is posted, and the offline demo
 * provider keeps the test free of keys and network calls.
 */
function test_ai_preview_then_confirm_writes_data_once(): void
{
    withTestServer('ai', function (TestHttp $http, string $base, string $csrf): void {
        // Off by default: the page explains itself and refuses to run.
        $off = $http->get($base . '/ai');
        assertContains('未启用', $off['body'], 'AI ships disabled');
        $blocked = $http->post($base . '/ai/plan', ['csrf_token' => $csrf, 'instruction' => '新建线索：测试']);
        assertTrue(!str_contains($blocked['body'], '确认执行'), 'a disabled assistant produces no runnable plan');

        // Admin turns on the offline demo provider in 预览确认 mode.
        $http->post($base . '/settings/app', [
            'csrf_token'     => $csrf,
            'ai_enabled'     => '1',
            'ai_provider'    => 'mock',
            'ai_mode'        => 'preview',
            'ai_model'       => '',
            'ai_base_url'    => '',
            'ai_temperature' => '0.2',
        ]);
        $on = $http->get($base . '/settings?tab=ai')['body'];
        assertContains('已启用', $on, 'the page reports the new state');
        assertContains('演示模型', $on, 'and the chosen offline provider');

        // The wait state: the page must show the time budget it is about to use.
        $aiOn = $http->get($base . '/ai')['body'];
        assertContains('id="ai-plan-form"', $aiOn, 'the wait-state script has a form to bind');
        assertContains('data-budget="45"', $aiOn, 'the configured timeout reaches the submit button');
        assertContains('超时 45s', $aiOn, 'and is visible as a badge');
        assertContains('≤800 tokens', $aiOn, 'the answer cap is visible too');

        // Ask for a lead. Preview must not create it.
        $http->post($base . '/ai/plan', [
            'csrf_token'  => $csrf,
            'instruction' => '新建线索：联系人 林小姐，公司 联盛机械，邮箱 lin@liansheng.com，'
                . '电话 +8613800001111，预计金额 25000 美元，来源 WhatsApp',
        ]);
        $db = Database::connection();
        $row = $db->query('SELECT * FROM ai_actions ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        assertTrue(is_array($row), 'the request was audited');
        assertEquals('pending', $row['status'], 'the plan waits for confirmation');
        $planned = (int) $db->query("SELECT COUNT(*) FROM leads WHERE contact_email = 'lin@liansheng.com'")->fetchColumn();
        assertEquals(0, $planned, 'previewing wrote no lead');

        $plan = $http->get($base . '/ai?plan=' . $row['id'])['body'];
        assertContains('新建线索', $plan, 'the plan card names the action');
        assertContains('lin@liansheng.com', $plan, 'and shows the arguments it will use');
        assertContains('确认执行', $plan, 'behind a human confirmation button');

        // Confirm once -> exactly one lead.
        $http->post($base . '/ai/apply', ['csrf_token' => $csrf, 'id' => $row['id']]);
        $leads = $db->query("SELECT * FROM leads WHERE contact_email = 'lin@liansheng.com'")->fetchAll(PDO::FETCH_ASSOC);
        assertEquals(1, count($leads), 'the lead exists once, after confirmation');
        assertEquals('联盛机械', $leads[0]['company'], 'fields landed as planned');
        assertEquals(25000.0, (float) $leads[0]['value'], 'money parsed');
        assertEquals(1, (int) $leads[0]['owner_id'], 'owned by the person who asked');

        // Replaying the confirmation must not duplicate the write.
        $http->post($base . '/ai/apply', ['csrf_token' => $csrf, 'id' => $row['id']]);
        assertEquals(1, (int) $db->query("SELECT COUNT(*) FROM leads WHERE contact_email = 'lin@liansheng.com'")->fetchColumn(),
            'an executed plan cannot be applied twice');
        $done = $db->query('SELECT status FROM ai_actions WHERE id = ' . (int) $row['id'])->fetch(PDO::FETCH_ASSOC);
        assertEquals('executed', $done['status'], 'the audit row shows it ran');
        assertContains('已执行', $http->get($base . '/ai/history')['body'], 'and is visible in 操作记录');

        // Leave the shipped default in place for any later case in this process.
        (new Setting())->setMany(['ai_enabled' => '0', 'ai_provider' => 'mock', 'ai_mode' => 'preview'], 1);
    });
}

/**
 * 稳定编号要看得见：人得能拿页面上的编号去核对/指给 AI 看。
 * 顺带验一下历史行没回填时也不留白（Model::codeOf 推导）。
 */
function test_stable_codes_are_visible_on_the_pages(): void
{
    withTestServer('codes', function (TestHttp $http, string $base, string $csrf): void {
        $db = Database::connection();
        // 本用例文件会 resetData()清掉种子行，所以自己建记录（编号由 create() 自动生成）
        $custId = (int) (new Customer())->create(['name' => '编号可见客户', 'company' => '可见公司',
            'status' => 'active', 'owner_id' => 1]);
        $leadId = (int) (new Lead())->create(['title' => '编号可见线索', 'status' => 'new', 'owner_id' => 1]);
        $dealId = (int) (new Deal())->create(['title' => '编号可见商机', 'customer_id' => $custId, 'owner_id' => 1]);
        $custCode = 'CUS-' . sprintf('%06d', $custId);
        $leadCode = 'LEAD-' . sprintf('%06d', $leadId);
        $dealCode = 'DEAL-' . sprintf('%06d', $dealId);
        assertTrue($custId > 0 && $leadId > 0 && $dealId > 0, '三条记录建好了');

        assertContains($custCode, $http->get($base . '/customers')['body'], '客户列表带编号');
        assertContains($custCode, $http->get($base . '/customers/' . $custId)['body'], '客户详情带编号');
        assertContains($leadCode, $http->get($base . '/leads')['body'], '线索列表带编号');
        assertContains($dealCode, $http->get($base . '/deals')['body'], '商机看板带编号');

        // 把编号抹空（模拟还没跑 007 的老库），界面仍不该出现空白或 '--'
        $db->query('UPDATE leads SET public_code = NULL WHERE id = ' . $leadId)->execute();
        assertContains($leadCode, $http->get($base . '/leads')['body'],
            '空编号由 Model::codeOf() 按同一规则推导，不会留白');
        // 而且 AI 拿空编号的行也能用数字 ID 正常操作
        // （注意：HTTP 登录发生在子进程里，本进程的归属检查要看自己的 session）
        $prevUser = $_SESSION['user_id'] ?? null;
        $_SESSION['user_id'] = 1;
        $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
        $plan = Ai::validatePlan([['tool' => 'update_lead', 'args' => ['lead_id' => (string) $leadId, 'notes' => '空编号行']]], 1);
        assertEquals(false, $plan['blocked'], implode('；', $plan['errors']));
        if ($prevUser === null) {
            unset($_SESSION['user_id'], $_SESSION['user']);
        }
    });
}

/**
 * 服务端 CSRF 闸门必须覆盖四个 destroy 端点与 logout：列表/退出表单本来就带
 * token，缺的是服务器校验。补上之后，没带 / 伪造 token 的跨站删除要被 419 拦下，
 * 而带真 token 的正常删除（真实表单）照常执行。
 */
function test_csrf_guard_covers_destroy_endpoints_and_logout(): void
{
    $custId = (int) (new Customer())->create(['name' => 'CSRF 客户', 'status' => 'active', 'owner_id' => 1]);
    $leadId = (int) (new Lead())->create(['title' => 'CSRF 线索', 'status' => 'new', 'owner_id' => 1]);
    $cust2  = (int) (new Customer())->create(['name' => 'CSRF 客户二', 'status' => 'active', 'owner_id' => 1]);
    $dealId = (int) (new Deal())->create([
        'title' => 'CSRF 商机', 'customer_id' => $cust2, 'stage' => 'open', 'owner_id' => 1,
    ]);
    $orderId = (int) (new Order())->create([
        'order_number' => 'ORD-CSRF-0001', 'customer_id' => $cust2,
        'title' => 'CSRF 订单', 'status' => 'pending', 'owner_id' => 1,
    ]);

    withTestServer('csrf', function (TestHttp $http, string $base, string $csrf) use ($custId, $leadId, $dealId, $orderId): void {
        // 没带 token / 伪造 token：四个 destroy 端点都必须被 419 拦下，而不是默默删除
        foreach (['/customers/' . $custId, '/leads/' . $leadId, '/deals/' . $dealId, '/orders/' . $orderId] as $url) {
            $res = $http->post($base . $url, ['_method' => 'DELETE']);
            assertEquals(419, $res['code'], "DELETE {$url} 不带 token → 419");
            assertContains('CSRF', $res['body'], '419 页面说明是 CSRF 校验失败');
            $res = $http->post($base . $url, ['_method' => 'DELETE', 'csrf_token' => 'forged-token']);
            assertEquals(419, $res['code'], "DELETE {$url} 带伪造 token → 419");
        }

        // 带真 token：与页面表单一致的请求必须照常删掉
        $http->post($base . '/customers/' . $custId, ['_method' => 'DELETE', 'csrf_token' => $csrf]);
        assertEquals(false, (bool) (new Customer())->find($custId), '客户被带真 token 的请求删除');
        $http->post($base . '/leads/' . $leadId, ['_method' => 'DELETE', 'csrf_token' => $csrf]);
        assertEquals(false, (bool) (new Lead())->find($leadId), '线索被带真 token 的请求删除');
        $http->post($base . '/deals/' . $dealId, ['_method' => 'DELETE', 'csrf_token' => $csrf]);
        assertEquals(false, (bool) (new Deal())->find($dealId), '商机被带真 token 的请求删除');
        $http->post($base . '/orders/' . $orderId, ['_method' => 'DELETE', 'csrf_token' => $csrf]);
        assertEquals(false, (bool) (new Order())->find($orderId), '订单被带真 token 的请求删除');

        // logout 同款闸门：伪造/缺失 token 拦下，真 token 登出后受保护页弹回登录页
        $res = $http->post($base . '/logout', ['csrf_token' => 'forged-token']);
        assertEquals(419, $res['code'], 'logout 带伪造 token → 419');
        $res = $http->post($base . '/logout', ['csrf_token' => '']);
        assertEquals(419, $res['code'], 'logout 不带 token → 419');
        $http->post($base . '/logout', ['csrf_token' => $csrf]);
        $visit = $http->get($base . '/customers');
        assertTrue(str_contains($visit['url'], '/login'), 'logout 成功后受保护页弹回登录页');
    });
}

/**
 * 两个“保存时防数据丢失”的硬化流：
 *  1) deals store 校验失败 → 表单回显用户刚填的明细行（回归：Undefined $itemsForForm + 吞行）
 *  2) orders store 撞号的建议编号 → 自动换新号落库，而不是 500（回归：UNIQUE 冲突炸页面）
 */
function test_save_time_data_loss_regressions(): void
{
    $cust = (int) (new Customer())->create(['name' => '硬化流客户', 'status' => 'active', 'owner_id' => 1]);
    withTestServer('harden', function (TestHttp $http, string $base, string $csrf) use ($cust): void {
        // 1) 商机：标题留空触发校验错误，用户刚填的明细行必须原样回到表单
        $res = $http->post($base . '/deals', [
            'csrf_token' => $csrf,
            'title' => '',
            'customer_id' => (string) $cust,
            'value' => '0',
            'stage' => 'open',
            'close_date' => '',
            'items' => [
                ['product_id' => '', 'product_name' => '回显行·轴承6206', 'quantity' => '2', 'unit_price' => '9.9', 'unit' => '件'],
            ],
        ]);
        assertEquals(200, $res['code'], 'deals 校验失败回到 200 的表单页');
        assertContains('商机名称不能为空', $res['body'], '错误信息可见');
        assertContains('回显行·轴承6206', $res['body'], '刚填的明细行原样回显');
        assertTrue(!str_contains($res['body'], 'Undefined variable'), '不再有未定义变量告警漏出');

        // 2) 订单：同秒开单或手改编号撞同一个号 → 换新号存，绝不 500
        $dup = 'ORD-DUP-7777';
        $first = $http->post($base . '/orders', [
            'csrf_token' => $csrf,
            'order_number' => $dup, 'title' => '撞号单 A', 'customer_id' => (string) $cust,
            'amount' => '0', 'status' => 'pending', 'payment_status' => 'unpaid',
            'order_date' => date('Y-m-d'),
        ]);
        assertEquals(200, $first['code'], '第一单保存成功');
        $second = $http->post($base . '/orders', [
            'csrf_token' => $csrf,
            'order_number' => $dup, 'title' => '撞号单 B', 'customer_id' => (string) $cust,
            'amount' => '0', 'status' => 'pending', 'payment_status' => 'unpaid',
            'order_date' => date('Y-m-d'),
        ]);
        assertEquals(200, $second['code'], '第二单不 500（编号自动换新）');

        $orders = (new Order())->all('id ASC');
        assertEquals(2, count($orders), '两单都在库里');
        assertTrue($orders[0]['order_number'] !== $orders[1]['order_number'], '编号未被重复占用');
        assertContains('ORD-', $orders[1]['order_number'], '新号保持 ORD- 前缀格式');

        // 3) 商机不能“直接以成交/丢单创建”：成交必须走 update() 那条自动生成订单的链路
        $res3 = $http->post($base . '/deals', [
            'csrf_token' => $csrf, 'title' => '直接成交', 'customer_id' => (string) $cust,
            'value' => '100', 'stage' => 'closed_won', 'close_date' => '', 'items' => [],
        ]);
        assertEquals(200, $res3['code'], 'closed_won 直接创建被拦回表单页');
        assertContains('新建商机不能直接选', $res3['body'], '页面说明正确做法');
        assertEquals(0, (int) (new Deal())->count(), '没有创建出任何商机');
    });
}

runCase();
