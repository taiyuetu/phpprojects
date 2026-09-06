<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
/**
 * 商品模型（主数据）与“明细必须从商品库里选”这条约束。
 *
 * 治的是这个乱象：同一个商品被三个人写成 “6206 轴承 / 深沟球轴承6206 / bearing 6206”，
 * 于是销量、报价、月底对账全失真。两条设计决定贯穿本文件：
 *   1) 明细里同时保存名称/价格快照 —— 商品今天改价不能改写昨天签出去的订单；
 *   2) 人工表单与 AI 共用 OrderItem::normalizeRows()/Product::resolve() 一套规则 ——
 *      否则一定会长出“人能选、AI 能塞”的两套规矩。
 */
require __DIR__ . '/../bootstrap.php';

function prodAdmin(): int
{
    $_SESSION['user_id'] = 1;
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
    (new Setting())->setMany(['ai_enabled' => '1', 'ai_provider' => 'mock', 'ai_mode' => 'preview',
        'items_require_product' => '1', 'ai_allow_delete' => '1'], 1);
    Setting::flushCache();
    Ai::flushHistoryCache();
    return 1;
}

function mkProduct(string $name, string $sku = '', float $price = 100, string $unit = '件', int $owner = 1): array
{
    $id = (int) (new Product())->create(['name' => $name, 'sku' => $sku, 'price' => $price,
        'unit' => $unit, 'status' => 'active', 'owner_id' => $owner]);
    $model = new Product();
    return ['id' => $id, 'code' => $model->codeOf($model->find($id)), 'row' => $model->find($id)];
}

function orderWithItems(int $customerId, array $lines): int
{
    $oid = (int) (new Order())->create(['order_number' => (new Order())->generateOrderNumber(),
        'customer_id' => $customerId, 'title' => '商品测试订单', 'amount' => 0, 'status' => 'pending',
        'payment_status' => 'unpaid', 'owner_id' => 1]);
    $items = [];
    foreach ($lines as $l) {
        $qty = (float) $l['quantity'];
        $price = (float) $l['unit_price'];
        $items[] = array_merge($l, [
            'quantity' => $qty, 'unit_price' => $price, 'subtotal' => round($qty * $price, 2), 'notes' => '',
        ]);
    }
    (new OrderItem())->syncItems($oid, $items);
    return $oid;
}

/** 编号规则与其它模块一致；SKU 填了就不能重号 */
function test_product_codes_and_sku_rules(): void
{
    $user = prodAdmin();
    $a = mkProduct('6206 深沟球轴承', 'BRG-6206', 3.5, '个');
    assertTrue((bool) preg_match('~^PROD-\d{6}$~', (string) $a['code']), '商品编号格式：' . $a['code']);
    assertEquals('PROD-' . sprintf('%06d', $a['id']), (string) $a['code'], '编号由 id 派生');

    [$unused, $errors] = Product::validateInput(['name' => '另一个', 'price' => 1, 'sku' => 'BRG-6206']);
    assertTrue($errors !== [], 'SKU 重复必须报错');
    assertContains('已被其它商品占用', implode('；', $errors));
    // 不填 SKU 不受唯一约束（局部唯一索引正是为此）
    [$unused2, $okErrors] = Product::validateInput(['name' => '没货号的商品', 'price' => 2, 'sku' => '']);
    assertEquals([], $okErrors, '空 SKU 不该报冲突：' . implode('；', $okErrors));
    $second = mkProduct('没货号的商品 B', '', 2);
    assertContains('PROD-', $second['code']);

    // 名字是必填，价格是必填数字
    [$unused3, $bad] = Product::validateInput(['name' => '', 'price' => 'abc']);
    assertTrue(count($bad) >= 2, '空名 + 非数字价格应各报一条：' . implode('；', $bad));
}

/** 引用可以写编号、SKU、精确名称，也可以写裸 ID；写不出来的一律拒绝 */
function test_product_references_resolve(): void
{
    $user = prodAdmin();
    $p = mkProduct('深沟球轴承 6207', 'BRG-6207', 4.2);
    $m = new Product();
    foreach ([$p['code'], (string) $p['id'], 'BRG-6207', '深沟球轴承 6207', 'brg-6207'] as $ref) {
        $row = $m->resolve($ref);
        assertTrue(is_array($row) && (int) $row['id'] === $p['id'], "引用写法「{$ref}」应命中同一条商品");
    }
    assertTrue($m->resolve('不存在的货号') === null, '编不出来的引用必须返回空');
    assertTrue($m->resolve('') === null);
    assertTrue(!is_array((new Product())->find(999999)), '不存在的 id：find 返回 false（不是 null）');
}

/** 明细必须引用商品库；改价不影响历史订单（快照） */
function test_items_must_reference_products_and_keep_snapshots(): void
{
    $user = prodAdmin();
    $cid = (int) (new Customer())->create(['name' => '商品订单客户', 'status' => 'active', 'owner_id' => $user]);
    $p = mkProduct('圆锥滚子轴承', 'BRG-TR', 12.5, '套');

    $ok = OrderItem::normalizeRows([
        ['product_id' => $p['code'], 'quantity' => '8'],            // 单价不给 → 按商品库带出
    ], true);
    assertEquals([], $ok['errors'], json_encode($ok['errors'], JSON_UNESCAPED_UNICODE));
    assertEquals(1, count($ok['items']));
    assertEquals(12.5, (float) $ok['items'][0]['unit_price'], '缺省单价按商品库现行价');
    assertEquals('圆锥滚子轴承', (string) $ok['items'][0]['product_name'], '名称按快照写入');
    assertEquals('套', (string) $ok['items'][0]['unit'], '单位也来自商品库');

    $bad = OrderItem::normalizeRows([['product_name' => '手挨一个名字', 'quantity' => '1', 'unit_price' => '1']], true);
    assertTrue($bad['errors'] !== [], '自由文本商品名必须被拒');
    assertContains('不在商品库里', $bad['errors'][0]);

    // 历史行：不改可以留住，一改就要选商品
    $legacy = [['product_name' => '老行', 'sku' => '', 'quantity' => '1', 'unit_price' => '10',
                'unit' => '件', 'legacy_name' => '老行', 'legacy_price' => '10']];
    assertEquals([], OrderItem::normalizeRows($legacy, true)['errors'], '没动过的历史行应放行');
    $legacy[0]['unit_price'] = '99';
    assertTrue(OrderItem::normalizeRows($legacy, true)['errors'] !== [], '改了价的历史行必须选商品');

    // 开关关掉后恢复旧做法（商品库还没建全时不该卡住下单）
    $off = OrderItem::normalizeRows([['product_name' => '临时自由文本', 'quantity' => '1', 'unit_price' => '5']], false);
    assertEquals([], $off['errors'], '关掉开关应允许自由文本：' . implode('；', $off['errors']));

    // 快照：商品改价之后，已经写下去的订单行不能变
    (new Product())->update($p['id'], ['price' => 99, 'name' => '改过名的圆锥轴承']);
    $oid = orderWithItems($cid, [
        ['product_id' => $p['id'], 'product_name' => '圆锥滚子轴承', 'sku' => 'BRG-TR',
         'quantity' => 8, 'unit_price' => 12.5, 'unit' => '套'],
    ]);
    $line = (new OrderItem())->byOrder($oid)[0];
    assertEquals('圆锥滚子轴承', (string) $line['product_name'], '历史订单里的名称不能被商品改名带跑');
    assertEquals('12.50', number_format((float) $line['unit_price'], 2), '历史订单里的单价不能被商品改价带跑');
    assertEquals($p['id'], (int) $line['product_id'], '但仍要链回商品，便于统计');
}

/** 删除商品的边界：被引用时不能硬删 */
function test_referenced_products_cannot_be_deleted(): void
{
    $user = prodAdmin();
    $cid = (int) (new Customer())->create(['name' => '删除边界客户', 'status' => 'active', 'owner_id' => $user]);
    $p = mkProduct('会被引用的商品', 'USED-01', 10);
    $free = mkProduct('没人买的商品', 'FREE-01', 10);
    orderWithItems($cid, [['product_id' => $p['id'], 'product_name' => '会被引用的商品',
        'sku' => 'USED-01', 'quantity' => 1, 'unit_price' => 10, 'unit' => '件']]);

    $usage = (new Product())->usage($p['id']);
    assertEquals(1, $usage['items']);
    assertEquals(1, $usage['orders']);
    assertEquals(0, (new Product())->usage($free['id'])['items']);

    $res = Ai::execute(Ai::validatePlan([['tool' => 'delete_product',
        'args' => ['product_id' => $p['code'], 'confirm' => true, 'reason' => '不卖了']]], $user)['actions'], $user);
    assertTrue(!($res['results'][0]['ok'] ?? false), '被引用的商品不该被 AI 删掉');
    assertContains('不能删除', (string) $res['results'][0]['message']);
    assertContains('停用', (string) $res['results'][0]['message'], '要给出路：改成停用');
    assertTrue(is_array((new Product())->find($p['id'])), '被拒之后商品必须还在（find 找不到时返回 false）');

    $plan2 = Ai::validatePlan([['tool' => 'delete_product',
        'args' => ['product_id' => $free['code'], 'confirm' => true, 'reason' => '建错了']]], $user);
    assertEquals([], $plan2['errors'], '删除没人买的商品应通过校验：' . implode('；', $plan2['errors']));
    $res2 = Ai::execute($plan2['actions'], $user);
    assertTrue((bool) ($res2['results'][0]['ok'] ?? false), '没被引用的商品可以删：' . (string) ($res2['results'][0]['message'] ?? ''));
    assertTrue(!is_array((new Product())->find($free['id'])),
        '删完必须真的不在了（id ' . (int) $free['id'] . '，code ' . $free['code'] . '）');
}

/** 影响面要在确认前就说清：删商品会牵连哪些订单 */
function test_delete_impact_reports_usage(): void
{
    $user = prodAdmin();
    $cid = (int) (new Customer())->create(['name' => '影响面客户', 'status' => 'active', 'owner_id' => $user]);
    $p = mkProduct('影响面商品', 'IMP-01', 7);
    orderWithItems($cid, [['product_id' => $p['id'], 'product_name' => '影响面商品', 'sku' => 'IMP-01',
        'quantity' => 2, 'unit_price' => 7, 'unit' => '件']]);
    $impact = Ai::deleteImpact('delete_product', ['product_id' => $p['code']]);
    assertContains($p['code'], (string) $impact['target']);
    assertContains('影响面商品', (string) $impact['target']);
    assertEquals(1, (int) ($impact['cascade']['订单明细'] ?? 0));
    assertTrue(isset($impact['warning']) && str_contains((string) $impact['warning'], '停用'),
        '被引用时要给替代方案');
}

/** 老库升级/收编入口：把没关联的历史明细变成商品 */
function test_import_unlinked_history_lines(): void
{
    $user = prodAdmin();
    $cid = (int) (new Customer())->create(['name' => '收编客户', 'status' => 'active', 'owner_id' => $user]);
    // 塞一条没有 product_id 的旧行（模拟升级前手挨的数据）——必须有真订单，外键会拦假 order_id
    $oid = (int) (new Order())->create(['order_number' => (new Order())->generateOrderNumber(),
        'customer_id' => $cid, 'title' => '老订单', 'amount' => 5, 'status' => 'pending',
        'payment_status' => 'unpaid', 'owner_id' => $user]);
    (new OrderItem())->create(['order_id' => $oid, 'product_name' => '老行商品甲', 'sku' => 'OLD-A',
        'quantity' => 1, 'unit_price' => 5, 'subtotal' => 5, 'unit' => '件', 'sort_order' => 1]);
    $before = (new Product())->unlinkedItemCount();
    assertTrue($before >= 1, "前提：库里应有未关联明细");
    $stat = (new Product())->importUnlinkedItems();
    assertTrue($stat['created'] >= 1, '应收编出新商品：' . json_encode($stat, JSON_UNESCAPED_UNICODE));
    assertEquals(0, (new Product())->unlinkedItemCount(), '再点一次应全部链上（幂等）');
    $again = (new Product())->importUnlinkedItems();
    assertEquals(0, $again['created'], '重复收编不能造重复商品');
    $found = (new Product())->resolve('老行商品甲');
    assertTrue(is_array($found), '收编后的商品要能被搜到');
    assertEquals('OLD-A', (string) $found['sku']);
    assertEquals(5.0, (float) $found['price'], '价格按历史成交带进来');
}

/** AI 面：商品要能被查、被建、被改；编号进 <found> 后模型才不用猜 */
function test_ai_surface_covers_products(): void
{
    $user = prodAdmin();
    $p = mkProduct('AI 可见商品', 'AI-01', 21, '台');

    $surfaces = Ai::searchSurfaces();
    assertTrue(isset($surfaces['product']), 'searchSurfaces 要有商品面');
    assertEquals('products', (string) $surfaces['product']['table']);

    $run = Ai::execute(Ai::validatePlan([['tool' => 'search_records',
        'args' => ['tables' => 'product', 'q' => 'AI-01']]], $user)['actions'], $user);
    $msg = (string) ($run['results'][0]['message'] ?? '');
    $detail = (string) ($run['results'][0]['rows'][0]['detail'] ?? '');
    assertContains($p['code'], $detail, '搜索结果的行要带商品编号：' . textClip($detail, 140));
    assertContains('AI 可见商品', $detail);
    assertContains('1 条', $msg, '总数要在 message 里');

    $found = Ai::foundDigest('给订单加一行 AI 可见商品 3 台');
    assertContains($p['code'], (string) $found, '<found> 要能把商品名换成编号：' . textClip($found, 160));

    $mk = Ai::execute(Ai::validatePlan([['tool' => 'create_product', 'args' => [
        'name' => 'AI 建的商品', 'sku' => 'AI-NEW', 'price' => 66, 'unit' => '个', 'spec' => 'M8×30']]], $user)['actions'], $user);
    assertTrue((bool) ($mk['results'][0]['ok'] ?? false), (string) ($mk['results'][0]['message'] ?? ''));
    $row = (new Product())->resolve('AI-NEW');
    assertEquals('AI 建的商品', (string) $row['name']);
    assertEquals('M8×30', (string) $row['spec'], 'AI 能写商品库的所有字段（含规格）');
    assertContains('PROD-', (string) $mk['results'][0]['message']);

    $ch = Ai::execute(Ai::validatePlan([['tool' => 'update_product', 'args' => [
        'product_id' => 'AI-NEW', 'price' => 88, 'status' => 'inactive']]], $user)['actions'], $user);
    $row2 = (new Product())->find((int) $row['id']);
    assertEquals('88.00', number_format((float) $row2['price'], 2));
    assertEquals('inactive', (string) $row2['status']);
    assertContains('单价', (string) ($ch['results'][0]['message'] ?? ''), '回执要用中文名');

    // 停用之后搜索仍可查到，但页面选择框按“在售优先”排；编号引用不能误伤到别的商品
    $ambiguous = mkProduct('AI 建的商品', 'AI-TWIN', 5);
    $bad = Ai::validatePlan([['tool' => 'update_product', 'args' => ['product_id' => '查无此货', 'price' => 1]]], $user);
    assertTrue($bad['blocked'], '不存在的商品编号必须被引用校验拦下');
    assertContains('既不是编号也不是 ID', $bad['errors'][0], '看不像引用的值要直说，别让人以为是查不到');
}

/** 明细行可查：「哪个商品卖得最多」只能从 order_items 统计 */
function test_order_lines_are_searchable_for_aggregation(): void
{
    $user = prodAdmin();
    $cid = (int) (new Customer())->create(['name' => '统计客户', 'status' => 'active', 'owner_id' => $user]);
    $p1 = mkProduct('卖得多的', 'MANY-1', 5);
    $p2 = mkProduct('卖得少的', 'FEW-1', 9);
    $o1 = orderWithItems($cid, [['product_id' => $p1['id'], 'product_name' => '卖得多的', 'sku' => 'MANY-1',
        'quantity' => 10, 'unit_price' => 5, 'unit' => '件'],
        ['product_id' => $p2['id'], 'product_name' => '卖得少的', 'sku' => 'FEW-1', 'quantity' => 1, 'unit_price' => 9, 'unit' => '件']]);
    $res = Ai::execute(Ai::validatePlan([['tool' => 'search_records',
        'args' => ['tables' => 'order_item', 'all' => true]]], $user)['actions'], $user);
    $msg = (string) ($res['results'][0]['message'] ?? '');
    assertContains('明细', $msg, '明细面要在搜索结果里出现：' . textClip($msg, 130));
    $rows = (array) ($res['results'][0]['rows'] ?? []);
    $blob = json_encode($rows, JSON_UNESCAPED_UNICODE);
    assertContains('卖得多的', $blob);
    assertContains('subtotal=', $blob, '统计销量必须要小计字段（明细行以字段串给出）');
    assertContains('quantity=', $blob);
    assertTrue(str_contains($blob, '"writable":false'), '明细行不能单独改：writable 必须为 false');
    // 提示词里要告诉模型聚合问句怎么办（不告诉它就会说查不到或者干脆猜一个）
    assertContains('聚合问句', Ai::systemPrompt());
    $bad = Ai::validatePlan([['tool' => 'search_records', 'args' => ['tables' => 'order_item']]], $user);
    assertTrue($bad['blocked'], '明细整表也要显式 all:true，防止含糊指令扫全库');
}

/** 提示词与文档都得说清这件事（同源生成，不是手写说明书） */
function test_prompt_and_docs_mention_the_catalog(): void
{
    $user = prodAdmin();
    mkProduct('文档检查商品', 'DOC-01', 9);
    $prompt = Ai::systemPrompt();
    assertContains('create_product', $prompt, '提示词里要有商品工具');
    assertContains('product', (string) json_encode(Ai::toolsForPrompt(), JSON_UNESCAPED_UNICODE));
    assertContains('不要塞自由文本的商品名', $prompt, '规则要说清明细的引用要求');
    assertContains('商品', Ai::contextDigest($user), '数据快照里应有商品计数');

    $docs = AppMap::toText();
    assertContains('products', $docs, '数据字典要能查到商品表');
    assertContains('normalizeRows', $docs, '说明要指到那个唯一的洗行入口');
    assertContains('items_require_product', $docs, '开关要出现在设置清单里');
    $tools = Ai::tools();
    foreach (['create_product', 'update_product', 'delete_product'] as $t) {
        assertTrue(isset($tools[$t]), "工具表里应有 {$t}");
    }
    assertEquals('delete', (string) $tools['delete_product']['kind'], '删除商品必须归入 delete 档');
    assertTrue(Ai::isDestructive('delete_product'), '它必须走人工确认那套门槛');
}

/** 表单里的选择框：上面输入框、下面 select，且禁用 JS 也能提交 */
function test_picker_markup_is_input_on_top_of_select(): void
{
    $user = prodAdmin();
    $p = mkProduct('选择框商品', 'PICK-01', 12);
    $html = renderPickerPartial($p['code']);
    $iSearch = strpos($html, 'class="form-control form-control-sm mb-1 picker-search"');
    $iSelect = strpos($html, 'name="items[0][product_id]"');
    assertTrue($iSearch !== false && $iSelect !== false, '输入框与 select 都要在');
    assertTrue($iSearch < $iSelect, '输入框必须在 select 上方（就是人眼看到的顺序）');
    $js = renderPickerJs();
    assertContains('CRM_PRODUCTS', $js);
    assertContains('"price":12', $js, '目录要带单价，选完才能回填');
    assertContains('选择框商品', $js);
    assertContains('CrmProductPicker', $js);
    assertContains('required', $html, '新行必须选商品');
    // 历史行：不加 required，否则老单子存不了
    $legacyHtml = renderPickerPartial('', ['product_name' => '老行', 'sku' => '', 'unit' => '件', 'unit_price' => '3']);
    assertTrue(!str_contains($legacyHtml, ' required'), '历史手填行不该被 required 卡住');
    assertContains("data-legacy=", $legacyHtml, '历史行要把原值带给选择框');
    assertContains('老行', $legacyHtml);
    $js = renderPickerJs();
    assertContains('历史手填：', $js, '选择框 JS 负责把历史行渲染成一条不可误选的 option');
}

/** data-legacy 属性里的 JSON 必须整体转义：历史行名称带引号/尖括号时不能打出属性外 */
function test_legacy_json_in_picker_attribute_is_entity_escaped(): void
{
    // 攻击载荷：单引号关掉 data-legacy 后注入一个 <img>（存储型 XSS 现场）
    $payload = "A'><img src=x onerror=alert(1)>";
    $legacy = ['product_name' => $payload, 'sku' => '', 'unit' => '件', 'unit_price' => '3'];
    $html = renderPickerPartial('', $legacy);

    assertContains('data-legacy=', $html, '历史行数据仍在属性里');
    assertTrue(!str_contains($html, "'><img"), "载荷的关属性+开标签序列不得出现（原始载荷：{$payload}）");
    assertTrue(str_contains($html, '&#039;'), "单引号必须转成实体（而不是留在属性值里）");
    assertTrue(str_contains($html, '&lt;img'), '< 必须被转义成实体');

    // 反向验证：浏览器解析属性时会先做实体解码，解码后必须仍是同一份合法 JSON，
    // JSON.parse 才拿得到原名（这是 dataset.legacy 的读取路径，转义不能破坏它）
    if (preg_match("/data-legacy='([^']*)'/", $html, $m)) {
        $decoded = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        $parsed = json_decode($decoded, true);
        assertTrue(is_array($parsed) && ($parsed['product_name'] ?? '') === $payload,
            '实体解码后仍是原样 JSON，JSON.parse 不受影响');
    } else {
        throw new RuntimeException('data-legacy attribute missing');
    }
}

function renderPickerJs(): string
{
    ob_start();
    $products = (new Product())->pickList();
    include APP_PATH . '/views/products/_picker_js.php';
    return (string) ob_get_clean();
}

function renderPickerPartial(string $selected, ?array $legacy = null): string
{
    ob_start();
    $name = 'items[0][product_id]';
    $products = (new Product())->pickList();
    $truncated = false;
    include APP_PATH . '/views/products/_picker.php';
    return (string) ob_get_clean();
}

// ------------------------------------------------------------ CSV 导入/导出

/** 临时 CSV 文件（测试进程自带独立库，文件用完即删） */
function writeTempCsv(string $body): string
{
    $path = sys_get_temp_dir() . '/crm_products_' . bin2hex(random_bytes(4)) . '.csv';
    file_put_contents($path, $body);
    return $path;
}

/** 按 Product::csvColumns() 的列序把行拼成 CSV 正文（与导出端同一套转义） */
function productCsvBody(array $rows): string
{
    $cols = Product::csvColumns();
    $cell = static function ($v): string {
        $s = (string) $v;
        return strpbrk($s, ",\"\r\n") !== false ? '"' . str_replace('"', '""', $s) . '"' : $s;
    };
    $lines = [implode(',', array_values($cols))];
    foreach ($rows as $row) {
        $cells = [];
        foreach (array_keys($cols) as $k) {
            $cells[] = $cell($row[$k] ?? '');
        }
        $lines[] = implode(',', $cells);
    }
    return implode("\r\n", $lines) . "\r\n";
}

/** 导出行是可读文本：编号回填、金额去尾零、状态中文、键与 csvColumns 对齐 */
function test_csv_export_shape(): void
{
    prodAdmin();
    $a = mkProduct('螺丝 M4, 不锈钢', 'SCR-M4', 1.5, '个');
    (new Product())->update($a['id'], ['cost' => 0.8]);
    (new Product())->create(['name' => '停用的无码商品', 'price' => 2, 'status' => 'inactive', 'unit' => '件', 'owner_id' => 1]);

    assertEquals('1.5', Product::csvNumber(1.5), '小数不补零');
    assertEquals('0', Product::csvNumber(0.0), '整数不带小数点');
    assertEquals('8', Product::csvNumber(8), '整数直接输出');
    assertEquals('2.5', Product::csvNumber(2.50), '去尾零');

    $rows = (new Product())->exportRows('SCR-M4');
    assertEquals(1, count($rows), '按 SKU 过滤导出只命中一条');
    assertEquals(array_keys(Product::csvColumns()), array_keys($rows[0]), '导出键与列定义对齐');
    assertEquals('螺丝 M4, 不锈钢', $rows[0]['name'], '名称原样');
    assertEquals('1.5', $rows[0]['price'], '单价去尾零');
    assertEquals('0.8', $rows[0]['cost'], '参考价去尾零');
    assertEquals('在售', $rows[0]['status'], '状态给中文');
    assertContains('PROD-', $rows[0]['public_code'], '编号已回填');

    $inactive = (new Product())->exportRows('停用的无码商品');
    assertEquals('停用', $inactive[0]['status'], '停用也要给中文标签');
}

/** 同一份导出文件可导回：SKU/唯一名称命中更新，新行新建，坏行跳过；再导一遍不重复 */
function test_csv_import_upsert_and_roundtrip(): void
{
    $user = prodAdmin();
    mkProduct('已有商品', 'KEEP-1', 10, '个');
    $body = productCsvBody([
        ['name' => '已有商品', 'sku' => 'KEEP-1', 'category' => '轴承', 'brand' => '', 'spec' => '',
         'unit' => '个', 'price' => '12.5', 'cost' => '', 'status' => '在售', 'notes' => '改价了'],
        ['name' => '全新导入商品', 'sku' => 'NEW-1', 'category' => '', 'brand' => '', 'spec' => 'M6',
         'unit' => '套', 'price' => '3', 'cost' => '1', 'status' => 'active', 'notes' => ''],
        ['name' => '缺单价的坏行', 'sku' => 'BAD-1', 'category' => '', 'brand' => '', 'spec' => '',
         'unit' => '件', 'price' => '', 'cost' => '', 'status' => '', 'notes' => ''],
    ]);
    $path = writeTempCsv($body);
    $p = new Product();
    $stat = $p->importCsvFile($path, $user);

    assertEquals(1, $stat['created'], '新建 1 条');
    assertEquals(1, $stat['updated'], 'SKU 命中更新 1 条');
    assertEquals(1, $stat['skipped'], '缺单价的坏行整行跳过');
    assertTrue($stat['errors'] !== [], '跳过必须带原因');
    assertContains('单价', $stat['errors'][0], '原因要说清楚缺什么');

    $old = $p->resolve('KEEP-1');
    assertEquals(12.5, (float) $old['price'], '更新生效：价格');
    assertEquals('轴承', (string) $old['category'], '更新生效：分类');
    assertEquals('改价了', (string) $old['notes'], '更新生效：备注');
    assertEquals(1, (int) $old['owner_id'], '归属人不被动');

    $fresh = $p->resolve('NEW-1');
    assertEquals('全新导入商品', (string) $fresh['name'], '新建落库');
    assertEquals('套', (string) $fresh['unit'], '新建带单位');
    assertEquals('M6', (string) $fresh['spec']);

    // 幂等：同一份文件导第二遍 = 全部更新、零新建
    $again = $p->importCsvFile($path, $user);
    assertEquals(0, $again['created'], '第二遍新建为 0');
    assertEquals(2, $again['updated'], '第二遍按身份全部更新');
    @unlink($path);
}

/** 名称撞到多个商品时绝不猜：整行跳过并提示补 SKU */
function test_csv_import_name_ambiguity_is_safe(): void
{
    $user = prodAdmin();
    mkProduct('重名商品', 'DUP-A', 1);
    mkProduct('重名商品', 'DUP-B', 2);
    $path = writeTempCsv("名称,SKU,单价\n重名商品,,9\n");
    $stat = (new Product())->importCsvFile($path, $user);
    assertEquals(0, $stat['created'], '歧义行不新建');
    assertEquals(0, $stat['updated'], '歧义行不更新');
    assertEquals(1, $stat['skipped'], '歧义行整行跳过');
    assertContains('多个商品', $stat['errors'][0], '原因要说清是撞名');
    @unlink($path);
}

/** 编码容错（BOM/CRLF/GBK）与表头错误要给出人能懂的信息 */
function test_csv_import_encoding_and_header_errors(): void
{
    $user = prodAdmin();
    $p = new Product();

    // 带 BOM + CRLF 的 UTF-8 文件能正常导入
    $path = writeTempCsv("\xEF\xBB\xBF名称,SKU,单价,状态\r\nBOM商品,BOM-1,5,在售\r\n");
    $stat = $p->importCsvFile($path, $user);
    assertEquals(1, $stat['created'], '带 BOM 的 UTF-8 能导入');
    assertEquals('BOM商品', (string) $p->resolve('BOM-1')['name'], '中文名入库');
    @unlink($path);

    // GBK（Excel 中文另存的常见编码）在 iconv 可用时也要能导
    if (function_exists('iconv')) {
        $gbk = iconv('UTF-8', 'GB18030', "名称,单价\nGBK商品,7\n");
        $g = $p->importCsvFile(writeTempCsv((string) $gbk), $user);
        assertEquals(1, $g['created'], 'GBK 文件能转换后导入');
        assertEquals('GBK商品', (string) $p->resolve('GBK商品')['name'], 'GBK 中文名不乱码');
    }

    // 表头找不到「名称」列 → 整体拒绝
    $badPath = writeTempCsv("SKU,单价\nA-9,1\n");
    $threw = false;
    $msg = '';
    try {
        $p->importCsvFile($badPath, $user);
    } catch (Throwable $e) {
        $threw = true;
        $msg = $e->getMessage();
    }
    assertTrue($threw, '表头缺「名称」必须抛错，而不是静默建垃圾');
    assertContains('名称', $msg);
    @unlink($badPath);
}

runCase();
