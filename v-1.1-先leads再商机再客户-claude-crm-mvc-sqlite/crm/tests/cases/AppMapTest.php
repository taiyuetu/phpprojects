<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

/**
 * 使用说明 / AI 上下文的技术参考区是 AppMap 实时生成的。这里守住两件事：
 *   1) 它确实反映代码与数据库的现状（路由条数、表与列、枚举、设置项、AI 工具）；
 *   2) 它不会把密钥之类的东西写进文档。
 * 文档一旦和实现脱节，AI 就会照着错的地图操作 —— 所以这些断言等同于功能测试。
 */
require __DIR__ . '/../bootstrap.php';

function map_(): array
{
    AppMap::flushCache();
    return AppMap::all();
}

function test_appmap_routes_match_the_real_router(): void
{
    $router = new Router();
    require APP_PATH . '/routes.php';
    $real = 0;
    $flat = [];
    foreach ($router->all() as $method => $map) {
        foreach ($map as $path => $handler) {
            $real++;
            $flat[] = $method . ' ' . $path;
        }
    }

    $map = AppMap::routes();
    assertEquals($real, count($map), 'AppMap lists exactly the registered routes');
    $paths = implode(' ', array_column($map, 'path'));
    foreach (['/customers/{id}', '/leads/{id}/convert', '/settings/app/reset', '/ai/apply', '/help/context'] as $needle) {
        assertContains($needle, $paths, "the documented routes include {$needle}");
    }
    // 每个控制器都必须出现在文档里，否则新人/AI 找不到入口
    foreach (glob(APP_PATH . '/controllers/*Controller.php') ?: [] as $file) {
        assertContains(basename($file, '.php') . '@', implode(' ', array_column($map, 'handler')),
            basename($file) . ' is documented');
    }
}

function test_appmap_schema_matches_the_database(): void
{
    $db = Database::connection();
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
        ->fetchAll(PDO::FETCH_COLUMN);
    $map = map_()['schema'];

    assertEquals(array_values($tables), array_keys($map), 'every table is documented, nothing invented');
    foreach (['users', 'leads', 'deals', 'customers', 'orders', 'order_items', 'follow_ups',
              'activities', 'attachments', 'app_settings', 'ai_actions'] as $t) {
        assertTrue(isset($map[$t]), "table {$t} present in the map");
        assertTrue(count($map[$t]['columns']) > 0, "{$t} has columns");
    }
    // 列清单直接来自 pragma_table_info
    $users = array_column($map['users']['columns'], 'name');
    foreach (['name', 'email', 'password', 'role', 'phone', 'whatsapp', 'job_title', 'notes', 'updated_at'] as $col) {
        assertTrue(in_array($col, $users, true), "users.{$col} documented");
    }
    // 外键与“只存 ID”这条核心规则必须被文档讲出来
    $customersFk = implode(' ', $map['customers']['foreign']);
    assertContains('owner_id → users.id', $customersFk, 'customer ownership is an FK to users');
    assertContains('customer_id → customers.id', implode(' ', $map['deals']['foreign']), 'deals hang off a customer');
}

function test_appmap_harvests_the_enum_values(): void
{
    $enums = AppMap::enums();
    assertEquals('new|contacted|qualified|lost', $enums['leads.status'] ?? '', 'lead statuses come from the CHECK constraint');
    assertEquals('open|proposal|negotiation|closed_won|closed_lost', $enums['deals.stage'] ?? '', 'deal stages');
    assertEquals('unpaid|partial|paid', $enums['orders.payment_status'] ?? '', 'payment statuses');
    assertEquals('admin|sales', $enums['users.role'] ?? '', 'roles');
    assertEquals('deal|order|customer', $enums['attachments.related_type'] ?? '', 'attachment scopes');
    assertTrue(isset($enums['ai_actions.status']), 'the AI audit table states its own lifecycle');

    // 与代码里的枚举保持一致，否则文档会教出非法值
    assertEquals(array_keys(Lead::lostReasonOptions()), ['no_need', 'competitor', 'budget', 'no_match',
        'no_response', 'project_cancel', 'contact_lost', 'other'], 'lost reasons as coded');
}

function test_appmap_documents_settings_without_leaking_the_secret(): void
{
    (new Setting())->setMany(['ai_api_key' => 'sk-secret-VALUE-9999', 'ai_provider' => 'deepseek',
                             'ai_model' => 'deepseek-v4-pro'], 1);
    Setting::flushCache();

    $rows = map_()['settings'];
    $keys = array_column($rows, 'key');
    foreach (['app_name', 'currency_symbol', 'copyright_notice', 'ai_enabled', 'ai_provider',
              'ai_model', 'ai_base_url', 'ai_api_key', 'ai_mode', 'ai_temperature'] as $key) {
        assertTrue(in_array($key, $keys, true), "setting {$key} is documented");
    }
    $text = implode(' ', array_map(static fn($r) => $r['key'] . $r['current'] . $r['label'], $rows));
    assertTrue(strpos($text, 'sk-secret-VALUE-9999') === false, 'the stored API key is never printed');
    $secret = array_values(array_filter($rows, static fn($r) => $r['key'] === 'ai_api_key'))[0];
    assertTrue($secret['secret'], 'it is flagged as a secret');
    assertContains('已设置', $secret['current'], 'and reported as set/masked instead');

    (new Setting())->setMany(['ai_api_key' => '', 'ai_provider' => 'mock', 'ai_model' => ''], 1);
}

function test_appmap_documents_the_ai_surface(): void
{
    $tools = map_()['ai_tools'];
    assertEquals(array_keys(Ai::tools()), array_column($tools, 'name'), 'the whitelist in the docs == the whitelist in code');
    $names = array_column($tools, 'name');
    assertTrue(in_array('delete_customer', $names, true), '删除类工具现在被明确公开（附确认规则）');
    assertTrue(in_array('search_records', $names, true), '查询类工具也在文档里');
    $kinds = array_column($tools, 'kind', 'name');
    assertEquals('delete', $kinds['delete_customer']);
    assertEquals('read', $kinds['search_records']);
    assertEquals('write', $kinds['update_order']);
    $deleted = array_values(array_filter($tools, static fn($t) => $t['destructive']));
    assertEquals(5, count($deleted), '五个删除类工具，一个不多');
    foreach ($tools as $t) {
        assertTrue(in_array($t['kind'], ['read', 'write', 'delete'], true), $t['name'] . ' 必须标明类型');
        assertEquals($t['kind'] === 'delete', $t['destructive'], $t['name'] . ' 的破坏性标记与类型一致');
        assertEquals($t['kind'] !== 'read', $t['write'], $t['name'] . ' 的 write 标记由类型推导');
    }
    foreach ($deleted as $t) {
        $keys = array_column($t['params'], 'name');
        assertTrue(in_array('confirm', $keys, true), $t['name'] . ' 的参数里就有 confirm');
        assertTrue(in_array('reason', $keys, true), $t['name'] . ' 的参数里就有 reason');
    }
    assertTrue(!in_array('update_settings', $names, true), 'AI cannot change app settings');

    foreach (map_()['ai_models'] as $p) {
        assertTrue($p['default'] !== '' || $p['key'] === 'custom', "{$p['key']} has a default model");
        if (in_array($p['key'], ['openai', 'deepseek', 'dashscope', 'moonshot', 'zhipu', 'siliconflow', 'ollama'], true)) {
            assertTrue(preg_match('~^https?://~', $p['base']) === 1, "{$p['key']} declares an endpoint");
        }
    }
    $ids = array_column(map_()['ai_models'], 'key');
    assertContains('deepseek', implode(',', $ids));
    assertContains('dashscope', implode(',', $ids));
}

function test_the_text_map_is_usable_context_and_stays_clean(): void
{
    $text = AppMap::toText();
    assertTrue(strlen($text) > 4000, 'the map is substantial enough to orient a reader');
    foreach (['### ', '## 数据表', '## 路由', '## 设置项', '## AI 工具白名单', '## 约定与已知坑'] as $h) {
        assertContains($h, $text, "the text map has the {$h} section");
    }
    assertContains('owner_id', $text, 'ownership is explained');
    assertContains('csrf', strtolower($text), 'CSRF handling is documented');

    // 注入给模型的那份要小、要干净
    $prompt = AppMap::forPrompt();
    assertTrue(textLength($prompt) <= AppMap::COMPACT_LIMIT, 'the injected map is bounded');
    assertContains('leads.status', $prompt, 'enums reach the model');
    assertTrue(strpos($prompt, 'api_key') === false, 'prompt map never mentions the API key row');
    assertTrue(strpos($prompt, 'sk-') === false, 'and never a key value');
}

function test_the_documented_flows_match_the_code_paths_they_name(): void
{
    $flows = map_()['flows'];
    assertTrue(count($flows) >= 7, 'flows cover the whole app');
    $all = json_encode($flows, JSON_UNESCAPED_UNICODE);

    // 文档点名的方法必须真的存在，否则就是假文档
    foreach (['LeadController::convert', 'Lead::reactivate', 'DealController::update', 'OrderController::createFromDeal',
              'Order::generateOrderNumber', 'canManageResource', 'Ai::validatePlan', 'Setting::keysInGroup'] as $symbol) {
        assertContains($symbol, $all, "the docs reference {$symbol}");
    }
    [, $class, $method] = array_pad([], 3, null);
    foreach (['LeadController', 'DealController', 'OrderController', 'SettingController', 'AiController'] as $c) {
        $file = APP_PATH . '/controllers/' . $c . '.php';
        assertTrue(is_file($file), "{$c} exists as claimed by the docs");
    }
    foreach (['convert', 'markLost', 'reactivate'] as $m) {
        assertTrue(method_exists('LeadController', $m), "LeadController::{$m}() exists");
    }
    assertTrue(method_exists('OrderController', 'createFromDeal'), 'OrderController::createFromDeal() exists');
    assertTrue(method_exists('Order', 'generateOrderNumber'), 'Order::generateOrderNumber() exists');
    assertTrue(method_exists('Setting', 'keysInGroup'), 'Setting::keysInGroup() exists');
    // 诚实条款：权限现状必须写出来，不能只写理想
    assertContains('没有调用 canManageResource', str_replace('\n', ' ', $all) . json_encode($flows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'the docs admit ownership is not enforced in the business controllers yet');
}

function test_documented_conventions_match_the_environment(): void
{
    $php = map_()['php'];
    assertEquals(PHP_VERSION, $php['version'], 'php version is live');
    assertEquals(extension_loaded('openssl'), $php['extensions']['openssl'], 'openssl flag is live');
    assertEquals(extension_loaded('mbstring'), $php['extensions']['mbstring'], 'mbstring flag is live');
    assertEquals(AiClient::httpsAvailable(), $php['https'], 'https availability is live, not asserted by hand');

    $conv = json_encode(map_()['conventions'], JSON_UNESCAPED_UNICODE);
    assertContains('mb_strimwidth', $conv, 'the mbstring trap is documented');
    assertContains('datetime', $conv, 'the UTC vs Asia/Shanghai mix is documented');
    assertContains('migrate.php', $conv, 'the migration entry point is documented');
    assertContains('composer', strtolower($conv), 'the zero-dependency rule is documented');
}

runCase();
