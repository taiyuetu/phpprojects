<?php
/**
 * FieldsTest — 字段语义注册表（core/Fields）与 Schema 实表的一致性。
 *
 * 目标：注册表是"一处声明、多处派生"的骨架。这里兜三种漂移——
 *   1) 注册表里声明的列必须是库里真实存在的列（手滑写错列名要立刻红）；
 *   2) 关键词搜索列：注册表 searchable 派生的结果必须与历史清单一致（行为不变）；
 *   3) 没有注册表/没有声明时，自动推断照常工作，Fields 与 Schema 口径一致。
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
require __DIR__ . '/../bootstrap.php';

/** 有序集合相等的断言（搜索列的顺序不影响结果，比较时排好序） */
function assertSameSet(array $expected, array $actual, string $msg): void
{
    $sort = static function (array $a): array {
        $b = array_values(array_unique($a));
        sort($b, SORT_STRING);
        return $b;
    };
    assertEquals($sort($expected), $sort($actual), $msg);
}

function test_registry_keys_are_real_columns(): void
{
    foreach ([
        ['model' => new Customer(), 'table' => 'customers'],
        ['model' => new Lead(), 'table' => 'leads'],
    ] as $case) {
        $real = array_map(static fn($c) => (string) $c['name'], Schema::columns($case['table']));
        $declared = array_keys($case['model']->fieldDefs());
        $missing = array_diff($declared, $real);
        assertEquals([], $missing, "{$case['table']} 注册表里声明的列必须是真实列，多余的：" . implode(', ', $missing));
    }
}

function test_searchable_columns_match_previous_lists(): void
{
    // 历史清单（改动前手写在各 Model 的 $searchable）——搜索行为必须原样保留
    $expected = [
        'customers' => ['name', 'company', 'email', 'phone', 'whatsapp', 'wechat', 'source_country', 'notes'],
        'leads'     => ['title', 'company', 'contact_name', 'contact_email', 'phone', 'whatsapp',
                        'source', 'source_country', 'source_city', 'notes'],
    ];
    foreach ($expected as $table => $list) {
        $model = $table === 'customers' ? new Customer() : new Lead();
        $defs = $model->fieldDefs();

        assertSameSet($list, $model->searchableColumns(), "{$table}: Model::searchableColumns 与历史清单一致");
        assertSameSet($list, Fields::searchableColumns($table, $defs), "{$table}: Fields::searchableColumns 同口径");
    }
}

/** 未声明 searchable 的表（如 follow_ups）走自动推断，剔除枚举/编号/时间戳列 */
function test_no_registry_table_still_infers_searchable(): void
{
    $cols = (new FollowUp())->searchableColumns();
    assertTrue(in_array('title', $cols, true), '文本列照常被搜');
    assertTrue(in_array('description', $cols, true));
    assertTrue(in_array('next_action', $cols, true));

    assertTrue(!in_array('id', $cols, true), '主键不进搜索');
    assertTrue(!in_array('type', $cols, true), '枚举列有专门筛选，不进关键词搜索');
    assertTrue(!in_array('next_date', $cols, true), '日期列不进关键词搜索');
    assertTrue(!in_array('created_at', $cols, true), '时间戳不进关键词搜索');

    // 与引擎直呼口径一致（同一份推断逻辑）
    assertEquals($cols, Fields::searchableColumns('follow_ups', []), 'Model 与 Fields 推断一致');
}

/** 描述合并：声明补 label/type，DB 补枚举/可空/主键；未声明的列也有可用默认 */
function test_columns_overlay_merges_declared_and_schema(): void
{
    $defs = (new Customer())->fieldDefs();
    $cols = Fields::columns('customers', $defs);

    assertEquals('姓名', $cols['name']['label'], '声明的 label 生效');
    assertEquals('email', $cols['email']['type'], '声明的类型生效');
    assertEquals('备注', $cols['notes']['label'], 'notes 的 label');
    assertEquals('text', $cols['notes']['type'], '长文本类型生效');

    // status 的枚举值自动来自 DB CHECK，不用在注册表重复
    assertEquals(['active', 'inactive'], $cols['status']['options'], 'CHECK 枚举自动带出');
    assertEquals('enum', $cols['status']['type']);

    // 未在注册表声明的系统列：也有默认描述（label 回退列名），只是没有多余语义
    assertTrue($cols['id']['pk'], 'id 是主键');
    assertEquals('id', $cols['id']['label'], '没声明 label 时回退列名');
    // 注：SQLite 的 INTEGER PRIMARY KEY 在 pragma 里 notnull=0（靠 pk 表达），所以这里不断言 id 不可空

    assertTrue(!$cols['name']['nullable'], 'name NOT NULL 不可空');
    assertTrue($cols['company']['nullable'], 'company 可空');
}

/** 清洗语义与各 Controller 旧 validate() 逐条对齐（错误文案、默认回落、可空即 NULL） */
function test_sanitize_keeps_old_validator_semantics(): void
{
    // 客户：姓名必填、邮箱格式、空可空列写 NULL、复选框缺省 0、非法状态回落 active
    [$d, $e] = (new Customer())->sanitizeInput(['name' => '  ', 'email' => 'not-an-email']);
    assertEquals(['客户姓名不能为空。', '请输入有效的邮箱地址。'], $e, '姓名/邮箱错误文案保留');

    [$d2, $e2] = (new Customer())->sanitizeInput(['name' => '张三', 'company' => '', 'email' => 'ok@x.com',
        'status' => 'bogus', 'first_purchase_from_china' => '1']);
    assertEquals([], $e2, '合规输入零错误：' . implode('；', $e2));
    assertEquals(null, $d2['company'], '空可空列 → NULL');
    assertEquals('active', $d2['status'], '非法状态回落默认');
    assertEquals(1, $d2['first_purchase_from_china'], '复选框勾选 = 1');
    assertEquals(0, $d2['has_import_capability'], '复选框未勾 = 0');

    // 商机：客户必选、金额空默认 0、非法阶段回落 open
    [$dd, $ed] = (new Deal())->sanitizeInput(['title' => '', 'customer_id' => '', 'value' => '', 'stage' => 'x']);
    assertTrue(in_array('商机名称不能为空。', $ed, true), '商机名文案');
    assertTrue(in_array('请选择一个客户。', $ed, true), '选客户文案');
    [$d3, $e3] = (new Deal())->sanitizeInput(['title' => 'D', 'customer_id' => '5', 'stage' => 'closed_lost']);
    assertEquals([], $e3, '合规商机零错误');
    assertEquals(0.0, $d3['value'], '金额空 → 0');
    assertEquals('closed_lost', $d3['stage'], '合法阶段原样保留');

    // 订单：空单号/空标题/未选客户都报错；空日期补今天；deal_id 传 0 = 不关联
    [$do, $eo] = (new Order())->sanitizeInput(['order_number' => '', 'title' => '', 'customer_id' => '0']);
    assertSameSet(['订单编号不能为空。', '订单标题不能为空。', '请选择一个客户。'], $eo, '订单三条必填文案');
    [$d4, $e4] = (new Order())->sanitizeInput([
        'order_number' => 'ORD-1', 'title' => 'T', 'customer_id' => '9', 'deal_id' => '0', 'order_date' => '',
    ]);
    assertEquals([], $e4, '合规订单零错误：' . implode('；', $e4));
    assertEquals(date('Y-m-d'), $d4['order_date'], '空下单日期补今天');
    assertEquals(null, $d4['deal_id'], 'deal_id 传 0 → 不关联');

    // 商品：SKU 唯一冲突走引擎唯一性钩子（自排除后不报）；单位非法报错
    $p = mkPro();   // 占住 SKU = UNIQ-K
    [$unused2, $dup] = Product::validateInput(['name' => 'X2', 'price' => '1', 'sku' => 'UNIQ-K']);
    assertContains('已被其它商品占用', implode('；', $dup), '重复 SKU 被引擎拦下');
    [$d5, $e5] = Product::validateInput(['name' => 'X', 'price' => '1', 'sku' => 'UNIQ-K'], $p);
    assertEquals([], $e5, '改自己不算冲突');
    [$unused3, $badUnit] = Product::validateInput(['name' => 'Y', 'price' => '2', 'unit' => '不存在单位']);
    assertContains('单位不在可选值里。', implode('；', $badUnit));
}

function mkPro(): int
{
    $id = (int) (new Product())->create(['name' => '唯一测试商品', 'sku' => 'UNIQ-K', 'price' => 1, 'unit' => '件', 'owner_id' => 1]);
    return $id;
}

/** 自动表单：只有注册表里带 form 标记的列才渲染；无标记 = 零输出、不干扰手写布局 */
function test_auto_form_fields_only_include_flagged_columns(): void
{
    $defs = (new Customer())->fieldDefs();
    assertEquals([], Fields::autoFormFields('customers', $defs), '没标 form 的现有表单零增量');

    $withNotes = $defs;
    $withNotes['notes'] = array_merge($withNotes['notes'], ['form' => ['width' => 'col-12']]);
    $auto = Fields::autoFormFields('customers', $withNotes);
    assertEquals(['notes'], array_keys($auto), '只有 notes 进入自动区');
    assertEquals('col-12', $auto['notes']['form']['width'], '宽度布局透传');

    $html = Fields::block($auto['notes'], ['notes' => '一行<备注>'], []);
    assertContains('name="notes"', $html, '控件带字段名');
    assertContains('备注', $html);
    assertContains('textarea', $html);
    assertContains('col-12', $html);
}

/** block 渲染：bool→checkbox、enum→select（可选值来自 DB CHECK）、required 星号 */
function test_auto_form_block_control_mapping(): void
{
    $defs = (new Customer())->fieldDefs();
    $cols = Fields::columns('customers', $defs);

    $bool = $cols['first_purchase_from_china'];
    $boolHtml = Fields::block($bool, ['first_purchase_from_china' => 1], []);
    assertContains('type="checkbox"', $boolHtml);
    assertContains('checked', $boolHtml);

    $status = $cols['status'];
    $statusHtml = Fields::block($status, ['status' => 'inactive'], []);
    assertContains('<select', $statusHtml);
    assertContains('value="active"', $statusHtml);
    assertContains('value="inactive"', $statusHtml);
    assertContains('selected', $statusHtml);

    $name = $cols['name'];
    $required = $defs['name'];
    $name['required'] = !empty($required['required']);
    $nameHtml = Fields::block($name, ['name' => '张三'], []);
    assertContains('required', $nameHtml, '必填列带 required 与星号');
    assertContains('text-danger', $nameHtml);
}

/** schema-sync：已建注册表的表当前与库结构一致（缺列时 statements 才非空） */
function test_schema_sync_tables_are_in_sync(): void
{
    foreach (['customers', 'leads', 'deals', 'orders', 'products'] as $t) {
        assertEquals([], SchemaSync::missing($t), "{$t} 已与注册表同步");
        assertEquals([], SchemaSync::statements($t), "{$t} 无待执行 ALTER");
    }
}

function test_schema_sync_sql_helpers(): void
{
    assertEquals('INTEGER', SchemaSync::sqlType(['type' => 'bool']), 'bool → INTEGER');
    assertEquals('INTEGER', SchemaSync::sqlType(['type' => 'int']), 'int → INTEGER');
    assertEquals('REAL', SchemaSync::sqlType(['type' => 'number']), 'number → REAL');
    assertEquals('TEXT', SchemaSync::sqlType([]), '缺省 → TEXT');

    $add = SchemaSync::addColumnClause('customers', 'status', ['type' => 'enum', 'default' => 'active']);
    assertContains('ADD COLUMN status TEXT', $add);
    assertContains("DEFAULT 'active'", $add);

    assertContains('DEFAULT 0', SchemaSync::addColumnClause('orders', 'flag', ['type' => 'bool', 'default' => 0]));
    assertEquals("ALTER TABLE customers ADD COLUMN instagram TEXT;",
        SchemaSync::addColumnClause('customers', 'instagram', []), '新文本列语句');
}

runCase();
