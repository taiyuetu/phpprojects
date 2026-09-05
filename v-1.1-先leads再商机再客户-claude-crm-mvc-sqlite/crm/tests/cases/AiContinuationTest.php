<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
/**
 * 报障回归：「更新客户 ashmad 的电话」→ 模型说“没有 ashmad，最像 CUS-000020（Ahmad），请确认”
 * → 用户回「确认」→ 模型却答“请问您想确认什么内容？”。
 *
 * 两个独立缺陷叠在一起：
 *   1) 拼写差一个字母时精确检索查不到，模型只能反问；
 *   2) 它反问时没留下任何计划，于是「确认」这两个字里没有可续接的信息。
 * 现在：近似名检索给出疑似对象；上一轮只提问时服务端把它的意图与编号带进本轮
 * <continuation>；提示词也明确要求“要用户拍板就必须同时给出动作”。
 */
require __DIR__ . '/../bootstrap.php';

function cfAdmin(): int
{
    $_SESSION['user_id'] = 1;
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
    (new Setting())->setMany(['ai_enabled' => '1', 'ai_provider' => 'mock', 'ai_mode' => 'preview',
        'ai_context_minutes' => '60'], 1);
    Setting::flushCache();
    Ai::flushHistoryCache();
    return 1;
}

/** 只表态度的短回答要认出来，带内容的句子不能误认 */
function test_bare_acknowledgement_detection(): void
{
    foreach (['确认', '确认。', '好的', '是的', '对', '可以', '执行吧', 'ok', 'yes', '就这样'] as $yes) {
        assertTrue(Ai::isBareAcknowledgement($yes), "「{$yes}」应当算纯表态");
    }
    foreach (['确认删除客户 CUS-000020', '把电话改成 13800000000', '查一下商机', '不同意，换个号码',
              '', '   ', '确认之前先看看那条线索的备注内容是否也需要一起改'] as $no) {
        assertTrue(!Ai::isBareAcknowledgement($no), "「{$no}」不该被当成纯表态");
    }
}

/** 拼写差一个字母也要能查到：ashmad → Ahmad */
function test_near_name_lookup(): void
{
    $user = cfAdmin();
    // 注意：这里不放一个真叫 “ashmad …” 的行——精确匹配本来就该赢，
    // 那样根本走不到近似分支（那是另一种正确，下面单独测）。
    $id = (int) (new Customer())->create(['name' => 'Ahmad', 'company' => 'Al-Faraq', 'status' => 'active',
        'source_country' => '阿富汗', 'owner_id' => $user]);
    $code = (new Customer())->codeOf((new Customer())->find($id));

    $hits = Ai::fuzzyMatches('ashmad', 3);
    assertTrue($hits !== [], '打错一个字母不该就查无此人');
    $blob = json_encode($hits, JSON_UNESCAPED_UNICODE);
    assertContains($code, $blob, '近似结果要带真实编号：' . textClip($blob, 160));
    assertContains('Ahmad', $blob);

    $digest = Ai::foundDigest('更新客户ashmad的电话号码为024324567891');
    assertContains('疑似', $digest, '要标注这是近似匹配，不是确证：' . textClip($digest, 180));
    assertContains($code, $digest);

    // 太短的词不做模糊（否则全是误伤）
    assertEquals([], Ai::fuzzyMatches('ab', 3));
}

/** 上一轮只提问、这一轮只说“确认”：必须接得上 */
function test_confirmation_continues_the_previous_intent(): void
{
    $user = cfAdmin();
    $id = (int) (new Customer())->create(['name' => 'Ahmad', 'status' => 'active', 'phone' => '+964 700 000 0000',
        'source_country' => '阿富汗', 'owner_id' => $user]);
    $code = (new Customer())->codeOf((new Customer())->find($id));
    $original = '更新客户ashmad的电话号码为024324567891';

    // 第一轮：只提问没出计划（当时的真实形态）
    (new Ai())->create(['user_id' => $user, 'instruction' => $original,
        'reply' => '库内没有叫“ashmad”的客户，最接近的是阿富汗的 ' . $code . '（Ahmad）。请确认是否要更新这位客户的电话？确认后我再执行。',
        'plan_json' => json_encode(['actions' => [], 'reply' => '请确认'], JSON_UNESCAPED_UNICODE),
        'status' => 'invalid', 'provider' => 'mock', 'model' => 'triphase-mock']);
    Ai::flushHistoryCache();

    $cf = Ai::carryForwardIntent($user);
    assertTrue(is_array($cf), '上一轮在等人表态，就该能被续接');
    assertEquals($original, (string) $cf['instruction']);
    assertContains($code, implode(',', (array) $cf['codes']), '编号要从它当时的回答里捞出来');

    $res = Ai::complete('确认');
    assertTrue($res['ok'], (string) ($res['error'] ?? ''));
    assertEquals(1, count($res['actions']), '一句“确认”应当产出一个动作：' . json_encode($res['actions'], JSON_UNESCAPED_UNICODE));
    assertEquals('update_customer', (string) $res['actions'][0]['tool']);
    assertEquals($code, (string) $res['actions'][0]['args']['customer_id']);
    assertContains('024324567891', json_encode($res['actions'], JSON_UNESCAPED_UNICODE), '电话值要沿用上一轮给的');
    assertContains($code, (string) $res['reply'], '回执要讲清是对哪条操作');

    // 续接说明要真的进了提示消息
    $msgs = Ai::messages('确认', $user, $cf);
    assertContains('<continuation>', (string) $msgs[1]['content']);
    assertContains($original, (string) $msgs[1]['content']);

    // 落库要改对人
    $checked = Ai::validatePlan($res['actions'], $user);
    assertEquals([], $checked['errors'], json_encode($checked['errors'], JSON_UNESCAPED_UNICODE));
    Ai::execute($checked['actions'], $user, 999);
    $row = (new Customer())->find($id);
    assertEquals('024324567891', (string) $row['phone'], '电话应当被更新到 Ahmad 身上');
}

/** 已经有待确认计划时不要重复造一个；上下文关掉时也不许续接 */
function test_carry_forward_respects_its_bounds(): void
{
    $user = cfAdmin();
    $code = (new Customer())->codeOf((new Customer())->create(['name' => '已有计划', 'status' => 'active', 'owner_id' => $user]));

    // (a) 上一轮留下了 pending 计划 → 该走页面上的「确认执行」，不该再造一遍
    (new Ai())->create(['user_id' => $user, 'instruction' => '把客户 ' . $code . ' 的电话改成 123',
        'reply' => '已生成计划，等你确认执行',
        'plan_json' => json_encode(['actions' => [['tool' => 'update_customer',
            'args' => ['customer_id' => $code, 'phone' => '123'], 'kind' => 'write']]], JSON_UNESCAPED_UNICODE),
        'status' => 'pending', 'provider' => 'mock', 'model' => 'triphase-mock']);
    Ai::flushHistoryCache();
    assertTrue(Ai::carryForwardIntent($user) === null, '已有 pending 计划时不再续接');

    // (b) 上一轮已经把事做完了 → “确认”不该凭空再生成一个动作
    $run = Ai::complete('确认');
    assertEquals(0, count($run['actions']), '没有待表态的上一轮就不该有动作：' . json_encode($run['actions'], JSON_UNESCAPED_UNICODE));

    // (c) 上下文窗口关掉 → 不装记得
    (new Ai())->create(['user_id' => $user, 'instruction' => '更新客户 ' . $code . ' 的备注为空',
        'reply' => '请确认是否要改这条？', 'plan_json' => '{"actions":[]}', 'status' => 'invalid',
        'provider' => 'mock', 'model' => 'triphase-mock']);
    Setting::flushCache();
    Ai::flushHistoryCache();
    assertTrue(is_array(Ai::carryForwardIntent($user)), '开着窗口时能续接');
    (new Setting())->setMany(['ai_context_minutes' => '0'], 1);
    Setting::flushCache();
    Ai::flushHistoryCache();
    assertTrue(Ai::carryForwardIntent($user) === null, '窗口关闭时不许续接');
    cfAdmin();
}

/** 提示词与文档要把这套写清楚 */
function test_prompt_and_docs_cover_continuation(): void
{
    $user = cfAdmin();
    (new Customer())->create(['name' => 'Ahmad', 'status' => 'active', 'source_country' => '阿富汗', 'owner_id' => $user]);
    $prompt = Ai::systemPrompt();
    assertContains('把动作写进 actions', $prompt, '要禁止“只提问不出计划”');
    assertContains('<continuation>', $prompt, '要告诉模型这个块是什么');
    assertContains('严禁再问“你想确认什么”', $prompt);
    assertContains('疑似', Ai::foundDigest('更新客户ashmad的电话号码为024324567891'), '近似匹配要标明身份');

    $docs = AppMap::toText();
    assertContains('carryForwardIntent', $docs, '文档要指到具体方法');
    assertContains('fuzzyMatches', $docs, '近似检索也要有文档');
}

runCase();
