<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
/**
 * public_code 稳定编号（CUS- / LEAD- / DEAL-）。
 *
 * 这个字段存在的全部理由是「让人和 AI 引用同一条记录时不会有歧义」，所以它必须：
 *   - 新增时自动生成，任何人（包括 AI）都不能自己塞一个
 *   - 由 id 派生，因而天然唯一、可复现，迁移里能用一条 SQL 回填
 *   - 编号与裸 ID 都能解析回同一行；编出来的编号必须失败，而不是命中别的行
 *   - 老数据即使还没回填，也不能在界面上显示空白
 */
require __DIR__ . '/../bootstrap.php';

function test_creating_the_three_models_generates_a_code(): void
{
    $custId = (int) (new Customer())->create(['name' => '编号客户', 'status' => 'active', 'owner_id' => 1]);
    $leadId = (int) (new Lead())->create(['title' => '编号线索', 'status' => 'new', 'owner_id' => 1]);
    $dealId = (int) (new Deal())->create(['title' => '编号商机', 'customer_id' => $custId, 'owner_id' => 1]);

    $cust = (new Customer())->find($custId);
    $lead = (new Lead())->find($leadId);
    $deal = (new Deal())->find($dealId);

    assertEquals('CUS-' . sprintf('%06d', $custId), $cust['public_code'], '客户编号 = 前缀 + 六位 id');
    assertEquals('LEAD-' . sprintf('%06d', $leadId), $lead['public_code']);
    assertEquals('DEAL-' . sprintf('%06d', $dealId), $deal['public_code']);
    assertEquals(3, count(array_unique([$cust['public_code'], $lead['public_code'], $deal['public_code']])),
        '三类记录的前缀不同，编号不会撞');
}

function test_a_supplied_code_is_never_trusted(): void
{
    // 编号是系统派生的：外部塞进来的一律忽略，否则“编一个不存在的编号”就失去意义
    $id = (int) (new Customer())->create(['name' => '伪造编号', 'status' => 'active',
        'owner_id' => 1, 'public_code' => 'CUS-999999']);
    $row = (new Customer())->find($id);
    assertEquals('CUS-' . sprintf('%06d', $id), $row['public_code'], '存的是派生值，不是传入值');

    // update 也不能改它（AI 的 update_* 工具参数里根本没有这一项）
    $plan = Ai::validatePlan([['tool' => 'update_customer', 'args' =>
        ['customer_id' => $id, 'public_code' => 'CUS-000001']]], 1);
    assertTrue($plan['blocked'], '试图改编号会被参数白名单拒绝');
    assertContains('不接受参数 public_code', implode('；', $plan['errors']));
}

function test_codes_are_unique_across_a_really_large_batch(): void
{
    $codes = [];
    for ($i = 0; $i < 40; $i++) {
        $codes[] = (new Lead())->create(['title' => '批量 ' . $i, 'status' => 'new', 'owner_id' => 1]);
    }
    $rows = Database::connection()->query('SELECT public_code FROM leads')->fetchAll(PDO::FETCH_COLUMN);
    assertEquals(count($rows), count(array_unique($rows)), '列上还有唯一索引兜底');
    foreach ($rows as $c) {
        assertTrue((bool) preg_match('~^LEAD-\d{6}$~', (string) $c), "编号格式不对：{$c}");
    }
}

function test_every_reference_spelling_lands_on_the_same_row(): void
{
    $id = (int) (new Customer())->create(['name' => '写法客户', 'status' => 'active', 'owner_id' => 1]);
    $model = new Customer();
    $code = 'CUS-' . sprintf('%06d', $id);

    foreach ([$code, strtolower($code), str_replace('-', '', $code), 'cus_' . $id, '#' . $id,
              (string) $id, '  ' . $code . '  '] as $ref) {
        assertEquals($id, $model->idFromReference($ref), "「{$ref}」应解析到 #{$id}");
    }
    // 编不出来的就必须失败，而不是命中隔壁那行
    assertEquals(null, $model->idFromReference('CUS-999999'));
    assertEquals(null, $model->idFromReference('CUS-'));
    assertEquals(null, $model->idFromReference('0'));
    assertEquals(null, $model->idFromReference('编号'));
    // 客户与商机的 id 可能相同，前缀保证它们不会互相认错
    $dealId = (int) (new Deal())->create(['title' => '隔壁商机', 'customer_id' => $id, 'owner_id' => 1]);
    assertEquals(null, (new Customer())->idFromReference('DEAL-' . sprintf('%06d', $dealId)),
        '拿商机编号去查客户应当失败');
}

function test_a_legacy_row_without_a_code_still_shows_one(): void
{
    $id = (int) (new Customer())->create(['name' => '历史客户', 'status' => 'active', 'owner_id' => 1]);
    Database::connection()->query('UPDATE customers SET public_code = NULL WHERE id = ' . $id)->execute();
    $model = new Customer();
    $row = $model->find($id);
    assertEquals(null, $row['public_code'], '列确实空着');
    assertEquals('CUS-' . sprintf('%06d', $id), $model->codeOf($row), '读取时按同一规则推导，界面不留白');

    $model->ensurePublicCode($id);
    assertEquals('CUS-' . sprintf('%06d', $id), $model->find($id)['public_code'], '补写后落库');
    // 已存在的编号不会被覆盖
    Database::connection()->query("UPDATE customers SET public_code = 'CUS-000042' WHERE id = " . $id)->execute();
    $model->ensurePublicCode($id);
    assertEquals('CUS-000042', $model->find($id)['public_code'], 'ensure 只补空，不改写');
}

/**
 * 用“删掉新列的基线”建一个伪老库，再跑真实的 migrate.php：
 * 这比 DROP COLUMN 干净（不依赖 SQLite 版本），而且验的是真实升级路径：
 * 基线自愈 → 006 补列 → 007 回填 + 建唯一索引。 */
function codeOldDb(string $tag): string
{
    $file = sys_get_temp_dir() . '/' . $tag . '-' . uniqid() . '.sqlite';
    $schema = (string) file_get_contents(BASE_PATH . '/database/schema.sql');
    $before = $schema;
    // 去掉基线里的 public_code 列声明，等于回到 006 之前的表结构
    $schema = preg_replace('~^	if_skip_this$~m', '', $schema);
    $schema = preg_replace('~^\s*public_code\s+TEXT,\s*$~mi', '', $schema);
    // 注释里提一句的列存在与否不影响，只要语句真少了这一列
    assertTrue($schema !== $before, '测试本身要能剥掉 public_code');
    $pdo = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec($schema);
    // 老库里手工存两行（没有编号列可写）
    $pdo->exec("INSERT INTO customers (id, name, status, owner_id) VALUES (7, '老库客户', 'active', 1)");
    $pdo->exec("INSERT INTO leads (id, title, status, value, owner_id) VALUES (7, '老库线索', 'new', 0, 1)");
    $GLOBALS['CODE_OLD_DB'] = $file;
    return $file;
}

function test_the_migrations_backfill_and_stay_idempotent(): void
{
    $file = codeOldDb('olddb');
    $old = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    assertEquals(0, (int) $old->query("SELECT COUNT(*) FROM pragma_table_info('customers') WHERE name = 'public_code'")->fetchColumn(),
        '伪老库确实没有编号列');

    [$code, $out] = codeMigrate($file);
    assertEquals(0, $code, "老库升级必须成功：\n" . textClip($out, 500));
    assertContains('applied: 006_add_public_code_to_core_tables.sql', $out, '老库上 006 是真正执行的（补列）');
    assertContains('applied: 007_backfill_public_code.sql', $out, '007 负责回填与唯一索引');

    $db = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    assertEquals('CUS-000007', $db->query("SELECT public_code FROM customers WHERE id = 7")->fetchColumn(),
        '历史行被回填，规则与 Model::publicCode() 一致');
    assertEquals('LEAD-000007', $db->query("SELECT public_code FROM leads WHERE id = 7")->fetchColumn());
    foreach (['customers', 'leads', 'deals'] as $t) {
        assertEquals(0, (int) $db->query("SELECT COUNT(*) FROM {$t} WHERE public_code IS NULL OR public_code = ''")->fetchColumn(),
            "{$t} 不应残留空编号");
        assertEquals(1, (int) $db->query("SELECT COUNT(*) FROM pragma_index_list('{$t}') WHERE name = 'uidx_{$t}_public_code'")->fetchColumn(),
            "{$t}.public_code 上有唯一索引");
    }

    // 重复编号写不进去
    $hit = 0;
    try {
        $db->exec("INSERT INTO leads (id, public_code, title, status) VALUES (77, 'LEAD-000007', '撞号', 'new')");
    } catch (Throwable $e) {
        $hit = 1;
    }
    assertEquals(1, $hit, '唯一索引真的在拦');

    // 再跑一次：幂等，不重放
    [$code2, $out2] = codeMigrate($file);
    assertEquals(0, $code2, "第二次迁移必须干净：\n" . textClip($out2, 400));
    assertTrue(!str_contains($out2, 'applied: 006') && !str_contains($out2, 'applied: 007'), '增量不会重放');
    assertContains('Migration complete', $out2);
    assertEquals('CUS-000007', $db->query("SELECT public_code FROM customers WHERE id = 7")->fetchColumn(), '重跑不会把编号改飞');

    @unlink($file);
}

/** 列清单（另开连接读文件，避开本进程的旧结构缓存） */
function codeColumns(string $file, string $table): array
{
    $pdo = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->prepare('SELECT name FROM pragma_table_info(:t)');
    $stmt->execute([':t' => $table]);
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');
}
/** 用真实 migrate.php 跑一个库文件，返回 [退出码, 输出] */
function codeMigrate(string $file): array
{
    $out = [];
    $code = 1;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(BASE_PATH . '/database/migrate.php')
        . ' --db=' . escapeshellarg($file) . ' 2>&1', $out, $code);
    return [(int) $code, implode("\n", $out)];
}

function test_the_ai_works_with_codes_end_to_end(): void
{
    $_SESSION['user_id'] = 1;
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
    $custId = (int) (new Customer())->create(['name' => 'AI 编号客户', 'company' => '编号公司',
        'status' => 'active', 'owner_id' => 1]);
    $leadId = (int) (new Lead())->create(['title' => 'AI 编号线索', 'status' => 'new', 'owner_id' => 1]);
    $custCode = 'CUS-' . sprintf('%06d', $custId);
    $leadCode = 'LEAD-' . sprintf('%06d', $leadId);

    // 搜索结果把编号端出来
    $search = Ai::execute(Ai::validatePlan([['tool' => 'search_records', 'args' => ['q' => '编号公司']]], 1)['actions'], 1);
    $rows = $search['results'][0]['rows'] ?? [];
    assertTrue(count($rows) > 0, '搜到了');
    assertContains($custCode, (string) $rows[0]['detail'], '结果里第一样东西就是编号');
    assertEquals($custCode, $rows[0]['code']);

    // 用编号更新，落到的是同一行
    $upd = Ai::execute(Ai::validatePlan([['tool' => 'update_customer',
        'args' => ['customer_id' => $custCode, 'source_country' => 'Vietnam']]], 1)['actions'], 1);
    assertEquals(1, $upd['applied']);
    assertEquals('Vietnam', (new Customer())->find($custId)['source_country']);
    assertContains($custCode, (string) $upd['results'][0]['message'], '回执也说编号，人和 AI 看的是同一个东西');

    // 用编号删除
    $del = Ai::validatePlan([['tool' => 'delete_lead',
        'args' => ['lead_id' => $leadCode, 'confirm' => true, 'reason' => '按编号删']], ], 1);
    assertEquals(false, $del['blocked'], implode('；', $del['errors']));
    Ai::execute($del['actions'], 1);
    assertEquals(0, (int) Database::connection()->query('SELECT COUNT(*) FROM leads WHERE id = ' . $leadId)->fetchColumn(),
        '按编号删掉了');

    // 编一个不像真的的编号：被拒，且告诉你怎么拿到真编号
    $fake = Ai::validatePlan([['tool' => 'delete_lead',
        'args' => ['lead_id' => 'LEAD-999999', 'confirm' => true, 'reason' => 'x']]], 1);
    assertTrue($fake['blocked']);
    $err = implode('；', $fake['errors']);
    assertContains('找不到对应记录', $err);
    assertContains('LEAD-', $err, '提示里给出编号长什么样');
    assertContains('search_records', $err, '并告诉它先去搜');
}

function test_codes_reach_the_prompt_and_the_pages(): void
{
    $_SESSION['user_id'] = 1;
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
    (new Lead())->create(['title' => '提示词里的编号', 'status' => 'new', 'owner_id' => 1]);

    $map = AppMap::forPrompt();
    assertContains('CUS-', $map);
    assertContains('LEAD-', $map);
    assertContains('优先用编号', $map);
    assertTrue(textLength($map) < 900, '加了编号说明仍然很短（' . textLength($map) . ' 字）');

    $digest = Ai::contextDigest(1, 5);
    assertContains('编号|标题', $digest, '数据快照表头也改成编号');
    assertContains('LEAD-', $digest, '快照里给的是编号');

    $ctx = AppMap::toText();
    assertContains('public_code', $ctx, '数据字典里能看到这一列（自动来自数据库）');
    assertContains('稳定编号', $ctx);
}

runCase();
