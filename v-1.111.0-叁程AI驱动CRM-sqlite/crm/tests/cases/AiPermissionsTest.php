<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
/**
 * AI 助手的 读 / 改 / 删 权限。
 *
 * The rails these tests guard:
 *   read    — free, no confirmation, and it cannot reach app_settings/users
 *   write   — needs a plan; only the fields named by the model change
 *   delete  — needs plan + confirm:true + reason + a human pressing 确认执行,
 *             and its blast radius is computed and shown BEFORE approval
 *
 * Everything runs on this file's own temp database, so a delete here cannot hurt
 * anybody's real data.
 */
require __DIR__ . '/../bootstrap.php';

function permAdmin(): int
{
    $_SESSION['user_id'] = 1;
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
    return 1;
}

function permCount(string $table, string $where = ''): int
{
    $sql = 'SELECT COUNT(*) FROM ' . $table . ($where ? ' WHERE ' . $where : '');
    return (int) Database::connection()->query($sql)->fetchColumn();
}

function permGone(int $id, string $label): void
{
    $table = ['客户' => 'customers', '线索' => 'leads', '商机' => 'deals', '订单' => 'orders',
              '附件' => 'attachments', 'AI 记录' => 'ai_actions'][$label] ?? '';
    $n = permCount($table, 'id = ' . $id);
    assertTrue($n === 0, "{$label} #{$id} 应该已经不存在");
}

function permAlive(int $id, string $label): void
{
    $table = ['客户' => 'customers', '线索' => 'leads', '商机' => 'deals', '订单' => 'orders',
              '附件' => 'attachments', 'AI 记录' => 'ai_actions'][$label] ?? '';
    assertTrue(permCount($table, 'id = ' . $id) === 1, "{$label} #{$id} 应该还在");
}

/**
 * One account: a customer with a lead, a deal, two orders and one attachment file
 * that really exists on disk. Unique names, so repeated calls do not collide.
 */
function permAccount(int $userId = 1): array
{
    $tag = substr(uniqid(), -6);
    $orders = new Order();

    $custId = (int) (new Customer())->create([
        'name' => '联盛机械' . $tag . ' 采购部', 'company' => '联盛机械' . $tag,
        'email' => 'buy' . $tag . '@liansheng.example', 'status' => 'active',
        'owner_id' => $userId, 'notes' => '做轴承询盘',
    ]);
    $leadId = (int) (new Lead())->create([
        'title' => '深沟球轴承 6205 询盘 ' . $tag, 'company' => '联盛机械' . $tag,
        'contact_name' => '林小姐', 'contact_email' => 'lin' . $tag . '@liansheng.example',
        'customer_id' => $custId,
        'status' => 'new', 'value' => 25000, 'owner_id' => $userId,
    ]);
    $dealId = (int) (new Deal())->create([
        'title' => '联盛 ' . $tag . ' 批量单', 'customer_id' => $custId,
        'value' => 48000, 'stage' => 'open', 'owner_id' => $userId,
    ]);
    $orderId = (int) $orders->create([
        'order_number' => $orders->generateOrderNumber(), 'customer_id' => $custId, 'deal_id' => $dealId,
        'title' => '联盛首柜 ' . $tag, 'amount' => 48000, 'status' => 'pending',
        'payment_status' => 'unpaid', 'owner_id' => $userId,
    ]);
    $orphanId = (int) $orders->create([
        'order_number' => $orders->generateOrderNumber(), 'customer_id' => $custId,
        'title' => '联盛二柜 ' . $tag, 'amount' => 9000, 'status' => 'pending',
        'payment_status' => 'unpaid', 'owner_id' => $userId,
    ]);

    $path = Attachment::uploadDir() . '/perm-' . $tag . '.txt';
    file_put_contents($path, 'probe');
    $attId = (int) (new Attachment())->create([
        'related_type' => 'deal', 'related_id' => $dealId, 'filename' => basename($path),
        'original_name' => '报价单.txt', 'mime_type' => 'text/plain', 'file_size' => 5,
        'uploaded_by' => $userId,
    ]);

    return compact('custId', 'leadId', 'dealId', 'orderId', 'orphanId', 'attId') + ['tag' => $tag, 'path' => $path];
}

// ------------------------------------------------------------------- read

function test_search_returns_real_ids_and_never_secrets(): void
{
    $u = permAdmin();
    $a = permAccount($u);

    $plan = Ai::validatePlan([[
        'tool' => 'search_records',
        'args' => ['q' => $a['tag'], 'tables' => 'customer,deal,order', 'limit' => '10'],
    ]], $u);
    assertEquals(false, $plan['blocked'], implode('；', $plan['errors']));
    assertTrue(!empty($plan['actions'][0]['read']), '搜索被标成只读');

    $before = permCount('leads');
    $run = Ai::execute($plan['actions'], $u);
    assertEquals(1, $run['applied']);
    $rows = $run['results'][0]['rows'] ?? [];
    assertTrue(count($rows) >= 3, '客户/商机/订单都搜到了（实收 ' . count($rows) . ' 条）');
    $found = array_map(static fn($r) => $r['type'] . '#' . $r['id'], $rows);
    assertTrue(in_array('客户#' . $a['custId'], $found, true), '返回真实客户 ID');
    assertTrue(in_array('商机#' . $a['dealId'], $found, true), '返回真实商机 ID');
    assertTrue(array_key_exists('writable', $rows[0]), '每条都带“这人能不能改”的标注');
    assertTrue($rows[0]['writable'], '管理员对自己负责的数据可写');
    assertTrue(array_key_exists('owner', $rows[0]) && $rows[0]['owner'] !== '', '负责人带的是名字，不是又一个裸 ID');
    assertEquals($before, permCount('leads'), '搜索一行数据都不改');

    $blob = json_encode($run, JSON_UNESCAPED_UNICODE) ?: '';
    assertTrue(!str_contains($blob, 'sk-'), '结果里没有密钥');
    assertTrue(!str_contains($blob, 'password_hash'), '结果里没有密码散列');
    assertTrue(!str_contains($blob, 'ai_api_key'), 'app_settings 根本不在可搜索清单里');
    assertTrue(!str_contains($blob, 'users'), 'users 表不在可搜索清单里');
}

function test_search_scope_is_a_whitelist_and_like_is_literal(): void
{
    $u = permAdmin();
    permAccount($u);

    foreach (['app_settings', 'users', 'everything'] as $try) {
        $bad = Ai::validatePlan([['tool' => 'search_records', 'args' => ['q' => 'x', 'tables' => $try]]], $u);
        assertTrue($bad['blocked'], "范围 {$try} 应被拒绝");
        assertContains('不是可搜索的范围', implode('；', $bad['errors']));
    }

    // "%" must search for a percent sign, not for "all rows"
    $pct = Ai::validatePlan([['tool' => 'search_records', 'args' => ['q' => '100%']]], $u);
    $run = Ai::execute($pct['actions'], $u);
    assertEquals(0, count($run['results'][0]['rows'] ?? []), 'LIKE 的通配符被转义了');
}

function test_get_record_reports_fields_relations_and_writability(): void
{
    $u = permAdmin();
    $a = permAccount($u);

    $plan = Ai::validatePlan([['tool' => 'get_record', 'args' => ['type' => 'customer', 'id' => (string) $a['custId']]]], $u);
    $run = Ai::execute($plan['actions'], $u);
    $msg = (string) ($run['results'][0]['message'] ?? '');
    assertContains('客户 CUS-' . sprintf('%06d', $a['custId']), $msg, '详情用编号开头');
    assertContains('商机 1', $msg, '带出关联数量');
    assertContains('订单 2', $msg);
    assertContains('你可操作：是', $msg);
    assertTrue(!str_contains($msg, 'plan_json'), '大字段不会被端出去');

    $missing = Ai::execute(Ai::validatePlan([['tool' => 'get_record', 'args' => ['type' => 'order', 'id' => '999999']]], $u)['actions'], $u);
    assertEquals(0, $missing['applied']);
    assertContains('找不到对应记录', (string) $missing['results'][0]['message']);
}

function test_the_prompt_resolves_a_named_record_to_a_real_id(): void
{
    $u = permAdmin();
    $a = permAccount($u);

    $words = Ai::keywords('把「联盛机械' . $a['tag'] . '」的商机推到报价阶段');
    assertTrue(in_array('联盛机械' . $a['tag'], $words, true), '引号里的公司名被提取：' . implode('/', $words));

    // 用户会怎么称呼这条商机？用商机自己标题里的词，而不是客户名
    $found = Ai::foundDigest('把 联盛 ' . $a['tag'] . ' 批量单 推进到 proposal');
    assertContains('DEAL-' . sprintf('%06d', $a['dealId']), $found, '指令里的说法能定位到真实商机：' . textClip($found, 160));
    assertContains('可操作:是', $found);
    assertTrue(textLength($found) < 1600, '<found> 有上限，不会撑爆提示词');
    assertEquals('', Ai::foundDigest('随便说一句跟数据无关的话'), '对不上就不硬凑');

    $messages = Ai::messages('把 联盛 ' . $a['tag'] . ' 批量单 推到报价');
    assertContains('<found>', $messages[1]['content'], '真实 ID 随消息一起给模型');
}

// ------------------------------------------------------------------ write

function test_update_tools_touch_only_the_fields_given(): void
{
    $u = permAdmin();
    $a = permAccount($u);
    $leadBefore = (new Lead())->find($a['leadId']);

    $plan = Ai::validatePlan([
        ['tool' => 'update_lead', 'args' => ['lead_id' => $a['leadId'], 'status' => 'qualified', 'value' => 31000]],
        ['tool' => 'update_deal', 'args' => ['deal_id' => $a['dealId'], 'stage' => 'proposal', 'close_date' => '2026-12-05']],
        ['tool' => 'update_order', 'args' => ['order_id' => $a['orderId'], 'payment_status' => 'partial', 'notes' => '已收 30%']],
        ['tool' => 'update_customer', 'args' => ['customer_id' => $a['custId'], 'source_country' => 'Germany', 'status' => 'active']],
    ], $u);
    assertEquals(false, $plan['blocked'], implode('；', $plan['errors']));
    assertEquals(4, Ai::execute($plan['actions'], $u)['applied']);

    $lead = (new Lead())->find($a['leadId']);
    assertEquals('qualified', $lead['status']);
    assertEquals(31000.0, (float) $lead['value']);
    assertEquals($leadBefore['contact_name'], $lead['contact_name'], '没传的字段保持原样');

    $deal = (new Deal())->find($a['dealId']);
    assertEquals('proposal', $deal['stage']);
    assertTrue(!empty($deal['stage_proposal_at']), '阶段时间同步写入，和看板拖拽一致');
    assertEquals('2026-12-05', $deal['close_date']);

    assertContains('已收 30%', (string) (new Order())->find($a['orderId'])['notes']);
    assertEquals('Germany', (new Customer())->find($a['custId'])['source_country']);

    $none = Ai::validatePlan([['tool' => 'update_lead', 'args' => ['lead_id' => $a['leadId']]]], $u);
    assertTrue($none['blocked'], '空更新不算成功');
    assertContains('至少要有一个要修改的字段', implode('；', $none['errors']));
}

function test_bad_values_are_refused_before_anything_lands(): void
{
    $u = permAdmin();
    $a = permAccount($u);

    $plan = Ai::validatePlan([
        ['tool' => 'update_lead', 'args' => ['lead_id' => $a['leadId'], 'status' => 'won']],
        ['tool' => 'update_order', 'args' => ['order_id' => $a['orderId'], 'amount' => 'aaa']],
        ['tool' => 'update_deal', 'args' => ['deal_id' => 999999, 'stage' => 'open']],
        ['tool' => 'update_lead', 'args' => ['lead_id' => $a['leadId'], 'colour' => 'red']],
    ], $u);
    assertTrue($plan['blocked']);
    $errors = implode('；', $plan['errors']);
    assertContains('不在可选值', $errors);
    assertContains('必须是数字', $errors);
    assertContains('找不到对应记录', $errors);
    assertContains('不接受参数 colour', $errors);
    assertEquals('new', (new Lead())->find($a['leadId'])['status'], '被拦的计划一行都没写');
}

// ----------------------------------------------------------------- delete

function test_deletes_need_confirm_and_a_reason(): void
{
    $u = permAdmin();
    $a = permAccount($u);

    $noConfirm = Ai::validatePlan([['tool' => 'delete_lead', 'args' => ['lead_id' => $a['leadId']]]], $u);
    assertTrue($noConfirm['blocked'], '不 confirm 不算删除意图');
    assertContains('必须显式写 true', implode('；', $noConfirm['errors']));

    $noReason = Ai::validatePlan([['tool' => 'delete_lead',
        'args' => ['lead_id' => $a['leadId'], 'confirm' => true]]], $u);
    assertTrue($noReason['blocked'], '不写理由不算删除意图');
    assertContains('删除理由 必填', implode('；', $noReason['errors']));
    permAlive($a['leadId'], '线索');

    $ok = Ai::validatePlan([[
        'tool' => 'delete_lead',
        'args' => ['lead_id' => $a['leadId'], 'confirm' => true, 'reason' => '重复提交，已有另一条'],
    ]], $u);
    assertEquals(false, $ok['blocked'], implode('；', $ok['errors']));
    assertTrue(!empty($ok['actions'][0]['destructive']), '它被标成破坏性操作');

    $run = Ai::execute($ok['actions'], $u);
    assertEquals(1, $run['applied']);
    permGone($a['leadId'], '线索');
    assertContains('理由：重复提交', (string) $run['results'][0]['message'], '理由留在执行结果里');
    // the removed row itself must stay recoverable from the audit, not just summarised
    $snap = (string) $run['results'][0]['snapshot'];
    assertContains('title=', $snap, '快照里是被删那一行');
    assertContains('深沟球轴承', $snap, '连标题都存着');
}

function test_a_delete_shows_its_blast_radius_before_approval(): void
{
    $u = permAdmin();
    $a = permAccount($u);

    $impact = Ai::deleteImpact('delete_customer', ['customer_id' => $a['custId']]);
    assertContains('联盛机械' . $a['tag'], $impact['target'], '写清要删的是哪一条');
    assertEquals(1, $impact['cascade']['线索']);
    assertEquals(1, $impact['cascade']['商机']);
    assertEquals(2, $impact['cascade']['订单']);
    assertEquals(1, $impact['cascade']['附件'], '附件文件数也算进去');
    assertTrue($impact['count'] >= 6, '总影响 = 本体 + 连带，实得 ' . $impact['count']);

    $plan = Ai::validatePlan([[
        'tool' => 'delete_customer',
        'args' => ['customer_id' => $a['custId'], 'confirm' => 'true', 'reason' => '客户注销'],
    ]], $u);
    assertEquals(2, $plan['actions'][0]['impact']['cascade']['订单'], '预览阶段就能看到连带影响');
    permAlive($a['custId'], '客户');
}

function test_deleting_a_customer_takes_children_and_files_with_it(): void
{
    $u = permAdmin();
    $a = permAccount($u);
    assertTrue(file_exists($a['path']), '先确认附件文件存在');

    $plan = Ai::validatePlan([[
        'tool' => 'delete_customer',
        'args' => ['customer_id' => $a['custId'], 'confirm' => true, 'reason' => '客户注销，数据已导出'],
    ]], $u);
    assertEquals(false, $plan['blocked'], implode('；', $plan['errors']));
    $run = Ai::execute($plan['actions'], $u, 7);
    assertEquals(1, $run['applied'], json_encode($run['results']));

    permGone($a['custId'], '客户');
    permGone($a['leadId'], '线索');
    permGone($a['dealId'], '商机');
    permGone($a['orderId'], '订单');
    permGone($a['orphanId'], '订单');
    permGone($a['attId'], '附件');
    assertTrue(!file_exists($a['path']), '磁盘上的附件文件也删掉了');
    assertContains('已导出', (string) $run['results'][0]['message']);
    assertContains('联盛', (string) ($run['results'][0]['snapshot'] ?? ''), '被删内容留了快照，可追责');
}

function test_a_deal_delete_keeps_its_orders_but_unlinks_them(): void
{
    $u = permAdmin();
    $a = permAccount($u);

    $plan = Ai::validatePlan([[
        'tool' => 'delete_deal',
        'args' => ['deal_id' => $a['dealId'], 'confirm' => true, 'reason' => '商机作废'],
    ]], $u);
    $run = Ai::execute($plan['actions'], $u);
    assertEquals(1, $run['applied']);
    permGone($a['dealId'], '商机');
    permAlive($a['orderId'], '订单');
    assertEquals(null, (new Order())->find($a['orderId'])['deal_id'], '只是解除关联');
    assertContains('解除订单关联 1', (string) $run['results'][0]['message']);
    permGone($a['attId'], '附件');
}

function test_sales_cannot_delete_a_colleagues_record(): void
{
    permAdmin();
    $a = permAccount(1);
    $rep = aiUserForPermissions();
    $_SESSION['user_id'] = $rep;
    $_SESSION['user'] = ['id' => $rep, 'role' => 'sales'];

    $plan = Ai::validatePlan([[
        'tool' => 'delete_customer',
        'args' => ['customer_id' => $a['custId'], 'confirm' => true, 'reason' => '以为是我的'],
    ]], $rep);
    assertTrue($plan['blocked'], '别人负责的客户删不掉');
    assertContains('属于其他同事负责的数据', implode('；', $plan['errors']));
    permAlive($a['custId'], '客户');

    // same story for a plain update
    $write = Ai::validatePlan([['tool' => 'update_customer', 'args' => ['customer_id' => $a['custId'], 'name' => '改名']]], $rep);
    assertTrue($write['blocked'], '改也一样要权限');
    permAdmin();
}

function test_the_master_switch_turns_the_whole_capability_off(): void
{
    $u = permAdmin();
    $a = permAccount($u);
    (new Setting())->setMany(['ai_allow_delete' => '0'], $u);
    Setting::flushCache();
    assertTrue(!Ai::deletesAllowed(), '开关读到的是关闭');

    $plan = Ai::validatePlan([[
        'tool' => 'delete_order',
        'args' => ['order_id' => $a['orderId'], 'confirm' => true, 'reason' => '测试'],
    ]], $u);
    assertTrue($plan['blocked'], '总开关关掉后删除被拒绝');
    assertContains('删除权限已关闭', implode('；', $plan['errors']));
    permAlive($a['orderId'], '订单');

    $write = Ai::validatePlan([['tool' => 'update_order', 'args' => ['order_id' => $a['orderId'], 'title' => '改标题']]], $u);
    assertEquals(false, $write['blocked'], '关闭删除不影响查询与修改');
    $read = Ai::validatePlan([['tool' => 'search_records', 'args' => ['q' => '联盛']]], $u);
    assertEquals(false, $read['blocked'], '也不影响搜索');

    (new Setting())->setMany(['ai_allow_delete' => '1'], $u);
    Setting::flushCache();
    assertTrue(Ai::deletesAllowed(), '开关可以重新打开');
}

function test_the_assistant_can_delete_its_own_request_records(): void
{
    $admin = permAdmin();
    $rep = aiUserForPermissions();
    $cfg = AiClient::config();
    $mine = (int) (new Ai())->record($rep, '帮我删掉测试线索', ['actions' => []], $cfg);
    $theirs = (int) (new Ai())->record($admin, '管理员自己的记录', ['actions' => []], $cfg);

    $_SESSION['user_id'] = $rep;
    $_SESSION['user'] = ['id' => $rep, 'role' => 'sales'];

    $foreign = Ai::validatePlan([[
        'tool' => 'delete_ai_request', 'args' => ['action_id' => $theirs, 'confirm' => true, 'reason' => '清历史'],
    ]], $rep);
    assertTrue($foreign['blocked'], '同事的 AI 记录删不掉');
    assertContains('你只能删自己的', implode('；', $foreign['errors']));

    $own = Ai::validatePlan([[
        'tool' => 'delete_ai_request', 'args' => ['action_id' => $mine, 'confirm' => true, 'reason' => '清自己的历史'],
    ]], $rep);
    assertEquals(false, $own['blocked'], implode('；', $own['errors']));

    // the row being executed right now may not erase itself — the audit chain stays intact
    $self = Ai::execute($own['actions'], $rep, $mine);
    assertEquals(0, $self['applied']);
    assertContains('不能删除正在执行的这条', (string) $self['results'][0]['message']);
    permAlive($mine, 'AI 记录');

    $other = (int) (new Ai())->record($rep, '另一条待清理的记录', ['actions' => []], $cfg);
    $p2 = Ai::validatePlan([[
        'tool' => 'delete_ai_request', 'args' => ['action_id' => $other, 'confirm' => true, 'reason' => '清理'],
    ]], $rep);
    assertEquals(1, Ai::execute($p2['actions'], $rep)['applied']);
    permGone($other, 'AI 记录');

    permAdmin();
    $pAdmin = Ai::validatePlan([[
        'tool' => 'delete_ai_request', 'args' => ['action_id' => $theirs, 'confirm' => true, 'reason' => '管理员收回记录'],
    ]], $admin);
    assertEquals(false, $pAdmin['blocked'], '管理员能删任何人的 AI 记录');
    assertEquals(1, Ai::execute($pAdmin['actions'], $admin)['applied']);
    permGone($theirs, 'AI 记录');
}

function test_destructive_tools_are_never_auto_executed(): void
{
    $u = permAdmin();
    assertTrue(Ai::hasDestructive([
        ['tool' => 'create_lead', 'args' => ['title' => '混合计划']],
        ['tool' => 'delete_lead', 'args' => ['lead_id' => 1, 'confirm' => true, 'reason' => 'x']],
    ]), '控制器靠这个判断停住自动执行');
    assertTrue(!Ai::hasDestructive([['tool' => 'search_records', 'args' => ['q' => 'x']]]), '纯查询不是破坏性');
    assertTrue(!Ai::hasDestructive([['tool' => 'update_lead', 'args' => ['lead_id' => 1, 'notes' => 'x']]]), '修改也不是');

    $tools = Ai::tools();
    assertEquals(6, count(Ai::destructiveTools()), '删除类工具就 6 个（线索/商机/订单/客户/商品/AI 记录）');
    foreach (Ai::destructiveTools() as $t) {
        assertTrue(in_array('confirm', array_keys($tools[$t]['params']), true), "{$t} 必须带 confirm");
        assertTrue(in_array('reason', array_keys($tools[$t]['params']), true), "{$t} 必须带 reason");
        assertEquals('delete', $tools[$t]['kind']);
    }
}

function test_the_tool_table_is_the_single_source_of_truth(): void
{
    $tools = Ai::tools();
    // 工具表是唯一真相：数量变了，README/流程说明/CHANGELOG 都要跟着改（这条断言就是干这个的）
    assertEquals(24, count($tools), '工具数量变了要同步文档与提示词');
    $kinds = array_count_values(array_map(static fn($t) => $t['kind'], $tools));
    assertEquals(3, $kinds['read']);
    assertEquals(15, $kinds['write']);
    assertEquals(6, $kinds['delete']);

    $prompt = Ai::systemPrompt();
    foreach (array_keys($tools) as $name) {
        assertContains($name, $prompt, "提示词里应能看到 {$name}");
    }
    assertContains('search_records {read', $prompt, '工具按紧凑写法列出');
    assertContains('confirm:bool_yes!', $prompt, '必填标记也在');
    assertContains('删除', $prompt);
    assertContains('真实 ID', $prompt);
    // 上限是回归闸门而不是目标值：能力增加时提示词会长，但不许无节制地长
    assertTrue(textLength($prompt) < 7400, '系统提示仍受长度约束（实测 ' . textLength($prompt) . ' 字）');

    // every tool is reachable: it has a runner, or it is refused as unimplemented
    foreach (array_keys($tools) as $name) {
        $spec = $tools[$name];
        $args = [];
        foreach ($spec['params'] as $key => $p) {
            if (empty($p['required'])) {
                continue;
            }
            $args[$key] = match ($p['type']) {
                'bool_yes' => true,
                default => '1',
            };
        }
        foreach ($spec['params'] as $key => $p) {
            if (($p['type'] ?? '') === 'table_list') {
                $args[$key] = 'lead';
            }
        }
        $outcome = Ai::execute([['tool' => $name, 'args' => $args, 'errors' => []]], $u ?? 1)['results'][0];
        assertTrue(isset($outcome['message']), "工具 {$name} 有执行分支（不是一句「未实现」）");
        assertTrue(!str_contains((string) $outcome['message'], '未实现的工具'),
            $name . ' 缺少 runTool 分支：' . $outcome['message']);
    }
}

function aiUserForPermissions(): int
{
    return (int) (new User())->register('销售丙', 'perm' . substr(uniqid(), -5) . '@x.example', '销售丙', 'sales');
}

// ------------------------------------------------------------------- HTTP

/**
 * The UI part of the promise: 确认执行 is what makes a delete happen, and the
 * history page can drop its own records. Covered in HttpSmokeTest for the plan
 * path; here we only assert the wiring exists.
 */
function test_delete_routes_exist_for_humans_too(): void
{
    $paths = [];
    foreach (AppMap::routes() as $r) {
        $paths[] = $r['method'] . ' ' . $r['path'];
    }
    assertTrue(in_array('POST /ai/history/{id}/delete', $paths, true),
        '人也能删 AI 记录（路由缺失：' . implode(' ', array_slice($paths, -6)) . '）');
    foreach (['POST /ai/plan', 'POST /ai/apply', 'POST /ai/cancel', 'GET /ai/history'] as $need) {
        assertTrue(in_array($need, $paths, true), "缺少 {$need}");
    }
}

runCase();
