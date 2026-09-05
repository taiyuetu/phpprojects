<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
/**
 * 上下文窗口：AI 记得同一个账号最近的处理记录。
 *
 * 没有这一块，「把刚才那条线索标为流失」「今天你帮我改了什么」永远做不到 ——
 * 模型每次收到的都是全新对话。历史不另存副本，直接读审计表 ai_actions（唯一的真相），
 * ai_context_minutes 决定窗口长度，0 表示关闭。
 */
require __DIR__ . '/../bootstrap.php';

function ctxAdmin(): int
{
    $_SESSION['user_id'] = 1;
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
    (new Setting())->setMany(['ai_enabled' => '1', 'ai_provider' => 'mock', 'ai_mode' => 'preview',
        'ai_context_minutes' => '60', 'ai_allow_delete' => '1'], 1);
    Setting::flushCache();
    return 1;
}

/** 直接写一条审计记录（等价于一次历史请求），可控时间与状态 */
function ctxRow(array $over = []): int
{
    return (int) (new Ai())->create(array_merge([
        'user_id'     => 1,
        'instruction' => '查一下印度的客户',
        'reply'       => '查到 2 位：CUS-000006、CUS-000007',
        'plan_json'   => json_encode(['actions' => [['tool' => 'search_records', 'args' => ['tables' => 'customer'],
            'kind' => 'read']]], JSON_UNESCAPED_UNICODE),
        'result_json' => json_encode(['results' => [['ok' => true, 'code' => 'CUS-000006']]], JSON_UNESCAPED_UNICODE),
        'status'      => 'executed',
        'provider'    => 'mock',
        'model'       => 'triphase-mock',
        'latency_ms'  => 1200,
    ], $over));
}

function ctxPrompt(): string
{
    // [0] 是系统提示词（里面本来就有 “<history>” 这个词），用户消息才是 [1]
    return (string) Ai::messages('随便', 1)[1]['content'];
}

/** 上下文必须真的进提示词，并且带着真实编号与我当时的回答 */
function test_previous_requests_reach_the_prompt(): void
{
    ctxAdmin();
    $lead = (new Lead())->create(['title' => '拉合特询盘', 'status' => 'new', 'owner_id' => 1]);
    $code = (new Lead())->codeOf((new Lead())->find($lead));
    ctxRow(['instruction' => '把 ' . $code . ' 的金额改成 2 万',
        'reply' => '已更新金额',
        'plan_json' => json_encode(['actions' => [['tool' => 'update_lead', 'args' => ['lead_id' => $code, 'value' => 20000],
            'kind' => 'write']]], JSON_UNESCAPED_UNICODE),
        'status' => 'executed']);

    $digest = Ai::historyDigest(1);
    assertTrue($digest !== '', '窗口内应有历史');
    assertContains('最近 1 小时', $digest, '窗口要用人名说话');
    assertContains($code, $digest, '历史里必须有真实编号，否则“刚才那条”无从指代');
    assertContains('已执行', $digest, '状态要中文');
    assertContains('已更新金额', $digest, '当时的回答要在');

    $prompt = ctxPrompt();
    assertContains('<history>', $prompt);
    assertContains('</history>', $prompt);
    assertContains($code, $prompt);
}

/** 关掉窗口就不该多花一个字的提示词；换窗口长度要立刻生效 */
function test_the_window_is_a_real_switch(): void
{
    ctxAdmin();
    $code = (new Lead())->codeOf((new Lead())->find(
        (new Lead())->create(['title' => '窗口测试线索', 'status' => 'new', 'owner_id' => 1])));
    ctxRow(['instruction' => '看看 ' . $code, 'status' => 'executed']);

    (new Setting())->setMany(['ai_context_minutes' => '0'], 1);
    Setting::flushCache();
    assertEquals('', Ai::historyDigest(1), '窗口=关闭 时不该有任何历史');
    assertEquals(0, Ai::contextMinutes());
    assertTrue(!str_contains((string) Ai::messages('随便', 1)[1]['content'], '<history>'), '关掉后用户消息里不该有 history 块');

    (new Setting())->setMany(['ai_context_minutes' => '1440'], 1);
    Setting::flushCache();
    assertEquals(1440, Ai::contextMinutes());
    assertContains('今天之内', Ai::contextWindowLabel());
    assertContains($code, Ai::historyDigest(1));

    // 越界值要收住，不能让人把提示词撑爆
    (new Setting())->setMany(['ai_context_minutes' => '999999'], 1);
    Setting::flushCache();
    assertEquals(10080, Ai::contextMinutes(), '最长 7 天');
    ctxAdmin();
}

/** 窗口外的记录必须掉出去：这是“一定时间的缓存”的全部含义 */
function test_rows_outside_the_window_drop_out(): void
{
    $user = ctxAdmin();
    $db = new Database();
    $old = ctxRow(['instruction' => '三年前的旧请求', 'status' => 'executed']);
    $db->query('UPDATE ai_actions SET created_at = datetime(\'now\', \'-400 days\') WHERE id = :i')
        ->bind(':i', $old)->execute();
    $fresh = ctxRow(['instruction' => '刚刚的新请求', 'status' => 'executed']);

    $digest = Ai::historyDigest($user);
    assertContains('刚刚的新请求', $digest);
    assertTrue(!str_contains($digest, '三年前的旧请求'), '窗口外的历史不能进提示词');

    // 换到 7 天窗口，400 天前的仍然不在；但同一窗口能收到“3 天前”的那条
    $mid = ctxRow(['instruction' => '三天前的请求', 'status' => 'executed']);
    $db->query('UPDATE ai_actions SET created_at = datetime(\'now\', \'-3 days\') WHERE id = :i')
        ->bind(':i', $mid)->execute();
    (new Setting())->setMany(['ai_context_minutes' => '10080'], 1);
    Setting::flushCache();
    $week = Ai::historyDigest($user);
    assertContains('三天前的请求', $week);
    assertTrue(!str_contains($week, '三年前的旧请求'), '超出 7 天仍然不算上下文');
    (new Setting())->setMany(['ai_context_minutes' => '60'], 1);
    Setting::flushCache();
    assertTrue(strpos(Ai::historyDigest($user), '三天前的请求') === false, '回到 1 小时窗口后 3 天前的又掉出去');
}

/** 统计行：模型不许自己数“今天你干了几件事” */
function test_the_digest_counts_are_exact(): void
{
    $user = ctxAdmin();
    ctxRow(['status' => 'executed']);
    ctxRow(['status' => 'cancelled']);
    ctxRow(['status' => 'failed', 'error' => '模型没有按 JSON 格式返回']);
    $digest = Ai::historyDigest($user);
    assertContains('共 3 次', $digest, '计数必须准确：' . textClip($digest, 120));
    assertContains('已执行 1', $digest);
    assertContains('已取消 1', $digest);
    assertContains('执行失败 1', $digest);
    assertContains('未完成原因', $digest, '失败原因要带上，用户才知道上次为什么没成');
}

/** 提示词不能被人把历史撑爆：有上限，超了就省略最老的 */
function test_the_digest_is_bounded(): void
{
    $user = ctxAdmin();
    for ($i = 0; $i < 24; $i++) {
        ctxRow(['instruction' => '批量历史请求第 ' . $i . ' 条，内容很长'. str_repeat('哈', 40)]);
    }
    $digest = Ai::historyDigest($user, 10, 1500);
    assertTrue(textLength($digest) <= 1800, '历史块体积失控：' . textLength($digest) . ' 字');
    assertContains('未列出', $digest, '被截断时要说明省略了多少：' . textClip($digest, 90));
    assertContains('days/from/to', $digest, '要告诉模型怎么查更早的');
}

/** 别人的历史绝不能进我的上下文 */
function test_history_is_scoped_to_its_owner(): void
{
    ctxAdmin();
    $colleague = (int) (new User())->register('隔壁同事', 'mate.' . substr(md5('m' . microtime()), 0, 8) . '@example.com', 'pw12345678', 'sales');
    ctxRow(['user_id' => $colleague, 'instruction' => '同事的私有请求']);
    $digest = Ai::historyDigest(1);
    assertTrue(!str_contains($digest, '同事的私有请求'), '上下文只能看到自己发起的请求');

    // 管理员按既定规则能看全站审计（下面这条是 sales 的情况）
    $asAdmin = json_encode(Ai::execute(Ai::validatePlan([['tool' => 'search_records',
        'args' => ['tables' => 'ai_request', 'days' => '1']]], 1)['actions'], 1), JSON_UNESCAPED_UNICODE);

    $_SESSION['user_id'] = $self2 = (int) (new User())->register('上下文旁观者', 'bystander.' . substr(md5('b' . microtime()), 0, 8) . '@example.com', 'pw12345678', 'sales');
    $_SESSION['user'] = ['id' => $self2, 'role' => 'sales'];
    $asSales = Ai::execute(Ai::validatePlan([['tool' => 'search_records',
        'args' => ['tables' => 'ai_request', 'days' => '1']]], $self2)['actions'], $self2);
    assertTrue(!str_contains(json_encode($asSales, JSON_UNESCAPED_UNICODE), '同事的私有请求'),
        '普通账号搜 AI 记录时不该看到同事的');
    assertTrue(str_contains($asAdmin, '同事的私有请求'), '管理员看全站审计（与 操作记录 页一致）');
}

/** 时间过滤：days / from / to —— 「今天之前」得能查 */
function test_search_by_time_range(): void
{
    $user = ctxAdmin();
    $db = new Database();
    $today = ctxRow(['instruction' => '今天删了印度客户', 'status' => 'executed']);
    $before = ctxRow(['instruction' => '今天之前的报价请求', 'status' => 'executed']);
    $db->query('UPDATE ai_actions SET created_at = datetime(\'now\', \'-10 days\') WHERE id = :i')
        ->bind(':i', $before)->execute();

    $run = static fn(array $args) => Ai::execute(
        Ai::validatePlan([['tool' => 'search_records', 'args' => $args]], $user)['actions'], $user)['results'][0];

    $all = $run(['tables' => 'ai_request', 'days' => '90']);
    assertContains('今天删了印度客户', json_encode($all, JSON_UNESCAPED_UNICODE));
    assertContains('今天之前的报价请求', json_encode($all, JSON_UNESCAPED_UNICODE));

    $just = $run(['tables' => 'ai_request', 'days' => '1']);
    assertContains('今天删了印度客户', json_encode($just, JSON_UNESCAPED_UNICODE));
    assertTrue(!str_contains(json_encode($just, JSON_UNESCAPED_UNICODE), '今天之前的报价请求'),
        'days:1 不该把 10 天前的捞进来');

    $range = $run(['tables' => 'ai_request', 'from' => date('Y-m-d', strtotime('-11 days')),
        'to' => date('Y-m-d', strtotime('-9 days'))]);
    assertContains('今天之前的报价请求', json_encode($range, JSON_UNESCAPED_UNICODE));
    assertTrue((int) $range['total'] === 1, 'from/to 应当精确框住这一天附近：' . textClip((string) ($range['message'] ?? ''), 120));

    // 只给时间条件也是合法查询（不能被“至少要一个条件”误拒）
    $checked = Ai::validatePlan([['tool' => 'search_records', 'args' => ['tables' => 'ai_request', 'days' => '7']]], $user);
    assertEquals([], $checked['errors'], '只给时间范围也算有条件：' . json_encode($checked['errors'], JSON_UNESCAPED_UNICODE));

    // 但日期写错了要被拦下，而不是静默放宽成全表
    $bad = Ai::validatePlan([['tool' => 'search_records', 'args' => ['tables' => 'ai_request', 'from' => '前年那阵子']]], $user);
    assertTrue($bad['blocked'], '无法识别的日期必须拒绝');
    assertContains('起始日期', $bad['errors'][0]);
}

/** 详情要能回答“上次那个删除到底删了哪几条” */
function test_ai_request_detail_shows_what_it_touched(): void
{
    $user = ctxAdmin();
    $cust = (new Customer())->create(['name' => '被删的', 'status' => 'active', 'owner_id' => $user]);
    $code = (new Customer())->codeOf((new Customer())->find($cust));
    $id = ctxRow(['instruction' => '删除 ' . $code, 'status' => 'executed',
        'plan_json' => json_encode(['actions' => [
            ['tool' => 'delete_customer', 'args' => ['customer_id' => $code, 'confirm' => true, 'reason' => '重复'], 'kind' => 'delete'],
        ], 'summary' => ['total' => 3]], JSON_UNESCAPED_UNICODE)]);

    $res = Ai::execute(Ai::validatePlan([['tool' => 'get_record', 'args' => ['type' => 'ai_request', 'id' => (string) $id]]], $user)['actions'], $user);
    $msg = (string) $res['results'][0]['message'];
    assertContains('delete_customer×1', $msg, '当时计划要在：' . textClip($msg, 160));
    assertContains($code, $msg, '涉及记录编号要在');
    assertContains('合计影响行数：3', $msg);
    assertTrue(!str_contains($msg, '{"actions"'), '原始 JSON 不该整坨甩给模型');
}

/** 演示模型必须能演示“接着上次做”，否则这条路径在离线状态从未被测 */
function test_the_demo_model_continues_from_context(): void
{
    $user = ctxAdmin();
    $lead = (new Lead())->create(['title' => '接续测试线索', 'status' => 'new', 'owner_id' => $user]);
    $code = (new Lead())->codeOf((new Lead())->find($lead));
    ctxRow(['instruction' => '新建了 ' . $code, 'status' => 'executed',
        'plan_json' => json_encode(['actions' => [['tool' => 'create_lead', 'args' => ['lead_id' => $lead], 'kind' => 'write']]],
            JSON_UNESCAPED_UNICODE)]);

    $res = Ai::complete('把刚才那条线索标记为流失');
    assertTrue($res['ok'], (string) ($res['error'] ?? ''));
    assertEquals(1, count($res['actions']), '应当正好一个动作：' . json_encode($res['actions'], JSON_UNESCAPED_UNICODE));
    assertEquals('update_lead_status', (string) $res['actions'][0]['tool']);
    assertEquals($code, (string) $res['actions'][0]['args']['lead_id'], '编号必须来自上下文');
    assertContains($code, (string) $res['reply']);

    $checked = Ai::validatePlan($res['actions'], $user);
    assertEquals([], $checked['errors'], json_encode($checked['errors'], JSON_UNESCAPED_UNICODE));
    Ai::execute($checked['actions'], $user, 999);
    assertTrue((string) ((new Lead())->find($lead)['status'] ?? '') === 'lost', '延续指令要真的落到那条记录上');

    // 纯问答：读工具在循环里当场执行完，所以最终 actions 为空——要断言的是“查过、且没写”
    $q = Ai::complete('今天你都帮我做了什么');
    $asked = [];
    foreach ((array) ($q['rounds'] ?? []) as $rd) {
        foreach ((array) ($rd['asked'] ?? []) as $a2) {
            $asked[] = (string) ($a2['tool'] ?? '');
        }
    }
    assertTrue(in_array('search_records', $asked, true), '应该自己去查历史：' . json_encode($asked));
    $writes = array_filter((array) ($q['actions'] ?? []), static fn($a) => (string) ($a['kind'] ?? '') !== 'read');
    assertEquals([], $writes, '问历史绝不该产生写动作');
    $left = (new Database())->query("SELECT COUNT(*) AS c FROM ai_actions WHERE status <> 'executed' OR status IS NULL")->single();
    assertTrue((int) $left['c'] <= 1, '只读问答不该留下待确认计划');
}

/** 提示词与文档都要交代清楚这套机制 */
function test_prompt_and_docs_explain_context(): void
{
    ctxAdmin();
    ctxRow(['instruction' => '把 ' . (new Lead())->codeOf((new Lead())->find((new Lead())->create(
        ['title' => '文档断言线索', 'status' => 'new', 'owner_id' => 1]))) . ' 的金额改成 1 万',
        'reply' => '已更新金额', 'status' => 'executed']);
    // 历史里的旧版本拒绝不能被当作当前能力（实测真模型会把 v1.7 之前那句“线索没有来源国家字段”复读回去）
    $digest = Ai::historyDigest(1);
    assertTrue($digest !== '', '演示前提：窗口内要有历史');
    assertContains('更早版本的限制', $digest, '历史块要自带免责声明：' . textClip($digest, 120));
    assertContains('并没有真的做成', $digest, '已取消/失败的条目要说清楚当时没成');

    $prompt = Ai::systemPrompt();
    assertContains('<history>', $prompt, '规则里要解释 history 块');
    assertTrue(str_contains($prompt, '独立新建') || str_contains($prompt, '不需要任何编号'),
        '不能让模型拿“缺客户编号”当理由拒绝建线索');
    assertContains('ai_request', $prompt, '要告诉模型怎么主动查更早的历史');
    assertContains('days', $prompt, '时间过滤参数要在工具表里');

    $docs = AppMap::toText();
    assertContains('ai_context_minutes', $docs, '设置项要出现在数据字典/设置清单里');
    assertContains('historyDigest', $docs, '说明要指到具体方法');
    $settings = AppMap::settings();
    assertTrue(isset($settings['ai_context_minutes']) || in_array('ai_context_minutes',
        array_map(static fn($r) => (string) ($r['name'] ?? ''), (array) (is_array(reset($settings)) ? $settings : [])), true)
        || str_contains(json_encode($settings, JSON_UNESCAPED_UNICODE), 'ai_context_minutes'), 'AppMap 设置清单要含这一项');
}

/** 指代消解：用户不抄编号也能干活（实测模型“看到了编号但不敢用”，会反问编号） */
function test_deictic_words_resolve_to_concrete_codes(): void
{
    $user = ctxAdmin();
    $lead = (new Lead())->create(['title' => '指代测试线索', 'status' => 'new', 'owner_id' => $user]);
    $leadCode = (new Lead())->codeOf((new Lead())->find($lead));
    $cust = (new Customer())->create(['name' => '指代测试客户', 'status' => 'active', 'owner_id' => $user]);
    $custCode = (new Customer())->codeOf((new Customer())->find($cust));
    ctxRow(['instruction' => '建了客户 ' . $custCode, 'status' => 'executed',
        'plan_json' => json_encode(['actions' => [['tool' => 'create_customer', 'args' => ['customer_id' => $cust], 'kind' => 'write']]],
            JSON_UNESCAPED_UNICODE)]);
    ctxRow(['instruction' => '建了线索 ' . $leadCode, 'status' => 'executed',
        'plan_json' => json_encode(['actions' => [['tool' => 'create_lead', 'args' => ['lead_id' => $lead], 'kind' => 'write']]],
            JSON_UNESCAPED_UNICODE)]);

    $block = Ai::contextReferenceBlock('把刚才那条线索的来源国家改成伊拉克');
    assertContains($leadCode, $block, '“刚才那条线索”必须被钉成真实编号：' . textClip($block, 160));
    assertContains('不要再问用户要编号', $block, '要顺带把模型的犹豫压掉');
    assertTrue(!str_contains($block, $custCode), '用户说的是线索，就不该把客户编号排在前面');

    // 没有指代词时不要塞这块（省提示词）
    assertEquals('', Ai::contextReferenceBlock('现在有多少客户了'), '无关指令不该注入指代块');

    // 关掉窗口就没有上下文，指代块也必须为空（不能凭空造编号）
    (new Setting())->setMany(['ai_context_minutes' => '0'], 1);
    Setting::flushCache();
    Ai::flushHistoryCache();
    assertEquals('', Ai::contextReferenceBlock('把刚才那条线索标为流失'), '没有上下文时不许猜编号');
    ctxAdmin();

    // 演示模型走完整链路：不给编号也能改对那条
    $res = Ai::complete('把刚才那条线索的来源城市改成埃尔比勒');
    assertTrue($res['ok'], (string) ($res['error'] ?? ''));
    assertEquals('update_lead', (string) ($res['actions'][0]['tool'] ?? ''), json_encode($res['actions'], JSON_UNESCAPED_UNICODE));
    $checked = Ai::validatePlan($res['actions'], $user);
    assertEquals([], $checked['errors'], json_encode($checked['errors'], JSON_UNESCAPED_UNICODE));
    Ai::execute($checked['actions'], $user, 999);
    assertEquals('埃尔比勒', (string) ((new Lead())->find($lead)['source_city'] ?? ''), '应落在上下文那条线索上');
    assertTrue((string) (new Customer())->find($cust)['source_city'] === '', '别误改到客户身上');
}

runCase();