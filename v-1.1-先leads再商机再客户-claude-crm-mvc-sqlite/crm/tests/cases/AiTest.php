<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
/**
 * AI 助手 — 计划生成、校验、执行与审计。
 *
 * Everything here runs offline: either the built-in 演示模型 (deterministic, no
 * network) or AiClient::$transport, a fake HTTP layer. A test suite that phoned
 * a real provider would be slow, costly and non-reproducible.
 *
 * The behaviour under test is the safety model, not the language model:
 *   - unknown tools, unknown parameters and bad values are refused
 *   - a plan may not touch records its owner cannot manage (canManageResource)
 *   - nothing reaches the database until the plan is executed
 *   - the API key never comes back out of a page or an error message
 */
require __DIR__ . '/../bootstrap.php';

function aiUser(string $email, string $name, string $role = 'sales'): int
{
    return (int) (new User())->register($name, $email, $name, $role);
}

/** Point the settings at a fake OpenAI-compatible endpoint. */
function aiUseFakeTransport(array $overrides = []): void
{
    $base = array_merge([
        'ai_enabled' => '1',
        'ai_provider' => 'openai',
        'ai_model' => 'gpt-4o-mini',
        'ai_api_key' => 'sk-test-1234567890abcdef',
        'ai_mode' => 'preview',
        'ai_base_url' => '',        // always reset: these tests switch endpoints
    ], $overrides);
    (new Setting())->setMany($base, 1);

    // Default transport: answer with an empty plan so nothing hits the network.
    if (AiClient::$transport === null) {
        AiClient::$transport = static fn() => ['ok' => true,
            'json' => ['choices' => [['message' => ['content' => '{"reply":"ok","actions":[]}']]]],
            'error' => '', 'status' => 200, 'raw' => ''];
    }
}

/**
 * Arm a transport that records the next request body into aiSeen(), answering with
 * an empty plan. Call it before chat(), read aiSeen() after — a returned array
 * would be a copy taken too early.
 */
function aiCapturePayload(): void
{
    $GLOBALS['ai_seen'] = [];
    AiClient::$transport = static function (string $url, ?array $payload, string $key, float $timeout) {
        $GLOBALS['ai_seen'] = (array) $payload;
        return ['ok' => true, 'json' => ['choices' => [['message' => ['content' => '{"reply":"ok","actions":[]}']]]],
                'error' => '', 'status' => 200, 'raw' => ''];
    };
}

/** The payload recorded by aiCapturePayload(). */
function aiSeen(): array
{
    return (array) ($GLOBALS['ai_seen'] ?? []);
}

/** Put the AI settings back to the state the other cases expect. */
function aiResetSettings(): void
{
    AiClient::$transport = null;
    (new Setting())->setMany(['ai_enabled' => '1', 'ai_provider' => 'mock', 'ai_mode' => 'preview',
        'ai_model' => '', 'ai_base_url' => '', 'ai_api_key' => '',
        'ai_fast_mode' => '1', 'ai_timeout' => '45', 'ai_max_tokens' => '800'], 1);
    Setting::flushCache();
}
/** Answer with a fixed assistant message. */
function aiJsonTransport(string $content): void
{
    AiClient::$transport = static function (string $url, ?array $payload, string $key, float $timeout) use ($content) {
        return ['ok' => true, 'json' => ['choices' => [['message' => ['content' => $content]]], 'model' => 'fake-1'],
                'error' => '', 'status' => 200, 'raw' => ''];
    };
}

// ------------------------------------------------------------------- defaults

function test_ai_is_off_by_default_and_refuses_to_run(): void
{
    (new Setting())->setMany(['ai_enabled' => '0', 'ai_provider' => 'mock'], 1);
    $cfg = AiClient::config();
    assertTrue($cfg['enabled'] === false, 'AI ships disabled');
    assertEquals('mock', $cfg['provider'], 'the default provider is the offline demo model');

    $res = Ai::complete('随便做点什么');
    assertTrue($res['ok'] === false, 'a disabled assistant refuses');
    assertContains('未启用', (string) $res['error']);
}

// ------------------------------------------------------------- plan lifecycle

function test_demo_model_plans_a_lead_from_raw_text(): void
{
    (new Setting())->setMany(['ai_enabled' => '1', 'ai_provider' => 'mock', 'ai_mode' => 'preview'], 1);

    $instruction = "客户 Robert Fox（robert@globex.com，+1-5550102）今天来信，"
        . "公司 Globex，想采购 200 套轴承，预计 30000 美元，来源 WhatsApp。";
    $plan = Ai::complete($instruction);
    assertTrue($plan['ok'], 'demo model produced a plan: ' . ($plan['error'] ?? ''));
    assertTrue(count($plan['actions']) >= 1, 'at least one action');

    $first = $plan['actions'][0];
    assertEquals('create_lead', $first['tool'], 'the expected tool');
    $checked = Ai::validatePlan($plan['actions'], 1);
    assertTrue($checked['blocked'] === false, 'plan validates cleanly: ' . implode('；', $checked['errors']));

    // The whole point of preview mode: a plan is not data yet.
    $before = (new Lead())->count();
    $run = Ai::execute($checked['actions'], 1);
    assertEquals(1, $run['applied'], 'one action applied');
    assertEquals($before + 1, (new Lead())->count(), 'the lead row appeared only after executing');

    $lead = (new Lead())->find((int) $run['results'][0]['id']);
    assertEquals('Robert Fox', $lead['contact_name'], 'contact extracted');
    assertEquals('Globex', $lead['company'], 'company extracted');
    assertEquals('robert@globex.com', $lead['contact_email'], 'email extracted');
    assertEquals(30000.0, (float) $lead['value'], 'money extracted');
    assertEquals('WhatsApp', $lead['source'], 'source recognised');
    assertEquals(1, (int) $lead['owner_id'], 'the AI assigns ownership to the requesting user');
}

function test_preview_mode_writes_nothing_until_executed(): void
{
    (new Setting())->setMany(['ai_enabled' => '1', 'ai_provider' => 'mock', 'ai_mode' => 'preview'], 1);
    $before = (new Lead())->count();
    $plan = Ai::complete('新建线索：张网，邮箱 zhangwang@example.com，来源 邮件');
    $checked = Ai::validatePlan($plan['actions'], 1);
    assertTrue($checked['blocked'] === false, 'valid plan');
    assertEquals($before, (new Lead())->count(), 'planning alone never writes');
    assertTrue(count($checked['actions']) > 0, 'there was something to confirm');
}

// ------------------------------------------------------------------ validation

function test_the_model_cannot_invent_tools_parameters_or_values(): void
{
    $checked = Ai::validatePlan([
        // 1) tool that does not exist (the classic prompt-injection wish)
        ['tool' => 'delete_all_customers', 'args' => []],
        // 2) parameter outside the whitelist
        ['tool' => 'create_lead', 'args' => ['title' => 'ok', 'owner_id' => 99, 'is_admin' => true]],
        // 3) bad values: status/enum, email, money, date
        ['tool' => 'update_lead_status', 'args' => ['lead_id' => 1, 'status' => 'archived_forever']],
        ['tool' => 'create_lead', 'args' => ['title' => 'x', 'contact_email' => 'not-an-email']],
        ['tool' => 'create_lead', 'args' => ['title' => 'x', 'value' => 9e17]],
        ['tool' => 'add_follow_up', 'args' => ['customer_id' => 1, 'title' => 'x', 'next_date' => '明年再说吧']],
        // 4) missing required argument
        ['tool' => 'create_lead', 'args' => ['contact_name' => '没有标题']],
        // 5) a legitimate action mixed in
        ['tool' => 'create_lead', 'args' => ['title' => '合法线索']],
    ], 1);

    $byIndex = array_column($checked['actions'], null, 'index');
    assertTrue($checked['blocked'], 'a plan with any bad step is blocked');
    assertTrue(isset($byIndex[0]['errors'][0]), 'unknown tool refused');
    assertContains('不存在的工具', implode(' ', $byIndex[0]['errors']));
    assertContains('不接受参数', implode(' ', $byIndex[1]['errors']), 'unknown parameter refused');
    assertContains('不在可选值', implode(' ', $byIndex[2]['errors']), 'enum whitelist enforced');
    assertContains('不是合法邮箱', implode(' ', $byIndex[3]['errors']));
    assertContains('超出合理范围', implode(' ', $byIndex[4]['errors']), 'absurd money refused');
    assertContains('无法识别为日期', implode(' ', $byIndex[5]['errors']));
    assertContains('必填', implode(' ', $byIndex[6]['errors']));
    assertEquals([], $byIndex[7]['errors'], 'the well-formed action is still fine');

    $run = Ai::execute(array_values($byIndex), 1);
    assertEquals(1, $run['applied'], 'only the valid step executed');
    assertEquals(7, $run['refused'], 'the rest were refused, not silently fixed');
    foreach ($run['results'] as $r) {
        assertTrue(!empty($r['ok']) || !empty($r['skipped']), 'refusals are reported, never thrown');
    }
}

function test_a_sales_account_cannot_touch_another_owners_records(): void
{
    $boss  = aiUser('boss@example.com', '老板', 'admin');
    $rep   = aiUser('rep@example.com', '小销售');
    $other = aiUser('other@example.com', '隔壁');

    $mine    = (int) (new Customer())->create(['name' => '我负责的', 'status' => 'active', 'owner_id' => $rep]);
    $theirs  = (int) (new Customer())->create(['name' => '别人负责的', 'status' => 'active', 'owner_id' => $other]);
    $public  = (int) (new Customer())->create(['name' => '公海客户', 'status' => 'active', 'owner_id' => null]);

    $_SESSION['user_id'] = $rep;
    $_SESSION['user'] = ['id' => $rep, 'role' => 'sales'];
    User::flushIdentityCache();

    $checked = Ai::validatePlan([
        ['tool' => 'add_follow_up', 'args' => ['customer_id' => $mine, 'title' => '跟一下']],
        ['tool' => 'add_follow_up', 'args' => ['customer_id' => $theirs, 'title' => '偷着跟']],
        ['tool' => 'add_follow_up', 'args' => ['customer_id' => $public, 'title' => '认领公海']],
        ['tool' => 'update_customer', 'args' => ['customer_id' => 999999, 'phone' => '555']],
        // an AI request to hand data to itself as admin is just another parameter
        ['tool' => 'update_customer', 'args' => ['customer_id' => $theirs, 'owner_id' => $rep]],
    ], $rep);

    $by = array_column($checked['actions'], null, 'index');
    assertEquals([], $by[0]['errors'], "the rep's own customer is fine");
    assertContains('其他同事负责', implode(' ', $by[1]['errors']), "someone else's customer is refused");
    assertEquals([], $by[2]['errors'], 'unassigned (公海) records stay reachable');
    assertContains('找不到对应记录', implode(' ', $by[3]['errors']), 'a hallucinated id is refused');
    assertTrue($by[4]['errors'] !== [], 'unknown parameter / ownership both refused');

    $run = Ai::execute(array_values($by), $rep);
    assertEquals(2, $run['applied'], 'own + public executed');
    assertEquals(3, $run['refused'], 'the three bad steps did not run');
    unset($_SESSION['user_id'], $_SESSION['user']);
}

function test_a_validated_plan_targets_records_that_exist(): void
{
    $_SESSION['user_id'] = 1;
    $cust = (int) (new Customer())->create(['name' => '快照客户', 'status' => 'active', 'owner_id' => 1]);
    $digest = Ai::contextDigest(1);
    assertContains('快照客户', $digest, 'the prompt carries real records so ids are not guessed');
    assertContains((string) $cust, $digest, 'including the id');
    // a sales user must not see someone else's customer in the snapshot
    $stranger = aiUser('stranger@example.com', '陌生人');
    (new Customer())->create(['name' => '别人家的客户', 'status' => 'active', 'owner_id' => 1]);
    $limited = Ai::contextDigest($stranger);
    assertTrue(!str_contains($limited, '别人家的客户'), 'the snapshot is scoped to the caller');
}

/**
 * Presets must name models that actually exist at the endpoint — otherwise the
 * first thing an admin does is hit a 400. Also asserts the URL each preset
 * produces, using the injected transport so no network (or openssl) is needed.
 */
function test_provider_presets_name_current_model_ids(): void
{
    $p = AiClient::providers();

    assertEquals(['deepseek-v4-flash', 'deepseek-v4-pro'], $p['deepseek']['models'], 'DeepSeek V4 ids');
    assertEquals('deepseek-v4-flash', $p['deepseek']['default_model'], 'V4 Flash is the economical default');
    assertEquals('https://api.deepseek.com', $p['deepseek']['base'], '官方 base_url 不带 /v1');

    assertTrue(in_array('qwen3.8-max', $p['dashscope']['models'], true), 'Qwen 3.8 Max is offered');
    assertTrue(in_array('qwen3.8-flash', $p['dashscope']['models'], true), 'Qwen 3.8 Flash is offered');
    assertEquals('qwen3.8-flash', $p['dashscope']['default_model'], 'Qwen 3.8 Flash is the default');
    assertEquals('https://dashscope.aliyuncs.com/compatible-mode/v1', $p['dashscope']['base'], '百炼 OpenAI 兼容模式端点');

    // 服务商下拉与这份清单同源，不会各自漂移
    assertEquals(array_keys($p), array_column(Setting::definitionOptions('ai_provider'), 'value'),
        'the select offers exactly the known providers');
    assertTrue(Setting::isSecret('ai_api_key'), 'the API key is declared a secret');

    // 每个预设拼出的请求地址必须就是服务商公布的 chat/completions 路径
    $expected = [
        'deepseek'  => 'https://api.deepseek.com/chat/completions',
        'dashscope' => 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions',
    ];
    foreach ($expected as $provider => $want) {
        $seen = null;
        AiClient::$transport = static function (string $url, ?array $payload, string $key, float $timeout) use (&$seen) {
            $seen = $url;
            return ['ok' => true, 'json' => ['choices' => [['message' => ['content' => '{}']]]],
                    'error' => '', 'status' => 200, 'raw' => ''];
        };
        AiClient::chat([['role' => 'user', 'content' => 'hi']], [
            'enabled' => true, 'provider' => $provider, 'needs_key' => true,
            'base_url' => $p[$provider]['base'], 'model' => $p[$provider]['default_model'],
            'api_key' => 'sk-x', 'temperature' => 0.2, 'timeout' => 5.0,
        ]);
        assertEquals($want, $seen, "{$provider} chat endpoint");
        AiClient::$transport = null;
    }
}

function test_a_missing_https_transport_says_so_instead_of_failing_mysteriously(): void
{
    $diag = AiClient::diagnostics();
    assertTrue(is_bool($diag['https']), 'diagnostics reports https availability');
    assertEquals(PHP_VERSION, $diag['php'], 'the PHP version is reported');
    if ($diag['https']) {
        assertEquals('', $diag['https_hint'], 'nothing to fix when https works');
        return;
    }
    // This machine is the interesting case: openssl off means cloud providers
    // cannot be reached, and the message must name the fix.
    assertContains('openssl', $diag['https_hint']);
    (new Setting())->setMany(['ai_enabled' => '1', 'ai_provider' => 'deepseek',
                             'ai_api_key' => 'sk-x', 'ai_model' => 'deepseek-v4-flash'], 1);
    $res = AiClient::chat([['role' => 'user', 'content' => 'hi']]);
    assertTrue($res['ok'] === false, 'no request was attempted');
    assertContains('openssl', (string) $res['error']);
    (new Setting())->setMany(['ai_enabled' => '0', 'ai_provider' => 'mock', 'ai_api_key' => '', 'ai_model' => ''], 1);
}

/**
 * The capability probe must match the real environment. It once asked
 * stream_get_transports() for the literal "https", which no PHP ever lists
 * (the transport is "ssl"/"tls"), so a healthy build was told it could not do
 * https — the kind of false negative that makes an admin chase php.ini forever.
 */
function test_https_capability_probe_matches_the_real_environment(): void
{
    $transports = stream_get_transports();
    $expected = extension_loaded('openssl')
        && (in_array('ssl', $transports, true) || in_array('tls', $transports, true));
    assertEquals($expected, AiClient::httpsAvailable(), 'the probe mirrors openssl + a TLS transport');

    if (extension_loaded('openssl')) {
        assertTrue(AiClient::httpsAvailable(), 'with openssl loaded, https must be reported as available');
        assertEquals('', AiClient::diagnostics()['https_hint'], 'and nothing needs fixing');
    }
    if (!in_array('https', $transports, true)) {
        assertTrue(true, 'sanity: "https" is a URL scheme, never a transport name');
    }
}

/**
 * A slow model must produce a readable error, never a Fatal error on the page.
 * PHP's own max_execution_time (30 s under php -S / Apache) killed the request
 * mid-call; now the stream has to give up first, with room to spare.
 */
function test_the_timeout_chain_leaves_room_before_php_fatals(): void
{
    // pure maths, no set_time_limit() involved
    assertEquals(20.0, AiClient::effectiveTimeout(45.0, 30), 'php limit 30s -> stream stops at 20s');
    assertEquals(50.0, AiClient::effectiveTimeout(60.0, 60), 'equal limits -> back off by the headroom');
    assertEquals(45.0, AiClient::effectiveTimeout(45.0, 0), 'CLI has no script time limit');
    assertEquals(5.0, AiClient::effectiveTimeout(45.0, 12), 'never below the floor, never above php');

    // the setting drives the budget, and it is clamped to something sane
    (new Setting())->setMany(['ai_timeout' => '90', 'ai_max_tokens' => '400'], 1);
    $cfg = AiClient::config();
    assertEquals(90.0, $cfg['timeout'], '响应超时 comes from 设置');
    assertEquals(400, $cfg['max_tokens'], '最大回复长度 comes from 设置');
    (new Setting())->setMany(['ai_timeout' => '99999', 'ai_max_tokens' => '-5'], 1);
    $clamped = AiClient::config();
    assertEquals(AiClient::MAX_TIMEOUT, $clamped['timeout'], 'absurd timeouts are clamped');
    assertEquals(0, $clamped['max_tokens'], '0 = 不限制, never a negative token count');
    (new Setting())->setMany(['ai_timeout' => '45', 'ai_max_tokens' => '800'], 1);
}

function test_a_timed_out_model_reports_the_fix_not_a_dead_page(): void
{
    // "no response at all" is the timeout / unreachable case: the message must name
    // the setting to turn, and must not be a PHP fatal.
    $msg = AiClient::noResponseError('https://api.deepseek.com/chat/completions', 45.0);
    assertContains('没有收到 AI 响应', $msg);
    assertContains('响应超时', $msg, 'it names the setting to turn');
    assertContains('api.deepseek.com', $msg, 'and which endpoint was silent');
    assertTrue(!str_contains($msg, 'sk-'), 'no credential material in it');

    aiUseFakeTransport();
    AiClient::$transport = static fn() => ['ok' => false, 'json' => [], 'error' => '', 'status' => 0, 'raw' => ''];
    $res = AiClient::chat([['role' => 'user', 'content' => 'hi']]);
    assertTrue($res['ok'] === false, 'a silent provider is reported, not thrown');
    AiClient::$transport = null;
}

function test_requests_are_bounded_so_answers_come_back_fast(): void
{
    aiUseFakeTransport(['ai_max_tokens' => '600']);
    $seen = null;
    AiClient::$transport = static function (string $url, ?array $payload, string $key, float $timeout) use (&$seen) {
        $seen = $payload;
        return ['ok' => true, 'json' => ['choices' => [['message' => ['content' => '{}']]]],
                'error' => '', 'status' => 200, 'raw' => ''];
    };
    AiClient::chat(Ai::messages('新建线索：测试') );
    assertEquals(600, $seen['max_tokens'], 'max_tokens is actually sent');
    assertEquals(false, $seen['stream'], 'non-streaming so the reply arrives whole');
    assertContains('leads.status', $seen['messages'][0]['content'], 'the model gets the real enum values');
    // 17 tools + enums + rules now fit in this budget. The cap is a regression
    // guard, not a goal: it used to be 3000 with 7 tools. The point is that the
    // prompt grows with capability, not without bound.
    // 21 个工具的参数名必须逐个进提示词（那是能力面），但总量仍要受控：提示词长度就是等待时间
    assertTrue(textLength($seen['messages'][0]['content']) < 6800,
        'the system prompt stays bounded (got ' . textLength($seen['messages'][0]['content']) . ')');
    AiClient::$transport = null;
    (new Setting())->setMany(['ai_max_tokens' => '800'], 1);
}

/**
 * 快速模式 is the difference between a 1 s usable plan and a 8 s empty answer,
 * so the parameter, the fallback when a gateway rejects it, and the empty-content
 * rescue all have to stay wired.
 */
function test_fast_mode_sends_the_providers_thinking_switch(): void
{
    aiUseFakeTransport(['ai_provider' => 'deepseek', 'ai_fast_mode' => '1']);
    $cfg = AiClient::config();
    assertTrue($cfg['fast_mode'], '默认开启');
    assertEquals(['thinking' => ['type' => 'disabled']], $cfg['fast_params'], 'DeepSeek 的开关来自服务商预设');
    aiCapturePayload();
    AiClient::chat([['role' => 'user', 'content' => 'hi']], $cfg);
    assertEquals(['type' => 'disabled'], aiSeen()['thinking'], '参数确实随请求发出');

    // another provider, another parameter name; a provider without one sends nothing
    aiUseFakeTransport(['ai_provider' => 'dashscope']);
    assertEquals(['enable_thinking' => false], AiClient::config()['fast_params'], '通义用 enable_thinking');
    aiUseFakeTransport(['ai_provider' => 'openai']);
    assertEquals([], AiClient::config()['fast_params'], 'OpenAI 预设不塞私有参数');
    aiUseFakeTransport(['ai_provider' => 'deepseek', 'ai_fast_mode' => '0']);
    $cfg = AiClient::config();
    assertTrue(!$cfg['fast_mode'], '设置可以关掉');
    aiCapturePayload();
    AiClient::chat([['role' => 'user', 'content' => 'hi']], $cfg);
    assertEquals(false, array_key_exists('thinking', aiSeen()), '关掉后请求里不再有 thinking');
    aiResetSettings();
}

function test_a_gateway_that_rejects_the_parameter_falls_back_instead_of_failing(): void
{
    aiUseFakeTransport(['ai_provider' => 'deepseek', 'ai_fast_mode' => '1']);
    $calls = [];
    AiClient::$transport = static function (string $url, ?array $payload, string $key, float $timeout) use (&$calls) {
        $calls[] = $payload;
        if (count($calls) === 1) {
            return ['ok' => false, 'json' => [], 'error' => "unknown parameter: 'thinking'", 'status' => 400, 'raw' => ''];
        }
        return ['ok' => true, 'json' => ['choices' => [[
            'message' => ['content' => '{"reply":"回退成功","actions":[{"tool":"create_lead","args":{"title":"来自回退请求"}}]}'],
        ]]], 'error' => '', 'status' => 200, 'raw' => ''];
    };
    $res = AiClient::chat([['role' => 'user', 'content' => 'hi']], AiClient::config());
    assertTrue($res['ok'] === true, '第二次请求成功，用户看不到失败');
    assertContains('不接受', (string) ($res['notice'] ?? ''), '但会被告知发生了回退');
    assertEquals(2, count($calls), '只重试了一次');
    assertEquals(false, array_key_exists('thinking', $calls[1]), '重试时不带该参数');
    assertTrue(AiClient::rejectsParam('Rate limit exceeded', ['thinking']) === false, '别的 4xx 不会触发无意义重试');
    aiResetSettings();
}

function test_a_thinking_model_that_only_wrote_reasoning_is_not_reported_as_silent(): void
{
    aiUseFakeTransport(['ai_provider' => 'deepseek']);
    AiClient::$transport = static fn() => ['ok' => true, 'json' => ['choices' => [['message' => [
        'content' => '', 'reasoning_content' => '{\"reply\":\"从推理字段取回\",\"actions\":[]}',
    ]]]], 'error' => '', 'status' => 200, 'raw' => ''];
    $res = AiClient::chat([['role' => 'user', 'content' => 'hi']], AiClient::config());
    assertTrue($res['ok'] === true, 'reasoning_content 会被当作回复');
    assertContains('推理字段', (string) $res['content']);

    // truly empty, with fast mode off: point at the switch that fixes it
    AiClient::$transport = static fn() => ['ok' => true, 'json' => ['choices' => [['message' => ['content' => '']]]],
        'error' => '', 'status' => 200, 'raw' => ''];
    $cfg = AiClient::config();
    $cfg['fast_mode'] = false;
    $empty = AiClient::chat([['role' => 'user', 'content' => 'hi']], $cfg);
    assertTrue($empty['ok'] === false);
    assertContains('快速模式', (string) $empty['error'], '空内容时提示开快速模式');
    aiResetSettings();
}

// ------------------------------------------------------------------- parsing

function test_answers_are_parsed_even_when_the_model_is_chatty(): void
{
    $clean = '{"reply":"建好了","actions":[{"tool":"create_lead","args":{"title":"来自 JSON"},"reason":"测试"}]}';
    $plan = Ai::parsePlan($clean);
    assertTrue($plan['ok'], 'plain json parses');
    assertEquals('create_lead', $plan['actions'][0]['tool']);

    $fenced = Ai::parsePlan("好的：\n```json\n" . $clean . "\n```\n以上。");
    assertTrue($fenced['ok'], '```json fences parse');

    $prose = Ai::parsePlan('当然可以。' . $clean . ' 还需要我做什么吗？');
    assertTrue($prose['ok'], 'json embedded in prose parses');

    $bare = Ai::parsePlan('[{"tool":"create_lead","args":{"title":"裸数组"}}]');
    assertTrue($bare['ok'] && count($bare['actions']) === 1, 'a bare array is tolerated');

    assertTrue(Ai::parsePlan('抱歉，我做不到。')['ok'] === false, 'no json -> refused');
    // "nothing to do, and here is why" is a legitimate answer; silence is not.
    $noop = Ai::parsePlan('{"reply":"这些线索都还在跟进中，不需要改动。","actions":[]}');
    assertTrue($noop['ok'], 'an empty plan with an explanation is accepted');
    assertEquals([], $noop['actions']);
    assertContains('不需要改动', $noop['reply']);
    assertTrue(Ai::parsePlan('{"reply":"","actions":[]}')['ok'] === false, 'an empty answer with no reply is rejected');
    assertTrue(Ai::parsePlan('{"actions":"oops"}')['ok'] === false, 'wrong type refused');
    assertTrue(Ai::parsePlan('{"reply":"未闭合','actions":[{"tool":"x"}')['ok'] === false, 'truncated json refused');
}

function test_the_plan_is_stored_as_data_not_as_a_query(): void
{
    $actions = [['tool' => 'create_lead', 'args' => ['title' => '审计用线索'], 'reason' => '演示']];
    $checked = Ai::validatePlan($actions, 1);
    $id = (new Ai())->record(1, '帮我建条线索', $checked + ['status' => 'pending', 'reply' => '好'],
        ['provider' => 'mock', 'model' => 'triphase-mock', 'latency_ms' => 3]);
    assertTrue($id > 0, 'audit row written');

    $row = (new Ai())->find($id);
    assertEquals('pending', $row['status'], 'starts pending');
    assertEquals(1, (int) $row['user_id'], 'attributed to the requester');
    $plan = Ai::planOf($row);
    assertEquals('create_lead', $plan['actions'][0]['tool'], 'plan round-trips through JSON');

    // only the owner may pick it up for execution
    assertTrue((new Ai())->pendingFor($id, 2) === false, "another user's pendingFor finds nothing");
    $mine = (new Ai())->pendingFor($id, 1);
    assertEquals($id, (int) $mine['id'], 'the owner finds it');

    (new Ai())->finish($id, 'executed', [['tool' => 'create_lead', 'ok' => true, 'message' => '已新建线索 #7']], null);
    $done = (new Ai())->find($id);
    assertEquals('executed', $done['status'], 'status transitions');
    assertTrue($done['executed_at'] !== null && $done['executed_at'] !== '', 'execution timestamped');
    assertEquals('已新建线索 #7', Ai::resultsOf($done)[0]['message'], 'results round-trip');
    assertTrue((new Ai())->pendingFor($id, 1) === false, 'an executed plan can no longer be applied');
}

// ------------------------------------------------------------------ transport

function test_chat_reports_errors_instead_of_throwing_and_never_leaks_the_key(): void
{
    aiUseFakeTransport();
    AiClient::$transport = static fn() => ['ok' => false, 'json' => [], 'status' => 0, 'raw' => '',
        'error' => '连接失败，请求头 Authorization: Bearer sk-test-1234567890abcdef'];

    $res = AiClient::chat([['role' => 'user', 'content' => 'hi']]);
    assertTrue($res['ok'] === false, 'error surfaced');
    assertTrue(strpos($res['error'], 'sk-test-1234567890abcdef') === false, 'the raw key is redacted out of the message');
    assertContains('sk-•', $res['error'], 'replaced with the masked form');
    AiClient::$transport = null;
}

function test_chat_reads_the_openai_shape_and_records_latency(): void
{
    aiUseFakeTransport();
    aiJsonTransport('{"reply":"ok","actions":[]}');
    $res = AiClient::chat([['role' => 'user', 'content' => 'hi']]);
    assertTrue($res['ok'], 'chat succeeded');
    assertEquals('{"reply":"ok","actions":[]}', $res['content']);
    assertEquals('fake-1', $res['model'], 'the model that answered is reported');
    AiClient::$transport = null;
}

function test_requests_go_to_the_configured_openai_compatible_endpoint(): void
{
    aiUseFakeTransport(['ai_base_url' => 'https://gateway.internal.example/v1']);
    $seen = null;
    AiClient::$transport = static function (string $url, ?array $payload, string $key, float $timeout) use (&$seen) {
        $seen = ['url' => $url, 'payload' => $payload, 'key' => $key];
        return ['ok' => true, 'json' => ['choices' => [['message' => ['content' => '{}']]]],
                'error' => '', 'status' => 200, 'raw' => ''];
    };
    AiClient::chat([['role' => 'user', 'content' => 'hi']]);
    assertEquals('https://gateway.internal.example/v1/chat/completions', $seen['url'], 'base url + path');
    assertEquals('gpt-4o-mini', $seen['payload']['model'], 'model forwarded');
    assertEquals('sk-test-1234567890abcdef', $seen['key'], 'key sent as bearer');
    AiClient::$transport = null;
}

function test_endpoints_are_restricted_to_https_except_localhost(): void
{
    $cases = [
        ['http://api.example.com/v1', false, 'https'],
        ['https://api.example.com/v1', true, ''],
        ['http://127.0.0.1:11434/v1', true, ''],
        ['http://localhost:11434/v1', true, ''],
        ['ftp://api.example.com/v1', false, 'http'],
        ['not a url', false, '不完整'],
        ['https://user:pass@api.example.com/v1', false, '用户名/密码'],
    ];
    /** The model answered; the URL shape is asserted in the endpoint tests. */
    foreach ($cases as [$base, $shouldPass, $needle]) {
        aiUseFakeTransport(['ai_base_url' => $base]);
        $res = AiClient::chat([['role' => 'user', 'content' => 'hi']]);
        if ($shouldPass) {
            assertTrue(!isset($res['error']), "{$base} should be allowed (got: " . ($res['error'] ?? '') . ")");
        } else {
            assertTrue($res['ok'] === false, "{$base} must be rejected");
            assertContains($needle, (string) $res['error'], "{$base} rejection explains itself");
        }
    }
    AiClient::$transport = null;
}

function test_environment_overrides_win_over_the_stored_settings(): void
{
    aiUseFakeTransport(['ai_enabled' => '0', 'ai_provider' => 'openai', 'ai_api_key' => 'stored-key']);
    putenv('AI_ENABLED=1');
    putenv('AI_PROVIDER=ollama');
    putenv('AI_MODEL=qwen2.5:14b');
    putenv('AI_API_KEY=env-ke' . 'y-value');
    $cfg = AiClient::config();
    assertTrue($cfg['enabled'], 'env turns it on');
    assertEquals('ollama', $cfg['provider'], 'env picks the provider');
    assertEquals('qwen2.5:14b', $cfg['model'], 'env picks the model');
    assertEquals('env-key-value', $cfg['api_key'], 'env supplies the key');
    assertTrue($cfg['key_from_env'], 'and the UI is told so');
    assertEquals('http://127.0.0.1:11434/v1', $cfg['base_url'], 'provider default base url');

    putenv('AI_ENABLED'); putenv('AI_PROVIDER'); putenv('AI_MODEL'); putenv('AI_API_KEY');
    $back = AiClient::config();
    assertEquals('stored-key', $back['api_key'], 'falls back to the stored setting');
    assertTrue($back['enabled'] === false, 'and back to the stored off switch');
}

function test_the_key_is_masked_and_never_rendered(): void
{
    aiUseFakeTransport(['ai_enabled' => '1']);
    assertEquals('sk-••••••••••••cdef', AiClient::maskKey('sk-test-1234567890abcdef'), 'masked preview');
    assertEquals('', AiClient::maskKey(''), 'nothing stored -> nothing shown');
    assertEquals('•••••', AiClient::maskKey('short'), 'short values fully masked');

    // values() keeps the real key for AiClient; publicValues() is what a form may use
    assertEquals('sk-test-1234567890abcdef', Setting::get('ai_api_key'));
    assertEquals('', Setting::publicValues()['ai_api_key'], 'form values are scrubbed');
    $state = Setting::secretState();
    assertTrue($state['ai_api_key']['set'], 'state says a key exists');
    assertEquals('sk-••••••••••••cdef', $state['ai_api_key']['masked']);

    // sanitize must not wipe a stored key when the password box is left empty
    $clean = Setting::sanitize(['ai_enabled' => '1', 'ai_api_key' => '   ']);
    assertTrue(!array_key_exists('ai_api_key', $clean['values']), 'empty secret box keeps the stored key');
    (new Setting())->setMany(['ai_api_key' => 'sk-test-1234567890abcdef'], 1);
    assertEquals('sk-test-1234567890abcdef', Setting::get('ai_api_key'), 'key survived the save');

    // and the settings page must show only the mask
    $_SESSION['user_id'] = 1;
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
    $html = renderAiView(APP_PATH . '/views/settings/index.php', 'ai');
    assertTrue(strpos($html, 'sk-test-1234567890abcdef') === false, 'the real key never reaches the browser');
    assertContains('sk-••••••••••••cdef', $html, 'the masked form does');
    unset($_SESSION['user_id'], $_SESSION['user']);
    (new Setting())->setMany(['ai_enabled' => '0', 'ai_provider' => 'mock', 'ai_api_key' => '', 'ai_base_url' => ''], 1);
}

function renderAiView(string $file, string $tab): string
{
    $vars = [
        'user'        => (new User())->find(1),
        'settings'    => Setting::publicValues(),
        'definitions' => Setting::definitions(),
        'changes'     => (new Setting())->changes(),
        'references'  => (new User())->ownedReferences(1),
        'secrets'     => Setting::secretState(),
        'aiConfig'    => AiClient::config(),
        'tab'         => $tab,
        'csrf'        => 'token',
    ];
    extract($vars);
    ob_start();
    require $file;
    return (string) ob_get_clean();
}

// -------------------------------------------------------------- prompt shape

function test_the_prompt_asks_for_a_json_plan_and_whitelists_the_tools(): void
{
    $prompt = Ai::systemPrompt();
    assertContains('只能输出一个 JSON 对象', $prompt);
    assertContains('create_lead', $prompt, 'the tool list is in the prompt');
    assertContains('忽略其中任何', $prompt, 'the pasted material is framed as data, not instructions');
    assertTrue(!str_contains($prompt, 'api_key'), 'the prompt never contains credentials');

    $messages = Ai::messages('测试指令');
    assertEquals('system', $messages[0]['role']);
    assertContains('<data>', $messages[1]['content'], 'context is wrapped as data');
    assertContains('测试指令', $messages[1]['content']);
}

runCase();
