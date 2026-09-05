<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
/**
 * 按条件批量操作（多轮工具调用）。
 *
 * 「删除印度国家的所有客户」「删掉名字含 armtek 的客户及其线索/商机/订单」这类指令，
 * 单轮永远做不到：模型看不到搜索结果，就只能编 ID。所以 complete() 变成循环：
 *   第 1 轮模型只要查询 → 系统真的执行（只读，不写库）→ 把真实编号回填进
 *   <tool_results> → 第 2 轮模型针对这些编号出写/删计划 → 仍然要人工确认。
 *
 * 这里全部用内置演示模型跑，因此不需要 API Key、不联网、可重复。
 */
require __DIR__ . '/../bootstrap.php';

function bulkAdmin(): int
{
    $_SESSION['user_id'] = 1;
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
    (new Setting())->setMany(['ai_enabled' => '1', 'ai_provider' => 'mock', 'ai_mode' => 'preview',
        'ai_allow_delete' => '1'], 1);
    Setting::flushCache();
    return 1;
}

/** 一个客户 + 名下线索/商机/订单各一条，国家可指定。 */
function bulkAccount(string $name, string $country): array
{
    $cid = (int) (new Customer())->create(['name' => $name, 'company' => $name, 'status' => 'active',
        'source_country' => $country, 'owner_id' => 1]);
    $lid = (int) (new Lead())->create(['title' => $name . ' 询盘', 'company' => $name, 'status' => 'new',
        'owner_id' => 1, 'customer_id' => $cid]);
    $did = (int) (new Deal())->create(['title' => $name . ' 批量单', 'customer_id' => $cid,
        'value' => 9000, 'stage' => 'open', 'owner_id' => 1]);
    $oid = (int) (new Order())->create(['order_number' => (new Order())->generateOrderNumber(),
        'customer_id' => $cid, 'deal_id' => $did, 'title' => $name . ' 首柜', 'amount' => 9000,
        'status' => 'pending', 'payment_status' => 'unpaid', 'owner_id' => 1]);
    return ['cust' => $cid, 'lead' => $lid, 'deal' => $did, 'order' => $oid];
}

function bulkCounts(): array
{
    $db = Database::connection();
    return [
        'customers' => (int) $db->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
        'leads' => (int) $db->query('SELECT COUNT(*) FROM leads')->fetchColumn(),
        'deals' => (int) $db->query('SELECT COUNT(*) FROM deals')->fetchColumn(),
        'orders' => (int) $db->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    ];
}

function test_search_supports_filters_without_a_keyword(): void
{
    $u = bulkAdmin();
    bulkAccount('锐创轴承', 'India');
    bulkAccount('Ganesh Traders', 'India');
    bulkAccount('Acme Corp', 'United States');

    // 只有条件、没有关键词
    $plan = Ai::validatePlan([['tool' => 'search_records',
        'args' => ['tables' => 'customer', 'country' => 'India', 'limit' => '50']]], $u);
    assertEquals(false, $plan['blocked'], implode('；', $plan['errors']));
    $run = Ai::execute($plan['actions'], $u);
    $rows = $run['results'][0]['rows'] ?? [];
    assertEquals(2, count($rows), '按国家过滤命中两条印度客户');
    foreach ($rows as $row) {
        assertContains('CUS-', (string) $row['code'], '结果带编号');
        assertContains('India', (string) $row['detail'], '详情里能看到国家，便于人核对');
    }
    assertContains('India', (string) $run['results'][0]['message'], '回执说明用了什么条件');

    // status / stage 过滤
    $byStage = Ai::execute(Ai::validatePlan([['tool' => 'search_records',
        'args' => ['tables' => 'deal', 'stage' => 'open']]], $u)['actions'], $u);
    assertEquals(3, count($byStage['results'][0]['rows'] ?? []), '按阶段过滤商机');

    // owner 过滤要走 users JOIN
    $byOwner = Ai::execute(Ai::validatePlan([['tool' => 'search_records',
        'args' => ['tables' => 'customer', 'owner' => 'Admin']]], $u)['actions'], $u);
    assertTrue(count($byOwner['results'][0]['rows'] ?? []) >= 2, '按负责人姓名过滤：' . $byOwner['results'][0]['message']);

    // 什么条件都不给 → 拒，避免变成全表扫描
    $none = Ai::validatePlan([['tool' => 'search_records', 'args' => ['limit' => '10']]], $u);
    assertTrue($none['blocked'], '没有关键词也没有过滤条件时应被拒');
    assertContains('关键词', implode('；', $none['errors']));
}

function test_bulk_delete_by_country_runs_two_rounds_and_writes_nothing_yet(): void
{
    $u = bulkAdmin();
    $in1 = bulkAccount('锐创轴承', 'India');
    $in2 = bulkAccount('Ganesh Traders', 'India');
    $us = bulkAccount('Acme Corp', 'United States');
    $before = bulkCounts();

    $res = Ai::complete('删除印度国家的所有客户信息，连同他们的线索、商机和订单');
    assertTrue($res['ok'], (string) ($res['error'] ?? ''));
    assertEquals(1, count($res['rounds']), '应当正好查了一轮');
    assertEquals('search_records', (string) $res['rounds'][0]['asked'][0]['tool'], '第 1 轮是查询');
    assertEquals('India', (string) $res['rounds'][0]['asked'][0]['args']['country'], '把「印度」翻成了过滤条件');
    assertEquals(2, count($res['actions']), '第 2 轮针对查到的两条出计划');
    foreach ($res['actions'] as $a) {
        assertEquals('delete_customer', $a['tool'], '只发 delete_customer：它本身级联，不该重复发子记录删除');
        assertContains('CUS-', (string) $a['args']['customer_id'], '引用的是编号');
        assertEquals(true, $a['args']['confirm'] === true || $a['args']['confirm'] === 'true');
        assertTrue($a['args']['reason'] !== '', '每条都写了理由');
    }
    assertEquals($before, bulkCounts(), '计划阶段一行都没动');

    // 合计摘要要在人点确认之前就看得见
    $checked = Ai::validatePlan($res['actions'], $u);
    assertEquals(false, $checked['blocked'], implode('；', $checked['errors']));
    $sum = Ai::planSummary($checked['actions']);
    assertEquals(2, $sum['delete']);
    assertEquals(2, $sum['cascade']['线索']);
    assertEquals(2, $sum['cascade']['商机']);
    assertEquals(2, $sum['cascade']['订单']);
    assertEquals(8, $sum['total'], '2 客户 + 6 子记录');
    assertContains('锐创轴承', implode(' ', $sum['targets']), '合计里点名了要删谁');

    $run = Ai::execute($checked['actions'], $u, 5);
    assertEquals(2, $run['applied']);
    $after = bulkCounts();
    assertEquals($before['customers'] - 2, $after['customers']);
    assertEquals($before['leads'] - 2, $after['leads'], '线索随客户一起清掉');
    assertEquals($before['deals'] - 2, $after['deals']);
    assertEquals($before['orders'] - 2, $after['orders']);
    $rest = Database::connection()->query('SELECT name FROM customers')->fetchAll(PDO::FETCH_COLUMN);
    assertEquals(['Acme Corp'], $rest, '只有印度的被删，美国的留着');
}

function test_bulk_delete_by_name_with_english_company(): void
{
    $u = bulkAdmin();
    $a = bulkAccount('armtek Bearings', 'India');
    $b = bulkAccount('Armtek 分公司', 'Vietnam');
    $keep = bulkAccount('Globex Inc', 'United States');
    $before = bulkCounts();

    $res = Ai::complete('删除客户名字为 armtek 的所有客户信息，线索信息和商机和订单信息');
    assertTrue($res['ok'], (string) ($res['error'] ?? ''));
    $codes = array_map(static fn($x) => (string) $x['args']['customer_id'], $res['actions']);
    assertTrue(in_array('CUS-' . sprintf('%06d', $a['cust']), $codes, true), '第一条 armtek：' . implode('/', $codes));
    assertTrue(in_array('CUS-' . sprintf('%06d', $b['cust']), $codes, true), '第二条 armtek（中文后缀也算）：' . implode('/', $codes));
    assertEquals(2, count($res['actions']), '不该把 Globex 也卷进来');

    $run = Ai::execute(Ai::validatePlan($res['actions'], $u)['actions'], $u, 6);
    assertEquals(2, $run['applied']);
    assertEquals($before['customers'] - 2, bulkCounts()['customers']);
    assertTrue(is_array((new Customer())->find($keep['cust'])), '无关客户安然无恙');
}

function test_a_query_only_instruction_never_writes(): void
{
    $u = bulkAdmin();
    bulkAccount('锐创轴承', 'India');
    $before = bulkCounts();

    $res = Ai::complete('查一下印度国家的所有客户信息');
    assertTrue($res['ok'], (string) ($res['error'] ?? ''));
    assertEquals(1, count($res['rounds']), '查了一轮');
    assertEquals(0, count($res['actions']), '第 2 轮不再发查询，也不写数据');
    assertContains('India', (string) $res['rounds'][0]['results'][0]['message']);
    assertContains('未改动任何数据', (string) $res['reply'], '回执说清楚这轮什么都没改');
    assertEquals($before, bulkCounts());
}

function test_the_bulk_delete_cap_is_enforced_by_the_server(): void
{
    $u = bulkAdmin();
    for ($i = 0; $i < Ai::MAX_DELETES + 5; $i++) {
        bulkAccount('印度客户 ' . $i, 'India');
    }
    $before = bulkCounts();

    $res = Ai::complete('删除印度国家的所有客户信息');
    assertTrue($res['ok'], (string) ($res['error'] ?? ''));
    $checked = Ai::validatePlan($res['actions'], $u);
    assertTrue($checked['blocked'], '超出上限的批量删除必须被服务端拦住');
    $err = implode('；', $checked['errors']);
    assertContains('一次最多删除 ' . Ai::MAX_DELETES . ' 条', $err);
    assertContains('分批', $err, '并且告诉你怎么办');
    assertEquals($before, bulkCounts(), '拦住之后一行都没删');
}

/** 模型每轮都只查询时，循环必须有尽头，并且把已查到的东西交回去。 */
function test_the_loop_terminates_when_the_model_keeps_querying(): void
{
    $u = bulkAdmin();
    bulkAccount('锐创轴承', 'India');
    (new Setting())->setMany(['ai_provider' => 'deepseek', 'ai_model' => 'deepseek-v4-flash',
        'ai_api_key' => 'sk-test-1234567890abcdef', 'ai_base_url' => ''], 1);
    Setting::flushCache();

    $calls = 0;
    AiClient::$transport = static function () use (&$calls) {
        $calls++;
        return ['ok' => true, 'json' => ['choices' => [['message' => ['content' =>
            '{"reply":"我再查一次","actions":[{"tool":"search_records","args":{"q":"锐创","tables":"customer"}}]}']]]],
            'error' => '', 'status' => 200, 'raw' => ''];
    };
    $res = Ai::complete('查一下锐创');
    AiClient::$transport = null;
    assertEquals(Ai::MAX_TOOL_ROUNDS, $calls, '最多问 ' . Ai::MAX_TOOL_ROUNDS . ' 轮就收手');
    assertEquals(false, $res['ok'], '没有最终计划要如实报告');
    assertContains('只在查询', (string) $res['error']);
    assertEquals(Ai::MAX_TOOL_ROUNDS, count($res['rounds']), '每一轮查过什么都留了轨迹');
    assertTrue(is_array($res['rounds'][0]['results'] ?? null), '每轮结果都在，用户能看到查到了什么');
    (new Setting())->setMany(['ai_provider' => 'mock'], 1);
    Setting::flushCache();
}

/** 多轮的查询轨迹与合计要进审计，事后能审。 */
function test_rounds_and_summary_are_audited(): void
{
    $u = bulkAdmin();
    bulkAccount('锐创轴承', 'India');
    $cfg = AiClient::config();
    $id = (int) (new Ai())->record($u, '删除印度国家的所有客户信息', [
        'actions' => [['tool' => 'delete_customer', 'args' => ['customer_id' => 'CUS-000001', 'confirm' => true, 'reason' => 'x'],
            'kind' => 'delete', 'destructive' => true, 'impact' => ['target' => 'CUS-000001 锐创轴承', 'cascade' => ['线索' => 1], 'count' => 2]]],
        'reply' => '', 'blocked' => false, 'latency_ms' => 900,
        'rounds' => [['round' => 1, 'asked' => [['tool' => 'search_records', 'args' => ['country' => 'India']]],
                      'results' => [['tool' => 'search_records', 'ok' => true, 'message' => '找到 1 条', 'rows' => []]]]],
        'summary' => Ai::planSummary([['kind' => 'delete', 'destructive' => true,
            'impact' => ['target' => 'CUS-000001 锐创轴承', 'cascade' => ['线索' => 1], 'count' => 2]]]),
    ], $cfg);
    $plan = Ai::planOf((new Ai())->find($id));
    assertEquals(1, count($plan['rounds']), '轮次轨迹存下来了');
    assertEquals('India', (string) $plan['rounds'][0]['asked'][0]['args']['country']);
    assertEquals(1, (int) $plan['summary']['delete']);
    assertEquals(1, (int) $plan['summary']['cascade']['线索']);
    Database::connection()->query('DELETE FROM ai_actions WHERE id = ' . $id)->execute();
}

/** 提示词要告诉模型这套用法，否则它不会主动查询。 */
function test_the_prompt_teaches_the_query_then_act_pattern(): void
{
    $prompt = Ai::systemPrompt();
    assertContains('先只发查询', $prompt);   // 规则正文会被精简，断言盯住“先查后做”这件事本身
    assertContains('country / status / stage / owner', $prompt);
    assertContains('tool_results', $prompt);
    assertContains('一次最多删除 ' . Ai::MAX_DELETES . ' 条', $prompt);
    assertContains('一个 delete_customer 就够', $prompt, '要教它别把级联重复发一遍');
    assertContains('q 留空', $prompt);
    assertTrue(textLength($prompt) < 6800, '提示词长度仍然受控（' . textLength($prompt) . ' 字）');
}

/** 搜索的新参数得出现在文档与服务商表里（同源生成，不手写）。 */
function test_the_docs_show_the_new_parameters(): void
{
    $map = AppMap::all();
    $search = null;
    foreach ($map['ai_tools'] as $t) {
        if ($t['name'] === 'search_records') {
            $search = $t;
        }
    }
    assertTrue(is_array($search), '文档里有 search_records');
    $keys = array_column($search['params'], 'name');
    foreach (['q', 'tables', 'country', 'status', 'stage', 'owner', 'limit'] as $need) {
        assertTrue(in_array($need, $keys, true), "文档缺少参数 {$need}");
    }
    $ctx = AppMap::toText();
    assertContains('search_records', $ctx);
    assertContains('country', $ctx);
}

function test_whole_table_listing_needs_explicit_all_and_reports_exact_total(): void
{
    $u = bulkAdmin();
    for ($i = 0; $i < 12; $i++) {
        (new Customer())->create(['name' => '整表客户 ' . $i, 'status' => 'active',
            'source_country' => 'India', 'owner_id' => 1]);
    }

    // 什么条件都不给：只能显式说“整表”
    $lazy = Ai::validatePlan([['tool' => 'search_records', 'args' => ['limit' => '10']]], $u);
    assertTrue($lazy['blocked'], '不给条件也不说 all → 拒');
    assertContains('all:true', implode('；', $lazy['errors']));

    $all = Ai::validatePlan([['tool' => 'search_records',
        'args' => ['tables' => 'customer', 'all' => 'true', 'limit' => '10']]], $u);
    assertEquals(false, $all['blocked'], implode('；', $all['errors']));
    $run = Ai::execute($all['actions'], $u);
    // 种子库本身就有客户，所以断言“至少 12”并重点看总数准确
    $msg = (string) $run['results'][0]['message'];
    assertTrue((int) $run['results'][0]['total'] >= 12, 'total 是真实总数：' . $msg);
    assertContains('共 ', $msg, '回执直接说共多少条');
    assertEquals(10, count($run['results'][0]['rows'] ?? []), '但只列了 limit 要求的 10 条');
    assertContains('只列出前 10 条', $msg, '并承认自己只列了部分 —— 总数与条数不能含糊');

    // 总数不能大于真实行数（计数与列表同一口径）
    $real = (int) Database::connection()->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    assertEquals($real, (int) $run['results'][0]['total'], 'count 与 SELECT COUNT(*) 一致');
}

function test_a_count_question_is_answered_not_executed(): void
{
    $u = bulkAdmin();
    for ($i = 0; $i < 5; $i++) {
        (new Customer())->create(['name' => '问数量客户 ' . $i, 'status' => 'active', 'owner_id' => 1]);
    }
    $before = bulkCounts();

    $res = Ai::complete('现在有多少客户了');
    assertTrue($res['ok'], (string) ($res['error'] ?? ''));
    assertEquals(0, count($res['actions']), '这是一句提问，不是写操作');
    assertContains('客户', (string) $res['reply']);
    assertTrue((bool) preg_match('~\d+~u', (string) $res['reply']), '回答里有具体数字：' . $res['reply']);
    assertContains('未改动任何数据', (string) $res['reply']);
    assertEquals($before, bulkCounts(), '问数量不会改数据');
}

function test_bulk_delete_of_ai_requests_by_condition(): void
{
    $u = bulkAdmin();
    $cfg = AiClient::config();
    $db = Database::connection();
    $before = (int) $db->query('SELECT COUNT(*) FROM ai_actions')->fetchColumn();
    for ($i = 0; $i < 3; $i++) {
        (new Ai())->record($u, '待清理的历史记录 ' . $i, ['actions' => [], 'status' => 'pending'], $cfg);
    }
    $now = (int) $db->query('SELECT COUNT(*) FROM ai_actions')->fetchColumn();
    assertTrue($now > $before, '先堆几条 AI 记录');

    $res = Ai::complete('删除所有AI请求记录');
    assertTrue($res['ok'], (string) ($res['error'] ?? ''));
    assertEquals(1, count($res['rounds']), '先查了一轮');
    assertTrue(count($res['actions']) >= 3, '每个查询到的记录一个删除动作');
    foreach ($res['actions'] as $a) {
        assertEquals('delete_ai_request', $a['tool']);
    }
    $checked = Ai::validatePlan($res['actions'], $u);
    assertEquals(false, $checked['blocked'], implode('；', $checked['errors']));
    assertEquals(1, Ai::planSummary($checked['actions'])['delete'] > 2 ? 1 : 0, '合计里统计到了多条');

    Ai::execute($checked['actions'], $u, 999);
    assertEquals(0, (int) $db->query('SELECT COUNT(*) FROM ai_actions')->fetchColumn(), '整表 AI 记录被清干净');
}

/** 改名字这类“不该由 AI 做”的指令，要给出去哪儿办，而不是“没听懂”。 */
function test_out_of_scope_instructions_say_where_to_go(): void
{
    bulkAdmin();
    $res = Ai::complete('将我的名字改成wayne');
    assertEquals(0, count($res['actions']), '不拿其他工具乱 substitute');
    assertContains('设置 → 个人信息', (string) $res['reply']);
    assertContains('没有修改用户资料', (string) $res['reply']);
}

/** 真 Key 实测踩过：模型写 all:false 表示“不要整表”，结果被参数校验拒了。 */
function test_all_false_is_a_valid_answer_and_totals_are_exact(): void
{
    $u = bulkAdmin();
    for ($i = 0; $i < 4; $i++) {
        (new Customer())->create(['name' => '计数客户 ' . $i, 'status' => 'active', 'source_country' => 'India', 'owner_id' => 1]);
    }
    $real = (int) Database::connection()->query('SELECT COUNT(*) FROM customers')->fetchColumn();

    $plan = Ai::validatePlan([['tool' => 'search_records',
        'args' => ['tables' => ['customer'], 'country' => 'India', 'all' => false]]], $u);
    assertEquals(false, $plan['blocked'], 'all:false 必须能过：' . implode('；', $plan['errors']));
    $run = Ai::execute($plan['actions'], $u);
    assertEquals(4, (int) $run['results'][0]['total'], '按国家过滤的总数要准');

    // 「有多少」类问题的答案要来自系统给的准确总数，而不是模型自己数
    $digest = Ai::contextDigest($u, 5);
    assertContains('库内总数', $digest);
    assertContains('客户 ' . $real, $digest, '快照里带精确总数：' . textClip($digest, 160));
    assertContains('不要自己估', $digest);
}

/**
 * 真 Key 实测踩到的坑：模型会凭名字猜国籍，把伊拉克/埃及客户也列进「印度客户」的删除计划。
 * 所以 ≥2 个删除动作必须本轮真查过库作背书（除非用户自己点名了编号）。
 */
function test_bulk_deletes_are_forced_to_query_first(): void
{
    $u = bulkAdmin();
    for ($i = 0; $i < 4; $i++) {
        (new Customer())->create(['name' => '猜测受害者 ' . $i, 'company' => 'Asia Trading', 'status' => 'active',
            'source_country' => $i % 2 ? 'Iraq' : 'India', 'owner_id' => 1]);
    }
    $codes = Database::connection()->query("SELECT public_code FROM customers WHERE source_country = 'Iraq'")->fetchAll(PDO::FETCH_COLUMN);
    assertTrue($codes !== [], '副本里准备了非印度的客户');
    $guessed = json_encode(array_map(static fn($c) => ['tool' => 'delete_customer',
        'args' => ['customer_id' => $c, 'confirm' => true, 'reason' => '看起来像印度人']], $codes), JSON_UNESCAPED_UNICODE);
    $guessed = '{"reply":"我按国籍挑了几条","actions":' . $guessed . '}';
    $queryFirst = '{"reply":"先查印度客户","actions":[{"tool":"search_records","args":{"tables":"customer","country":"India"}}]}';
    $verified = str_replace('看起来像印度人', '查询确认是印度客户', $guessed);

    $script = [$guessed, $queryFirst, $verified];
    (new Setting())->setMany(['ai_provider' => 'deepseek', 'ai_model' => 'deepseek-v4-flash',
        'ai_api_key' => 'sk-test-1234567890abcdef', 'ai_base_url' => ''], 1);
    Setting::flushCache();
    $calls = 0;
    AiClient::$transport = static function () use (&$calls, $script) {
        $content = $script[min($calls, count($script) - 1)];
        $calls++;
        return ['ok' => true, 'json' => ['choices' => [['message' => ['content' => $content]]]],
                'error' => '', 'status' => 200, 'raw' => ''];
    };
    $res = Ai::complete('删除印度国家的所有客户信息');
    AiClient::$transport = null;
    (new Setting())->setMany(['ai_provider' => 'mock'], 1);
    Setting::flushCache();

    assertTrue($calls >= 3, '第一次被推回去查库：调了 ' . $calls . ' 次');
    assertEquals(1, count($res['rounds'] ?? []), '中间确实执行了一次查询');
    assertEquals('search_records', (string) $res['rounds'][0]['asked'][0]['tool']);
    assertEquals(3, $calls, '查询之后才允许出删除计划');
    assertTrue($res['ok'] && count($res['actions']) >= 1, '最终拿到了删除计划');
    // 用户点名编号时不该被强制多跑一轮
    $first = $script[0];
    $named = '{"reply":"按编号删两条","actions":[{"tool":"delete_customer","args":{"customer_id":"'
        . $codes[0] . '","confirm":true,"reason":"用户点名"}},{"tool":"delete_customer","args":{"customer_id":"'
        . $codes[1] . '","confirm":true,"reason":"用户点名"}}]}';
    $script = [$named, $named];
    (new Setting())->setMany(['ai_provider' => 'deepseek'], 1);
    Setting::flushCache();
    $calls2 = 0;
    AiClient::$transport = static function () use (&$calls2, $script) {
        $calls2++;
        return ['ok' => true, 'json' => ['choices' => [['message' => ['content' => $script[0]]]]],
                'error' => '', 'status' => 200, 'raw' => ''];
    };
    $res2 = Ai::complete('删掉客户 ' . $codes[0] . ' 和 ' . $codes[1]);
    AiClient::$transport = null;
    (new Setting())->setMany(['ai_provider' => 'mock'], 1);
    Setting::flushCache();
    assertEquals(1, $calls2, '用户已点名编号 → 不用强迫先查');
    assertEquals(0, count($res2['rounds'] ?? []));
}

/**
 * 真库现状：source_country 一列里中英混写（印度 2、埃及 1、伊拉克 1、United States 1…）。
 * 单向映射会查出 0 条，然后模型就开始凭名字猜国籍 —— 这一条守住了双向匹配。
 */
function test_country_filter_matches_both_chinese_and_english_spellings(): void
{
    $u = bulkAdmin();
    (new Customer())->create(['name' => '中文写法客户', 'status' => 'active', 'source_country' => '印度', 'owner_id' => 1]);
    (new Customer())->create(['name' => '英文写法客户', 'status' => 'active', 'source_country' => 'India', 'owner_id' => 1]);
    (new Customer())->create(['name' => '邻居国家客户', 'status' => 'active', 'source_country' => '印度尼西亚', 'owner_id' => 1]);
    (new Customer())->create(['name' => '埃及客户', 'status' => 'active', 'source_country' => '埃及', 'owner_id' => 1]);

    foreach (['India', '印度', '印度的'] as $said) {
        $run = Ai::execute(Ai::validatePlan([['tool' => 'search_records',
            'args' => ['tables' => 'customer', 'country' => $said]]], $u)['actions'], $u);
        assertEquals(2, (int) $run['results'][0]['total'], "说「{$said}」应当同时命中中英两种写法：" . $run['results'][0]['message']);
        $names = array_map(static fn($r) => (string) $r['detail'], $run['results'][0]['rows']);
        $blob = implode(' ', $names);
        assertContains('中文写法客户', $blob);
        assertContains('英文写法客户', $blob);
        assertTrue(!str_contains($blob, '印度尼西亚'), '「印度」绝不能连带命中「印度尼西亚」');
        assertTrue(!str_contains($blob, '埃及客户'), '也不能命中别的国家');
    }

    // 批量删除同样只会删这两条
    $res = Ai::complete('删除印度国家的所有客户');
    assertTrue($res['ok'], (string) ($res['error'] ?? ''));
    assertEquals(2, count($res['actions']), '只删印度的两条：' . json_encode(array_column($res['actions'], 'args'), JSON_UNESCAPED_UNICODE));
    $checked = Ai::validatePlan($res['actions'], $u);
    Ai::execute($checked['actions'], $u, 7);
    $rest = Database::connection()->query('SELECT name FROM customers WHERE name LIKE "%客户%" ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    assertEquals(['埃及客户', '印度尼西亚' === '' ? '' : '邻居国家客户'], $rest, '剩下的正好是非印度的两条');
}

runCase();
