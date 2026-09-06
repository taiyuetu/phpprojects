<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
/**
 * 字段引擎：AI 能不能写某个字段，取决于数据库里有没有这一列，不取决于我有没有手写进清单。
 *
 * 触发这一版的真实报障：「更新线索 LEAD-000016 的来源国家为伊拉克」被答复
 * “线索没有来源国家字段”——而 leads.source_country 一直都在，是我手写的参数清单漏了它。
 * 所以这些 update / create 工具的参数现在由 Ai::fieldsFor() 从表结构生成，
 * 提示词、参数校验、真正落库三处共用这一份。
 */
require __DIR__ . '/../bootstrap.php';

function fieldsAdmin(): int
{
    $_SESSION['user_id'] = 1;
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
    (new Setting())->setMany(['ai_enabled' => '1', 'ai_provider' => 'mock', 'ai_mode' => 'preview',
        'ai_allow_delete' => '1'], 1);
    Setting::flushCache();
    return 1;
}

function asSales(int $uid = 2): void
{
    $_SESSION['user_id'] = $uid;
    $_SESSION['user'] = ['id' => $uid, 'role' => 'sales'];
}

/** 跑一个工具，返回单条结果（校验失败时把错误抛给断言） */
function runToolOnce(string $tool, array $args, int $userId): array
{
    $checked = Ai::validatePlan([['tool' => $tool, 'args' => $args]], $userId);
    if (!empty($checked['errors'])) {
        return ['ok' => false, 'message' => implode('；', $checked['errors'])];
    }
    $run = Ai::execute($checked['actions'], $userId);
    return (array) ($run['results'][0] ?? ['ok' => false, 'message' => '没有结果']);
}

/**
 * 最重要的一条：每张表的列，要么能被 AI 写，要么在“系统自维护”名单里被明确排除。
 * 以后加列忘了同步也不会再出现“这个字段不存在”这种假拒绝。
 */
function test_writable_fields_cover_the_schema_exactly(): void
{
    $user = fieldsAdmin();
    $cases = ['leads', 'customers', 'deals', 'orders', 'follow_ups'];
    foreach ($cases as $table) {
        $columns = array_map(static fn($c) => (string) $c['name'], Schema::columns($table));
        $params = array_keys(Ai::fieldsFor($table));
        $writable = array_map(static fn($p) => $p === 'owner' ? 'owner_id' : $p, $params);
        $protected = array_merge(Ai::PROTECTED_COLUMNS['*'], Ai::PROTECTED_COLUMNS[$table] ?? []);
        $missing = array_values(array_diff($columns, $writable, $protected));
        assertEquals([], $missing, "{$table} 有列既不可写也没被明确排除：" . json_encode($missing, JSON_UNESCAPED_UNICODE));
        $bogus = array_values(array_diff($writable, $columns));
        assertEquals([], $bogus, "{$table} 的参数指向不存在的列：" . json_encode($bogus, JSON_UNESCAPED_UNICODE));
    }
    // 数量级也要钉住：线索 22 个可写字段，不再是一版里手写的 11 个
    assertTrue(count(Ai::fieldsFor('leads')) >= 21, '线索可写字段数：' . count(Ai::fieldsFor('leads')));
    assertContains('source_country', implode(',', array_keys(Ai::fieldsFor('leads'))), '线索必须能写来源国家');
    assertContains('source_city', implode(',', array_keys(Ai::fieldsFor('customers'))));
    assertContains('wechat', implode(',', array_keys(Ai::fieldsFor('customers'))));
    assertContains('shipping_address', implode(',', array_keys(Ai::fieldsFor('orders'))));
}

/** 报障原句：把某条线索的来源国家改掉 */
function test_the_reported_instruction_actually_works(): void
{
    $user = fieldsAdmin();
    $leadId = (int) (new Lead())->create(['title' => '拉合特轴承询盘', 'status' => 'new',
        'source_country' => '印度', 'owner_id' => $user]);
    $code = (new Lead())->codeOf((new Lead())->find($leadId));

    $res = runToolOnce('update_lead', ['lead_id' => $code, 'source_country' => '伊拉克',
        'source_city' => '埃尔比勒', 'source' => '展会'], $user);
    assertTrue((bool) ($res['ok'] ?? false), '更新线索来源国家应当成功：' . (string) ($res['message'] ?? ''));
    $row = (new Lead())->find($leadId);
    assertEquals('伊拉克', (string) $row['source_country']);
    assertEquals('埃尔比勒', (string) $row['source_city']);
    assertEquals('展会', (string) $row['source']);
    assertContains('来源国家', (string) $res['message'], '回执要写中文名，不要甩给用户一个 source_country');
    assertContains('标题', (string) (Ai::columnLabels()['title'] ?? ''), '列名中文表要有“标题”');
}

/** 只改给出来的字段；空串是真的清空；必填列清不得 */
function test_partial_update_clearing_and_not_null_guard(): void
{
    $user = fieldsAdmin();
    $cid = (int) (new Customer())->create(['name' => '清空测试', 'company' => '老公司名', 'status' => 'active',
        'phone' => '+964 770 111 2222', 'notes' => '原来的备注', 'source_country' => '伊拉克', 'owner_id' => $user]);
    $code = (new Customer())->codeOf((new Customer())->find($cid));

    runToolOnce('update_customer', ['customer_id' => $code, 'notes' => ''], $user);
    $row = (new Customer())->find($cid);
    assertTrue($row['notes'] === null || $row['notes'] === '', '传空串应当把备注清空，实得：' . var_export($row['notes'], true));
    assertEquals('老公司名', (string) $row['company'], '没给的字段绝不能被动到');
    assertEquals('+964 770 111 2222', (string) $row['phone']);

    $bad = runToolOnce('update_customer', ['customer_id' => $code, 'name' => ''], $user);
    assertTrue(!($bad['ok'] ?? false), 'name 是 NOT NULL 无默认值，不该允许清空');
    assertContains('必填列', (string) $bad['message']);
    assertEquals('清空测试', (string) ((new Customer())->find($cid)['name'] ?? ''), '被拒的计划不能留下半改状态');
}

/** 编号、单号、时间戳这类列必须由系统说话 */
function test_system_columns_stay_out_of_reach(): void
{
    $user = fieldsAdmin();
    $lid = (int) (new Lead())->create(['title' => '编号不可改', 'status' => 'new', 'owner_id' => $user]);
    $code = (new Lead())->codeOf((new Lead())->find($lid));

    foreach (['public_code', 'created_at', 'updated_at', 'lost_at'] as $column) {
        $res = runToolOnce('update_lead', ['lead_id' => $code, $column => '2020-01-01'], $user);
        assertTrue(!($res['ok'] ?? false), "系统列 {$column} 不该能被 AI 直写");
        assertContains('不接受参数', (string) $res['message']);
    }
    $row = (new Lead())->find($lid);
    assertEquals($code, (string) $row['public_code'], '编号一旦可变，用户复制来的引用就会指向别的记录');
    assertTrue($row['lost_at'] === null || $row['lost_at'] === '', 'lost_at 只能由状态变化带出');

    $oid = (int) (new Order())->create(['order_number' => (new Order())->generateOrderNumber(),
        'customer_id' => (int) (new Customer())->create(['name' => '单号测试', 'status' => 'active', 'owner_id' => $user]),
        'title' => '单号测试', 'amount' => 100, 'status' => 'pending', 'payment_status' => 'unpaid', 'owner_id' => $user]);
    $num = (string) (new Order())->find($oid)['order_number'];
    $res = runToolOnce('update_order', ['order_id' => (string) $num, 'order_number' => 'ORD-9999-999'], $user);
    assertTrue(!($res['ok'] ?? false), '单号不可改');
    assertEquals($num, (string) (new Order())->find($oid)['order_number']);
}

/** 布尔/金额/日期/枚举的换算，以及状态与归档自动带出的时间戳 */
function test_value_coercion_and_derived_timestamps(): void
{
    $user = fieldsAdmin();
    $lid = (int) (new Lead())->create(['title' => '换算测试', 'status' => 'new', 'owner_id' => $user]);
    $code = (new Lead())->codeOf((new Lead())->find($lid));
    runToolOnce('update_lead', ['lead_id' => $code, 'first_purchase_from_china' => true,
        'has_import_capability' => '否', 'value' => '12345.678'], $user);
    $row = (new Lead())->find($lid);
    assertEquals('1', (string) $row['first_purchase_from_china'], 'true 要落成 1');
    assertEquals('0', (string) $row['has_import_capability'], '「否」要落成 0');
    assertEquals('12345.68', (string) $row['value'], '金额保留两位');

    $bad = runToolOnce('update_lead', ['lead_id' => $code, 'has_import_capability' => '大概吧'], $user);
    assertTrue(!($bad['ok'] ?? false), '布尔列收到不像样的值必须拒绝');
    $bad = runToolOnce('update_lead', ['lead_id' => $code, 'status' => 'done'], $user);
    assertTrue(!($bad['ok'] ?? false), '状态取值以数据库 CHECK 为准');
    $bad = runToolOnce('update_lead', ['lead_id' => $code, 'lost_reason' => '随便写的'], $user);
    assertTrue(!($bad['ok'] ?? false), '流失原因的可选值在 PHP 里，也要被当枚举校验');

    $did = (int) (new Deal())->create(['title' => '归档测试', 'customer_id' => (int) (new Customer())->create(
        ['name' => '归档客户', 'status' => 'active', 'owner_id' => $user]), 'stage' => 'open', 'value' => 500, 'owner_id' => $user]);
    $dealCode = (new Deal())->codeOf((new Deal())->find($did));
    runToolOnce('update_deal', ['deal_id' => $dealCode, 'stage' => 'negotiation', 'close_date' => '下周五',
        'archived' => true], $user);
    $deal = (new Deal())->find($did);
    assertEquals('negotiation', (string) $deal['stage']);
    assertTrue((string) $deal['stage_negotiation_at'] !== '', '改阶段必须顺手写阶段时间（与看板拖拽一致）');
    assertTrue((string) $deal['archived_at'] !== '', '归档要带归档时间');
    assertTrue(strtotime((string) $deal['close_date']) !== false, '相对日期要换算成 YYYY-MM-DD');

    // 从流失改回来：与 Lead::reactivate 同一套语义
    runToolOnce('update_lead', ['lead_id' => $code, 'status' => 'lost', 'lost_reason' => 'no_response'], $user);
    assertTrue((string) ((new Lead())->find($lid)['lost_at'] ?? '') !== '', '标记流失应写 lost_at');
    runToolOnce('update_lead', ['lead_id' => $code, 'status' => 'contacted'], $user);
    $after = (new Lead())->find($lid);
    assertTrue(($after['lost_at'] === null || $after['lost_at'] === ''), '改回非流失应清掉 lost_at');
}

/** 设置：读要遮密钥，写要走同一套校验，而且只有管理员能改 */
function test_settings_are_readable_and_writable_within_rules(): void
{
    $user = fieldsAdmin();
    $read = runToolOnce('get_settings', ['group' => 'ai'], $user);
    assertTrue((bool) ($read['ok'] ?? false), (string) ($read['message'] ?? ''));
    assertContains('ai_timeout', (string) $read['message']);
    assertTrue(!str_contains((string) $read['message'], 'sk-'), '读设置不能把密钥带出来');

    $write = runToolOnce('update_setting', ['name' => 'ai_timeout', 'value' => '90'], $user);
    assertTrue((bool) ($write['ok'] ?? false), '管理员改超时应成功：' . (string) ($write['message'] ?? ''));
    Setting::flushCache();
    assertEquals('90', (string) Setting::get('ai_timeout', ''));

    $bad = runToolOnce('update_setting', ['name' => 'ai_timeout', 'value' => 'seven'], $user);
    assertTrue(!($bad['ok'] ?? false), '非法值要按 Setting 的规则拒绝');
    $bad = runToolOnce('update_setting', ['name' => 'ai_api_key', 'value' => 'sk-hacked'], $user);
    assertTrue(!($bad['ok'] ?? false), '密钥列不允许通过 AI 修改');
    assertTrue(Setting::get('ai_api_key') !== 'sk-hacked' || Setting::isSecret('ai_api_key'), '绝不能写进去');

    asSales(2);
    $bad = runToolOnce('update_setting', ['name' => 'ai_timeout', 'value' => '45'], 2);
    assertTrue(!($bad['ok'] ?? false), '普通账号不能改系统设置');
    assertContains('admin', (string) $bad['message']);
    fieldsAdmin();
}

/** 明细整单替换：金额与 subtotal 由系统算 */
function test_order_items_replace_and_recompute(): void
{
    $user = fieldsAdmin();
    $cid = (int) (new Customer())->create(['name' => '明细客户', 'status' => 'active', 'owner_id' => $user]);
    // v1.11 起明细必须引用商品库里的商品：先建两个商品，再用编号开单
    $bearing = (int) (new Product())->create(['name' => '6206 深沟球轴承', 'sku' => 'BRG-6206',
        'unit' => '个', 'price' => 3.5, 'status' => 'active', 'owner_id' => $user]);
    $bearingCode = (new Product())->codeOf((new Product())->find($bearing));
    $puller = (int) (new Product())->create(['name' => '轴承拉马', 'sku' => 'TOOL-2',
        'unit' => '件', 'price' => 480, 'status' => 'active', 'owner_id' => $user]);
    $pullerCode = (new Product())->codeOf((new Product())->find($puller));
    $oid = (int) (new Order())->create(['order_number' => (new Order())->generateOrderNumber(),
        'customer_id' => $cid, 'title' => '明细订单', 'amount' => 1, 'status' => 'pending',
        'payment_status' => 'unpaid', 'owner_id' => $user]);
    $num = (string) (new Order())->find($oid)['order_number'];

    $res = runToolOnce('set_order_items', ['order_id' => $num, 'items' => [
        ['product_name' => '6206 深沟球轴承', 'quantity' => 200, 'unit_price' => 3.5, 'unit' => '个'],
        ['product_name' => '轴承拉马', 'quantity' => 2, 'unit_price' => 480.00, 'sku' => 'TOOL-2'],
    ]], $user);
    assertTrue((bool) ($res['ok'] ?? false), '明细替换应成功：' . (string) ($res['message'] ?? ''));
    assertEquals(1660.0, round((float) (new Order())->find($oid)['amount'], 2), '订单金额=200×3.5+2×480');
    $items = (new OrderItem())->byOrder($oid);
    assertEquals(2, count($items));
    assertEquals('件', (string) $items[1]['unit'], '没给单位时沿用商品库里的单位');
    assertEquals($puller, (int) $items[1]['product_id'], '明细要链到商品库那条商品');
    assertEquals('轴承拉马', (string) $items[1]['product_name'], '名称按成交快照写入（商品以后改名不影响这一单）');
    // 只给 SKU、不给单价：单价按商品库现行价带出
    $res2 = runToolOnce('set_order_items', ['order_id' => $num, 'items' => [
        ['sku' => 'BRG-6206', 'quantity' => 100]]], $user);
    assertTrue((bool) ($res2['ok'] ?? false), '按 SKU 也能引用商品：' . (string) ($res2['message'] ?? ''));
    assertEquals(350.0, round((float) (new Order())->find($oid)['amount'], 2), '单价由商品库带出：' . (string) ($res2['message'] ?? ''));
    // 商品库里没有的名字必须被拒（这条约束与人工表单同源）
    $bad3 = runToolOnce('set_order_items', ['order_id' => $num, 'items' => [
        ['product_name' => '手挨的怪名字', 'quantity' => 1, 'unit_price' => 1]]], $user);
    assertTrue(!($bad3['ok'] ?? false), '自由文本商品名必须被拒');
    assertContains('不在商品库里', (string) $bad3['message']);
    assertEquals('700.00', number_format((float) $items[0]['subtotal'], 2));

    $bad = runToolOnce('set_order_items', ['order_id' => $num, 'items' => [
        ['product_name' => '乱来行', 'quantity' => 1, 'unit_price' => 1, 'subtotal' => 999999]]], $user);
    assertTrue(!($bad['ok'] ?? false), 'subtotal 由系统算，模型传了要拒');
    $bad = runToolOnce('set_order_items', ['order_id' => $num, 'items' => [
        ['product_name' => '单位错', 'quantity' => 1, 'unit_price' => 1, 'unit' => '加仑']]], $user);
    assertTrue(!($bad['ok'] ?? false), '单位必须在 OrderItem::unitOptions() 里');

    $res = runToolOnce('set_order_items', ['order_id' => $num, 'items' => []], $user);
    assertTrue((bool) ($res['ok'] ?? false), '空数组表示清空明细，与页面上删空一致：' . (string) ($res['message'] ?? ''));
    assertEquals(0, count((new OrderItem())->byOrder($oid)));
    assertEquals('0.00', number_format((float) (new Order())->find($oid)['amount'], 2));
}

/** 负责人与关联关系：能写姓名，但只有管理员能把数据交给别人 */
function test_owner_and_link_fields_follow_their_rules(): void
{
    $user = fieldsAdmin();
    $other = (int) (new User())->register('验收同事', 'check.' . substr(md5((string) microtime(true)), 0, 8) . '@example.com', 'pw12345678', 'sales');
    $lid = (int) (new Lead())->create(['title' => '转手测试', 'status' => 'new', 'owner_id' => $user]);
    $code = (new Lead())->codeOf((new Lead())->find($lid));

    $res = runToolOnce('update_lead', ['lead_id' => $code, 'owner' => '验收同事'], $user);
    assertTrue((bool) ($res['ok'] ?? false), '管理员按姓名指派负责人应成功：' . (string) ($res['message'] ?? ''));
    assertEquals((string) $other, (string) (int) (new Lead())->find($lid)['owner_id']);

    $bad = runToolOnce('update_lead', ['lead_id' => $code, 'owner' => '不存在的人'], $user);
    assertTrue(!($bad['ok'] ?? false), '姓名找不到账号必须拒绝，并把候选名单给出来');
    assertContains('验收同事', (string) $bad['message'], '报错要能用：直接列出可选姓名');

    // 换个真·普通账号：把自己当操作者，指派对象另一个人
    $self = (int) (new User())->register('普通销售甲', 'sales.' . substr(md5('a' . microtime()), 0, 8) . '@example.com', 'pw12345678', 'sales');
    asSales($self);
    $mine = (int) (new Lead())->create(['title' => '销售自有线索', 'status' => 'new', 'owner_id' => $self]);
    $bad = runToolOnce('update_lead', ['lead_id' => (string) $mine, 'owner' => '验收同事'], $self);
    assertTrue(!($bad['ok'] ?? false), '普通账号不能把数据指派给同事：' . (string) $bad['message']);
    $okSelf = runToolOnce('update_lead', ['lead_id' => (string) $mine, 'notes' => '自己写的'], $self);
    assertTrue((bool) ($okSelf['ok'] ?? false), '但改自己的线索内容应当可以：' . (string) ($okSelf['message'] ?? ''));
    fieldsAdmin();

    // 关联字段收编号，也收“留空取消关联”
    $cid = (int) (new Customer())->create(['name' => '关联测试', 'status' => 'active', 'owner_id' => $user]);
    $custCode = (new Customer())->codeOf((new Customer())->find($cid));
    runToolOnce('update_lead', ['lead_id' => $code, 'customer_id' => $custCode], $user);
    assertEquals((string) $cid, (string) (int) (new Lead())->find($lid)['customer_id']);
    runToolOnce('update_lead', ['lead_id' => $code, 'customer_id' => ''], $user);
    assertTrue(((new Lead())->find($lid)['customer_id'] === null) || ((int) (new Lead())->find($lid)['customer_id'] === 0),
        '留空即取消关联');
}

/** 跟进记录也要能改（上一版只能加不能改），并且 get_record 能看所有类型 */
function test_follow_ups_are_readable_and_editable(): void
{
    $user = fieldsAdmin();
    $cid = (int) (new Customer())->create(['name' => '跟进可改', 'status' => 'active', 'owner_id' => $user]);
    $custCode = (new Customer())->codeOf((new Customer())->find($cid));
    $add = runToolOnce('add_follow_up', ['customer_id' => $custCode, 'title' => '首次比价',
        'type' => 'price_comparison', 'description' => '客户嫌贵', 'next_date' => '明天'], $user);
    assertTrue((bool) ($add['ok'] ?? false), (string) ($add['message'] ?? ''));
    $fuId = (int) $add['id'];
    assertTrue((int) (new FollowUp())->find($fuId)['user_id'] === $user, '跟进人必须是当前账号');

    $upd = runToolOnce('update_follow_up', ['follow_up_id' => (string) $fuId, 'description' => '已报价，等回复',
        'next_action' => '周四电话确认'], $user);
    assertTrue((bool) ($upd['ok'] ?? false), '改跟进应成功：' . (string) ($upd['message'] ?? ''));
    $row = (new FollowUp())->find($fuId);
    assertEquals('已报价，等回复', (string) $row['description']);
    assertEquals('首次比价', (string) $row['title'], '没给的字段不能变');
    assertContains('描述', (string) $upd['message']);

    $detail = runToolOnce('get_record', ['type' => 'follow_up', 'id' => (string) $fuId], $user);
    assertTrue((bool) ($detail['ok'] ?? false), (string) ($detail['message'] ?? ''));
    assertContains('周四电话确认', (string) $detail['message'], '详情要能看到刚写进去的值');

    $bad = runToolOnce('update_follow_up', ['follow_up_id' => '999999'], $user);
    assertTrue(!($bad['ok'] ?? false), '不存在的跟进 ID 要拒（至少要有一个要改的字段）');
}

/** 三处同源：提示词、校验、落库用的必须是同一份字段清单 */
function test_prompt_validation_and_write_share_one_field_list(): void
{
    $user = fieldsAdmin();
    $prompt = Ai::systemPrompt();
    $cases = ['update_lead' => ['leads', false], 'update_customer' => ['customers', false],
              'update_deal' => ['deals', false], 'update_order' => ['orders', false],
              'update_follow_up' => ['follow_ups', false], 'create_lead' => ['leads', true],
              'create_customer' => ['customers', true], 'create_deal' => ['deals', true],
              'add_follow_up' => ['follow_ups', true]];
    foreach ($cases as $tool => [$table, $forCreate]) {
        $line = '';
        foreach (explode("\n", $prompt) as $l) {
            if (str_starts_with($l, $tool . ' {')) {
                $line = $l;
            }
        }
        assertTrue($line !== '', "提示词里应有 {$tool} 那一行");
        foreach (array_keys(Ai::fieldsFor($table, $forCreate)) as $param) {
            assertContains($param, $line, "{$tool} 的 {$param} 必须出现在提示词里（否则模型不知道该参数存在）");
        }
    }
    // 文档（/help、/help/context）也生成自同一处
    $docs = AppMap::toText();
    assertContains('source_country', $docs, '数据字典里要能找到来源国家');
    assertContains('Ai::fieldsFor', AppMap::toText(), '说明与提示词都要写清字段清单来自表结构');
    assertContains('可写字段', AppMap::toText());
}

runCase();
