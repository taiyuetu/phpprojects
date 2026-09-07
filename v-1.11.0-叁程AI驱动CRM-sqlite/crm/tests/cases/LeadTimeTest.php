<?php
/**
 * 线索时间（leads.lead_time）：AI 添加线索必须把它写上，而且写的是上海时间。
 *
 * 真实报障：让 AI 助手添加线索，它只填标题/联系人，线索时间这一栏永远空着。
 * 这一版守住三件事：
 *   1. AI 没给 → 系统按上海时间落当前时刻（Ai::kindInfo 的 defaults）；
 *   2. AI 给了 → 中文说法（昨天下午3点半）、datetime-local（…T14:30）、带时区的 ISO
 *      一律换算成上海时间的 Y-m-d H:i:s（appDateTime），认不出来就地报错而不是写脏数据；
 *   3. 页面上那个 <input type="datetime-local"> 回填/再提交不会把已存的时间洗成空。
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
require __DIR__ . '/../bootstrap.php';

function ltAdmin(): int
{
    $_SESSION['user_id'] = 1;
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
    (new Setting())->setMany(['ai_enabled' => '1', 'ai_provider' => 'mock', 'ai_mode' => 'preview',
                              'ai_allow_delete' => '1'], 1);
    Setting::flushCache();
    return 1;
}

/** 跑一个工具并返回单条结果（校验失败时把错误消息带回来，交给断言） */
function ltRun(string $tool, array $args, int $userId): array
{
    $checked = Ai::validatePlan([['tool' => $tool, 'args' => $args]], $userId);
    if (!empty($checked['errors'])) {
        return ['ok' => false, 'message' => implode('；', $checked['errors'])];
    }
    $run = Ai::execute($checked['actions'], $userId);
    return (array) ($run['results'][0] ?? ['ok' => false, 'message' => '没有结果']);
}

function ltLeadTime(int $id): string
{
    return (string) ((new Lead())->find($id)['lead_time'] ?? '');
}

/** 1. 最核心的一条：AI 建线索，线索时间不得留空 */
function test_ai_created_lead_is_stamped_with_lead_time(): void
{
    $user = ltAdmin();
    $r = ltRun('create_lead', ['title' => '埃及客户询价轮毂', 'source' => 'WhatsApp'], $user);
    assertTrue((bool) ($r['ok'] ?? false), 'AI 建线索要成功：' . (string) ($r['message'] ?? ''));

    $got = ltLeadTime((int) $r['id']);
    assertTrue($got !== '', '线索时间不能为空（本条就是本次报障）');
    assertTrue((bool) preg_match('~^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$~', $got),
        '落库必须是上海时间的 Y-m-d H:i:s，实测 ' . $got);
    // 与“上海时间的现在”相差 2 分钟以内（跨秒、跨分都算正常，跨小时就是时区写错了）
    $diff = abs(strtotime($got) - strtotime(appNow()));
    assertTrue($diff <= 120, '线索时间应等于当前上海时间，偏差 ' . $diff . ' 秒（' . $got . ' vs ' . appNow() . '）');
}

/** 2. 时区是显式的：就算服务器默认时区被改成纽约，写进去的仍是上海时间 */
function test_lead_time_is_shanghai_regardless_of_php_default_timezone(): void
{
    $user = ltAdmin();
    $wantBefore = appNow();
    date_default_timezone_set('America/New_York');
    try {
        $r = ltRun('create_lead', ['title' => '默认时区被改走的线索'], $user);
        assertTrue((bool) ($r['ok'] ?? false), 'AI 建线索要成功：' . (string) ($r['message'] ?? ''));
        $got = ltLeadTime((int) $r['id']);
        // date() 在纽约时区下会给出比上海早 12 小时的“现在”，所以这里只能用上海时间比对
        $diff = abs(strtotime($got) - strtotime($wantBefore));
        assertTrue($diff <= 120, '纽约时区下仍写上海时间，实测 ' . $got . '（本地 date() 是 '
            . date('Y-m-d H:i:s') . '）');
        assertEquals('Asia/Shanghai', (new DateTimeZone(APP_TIMEZONE))->getName(), '业务时区常量是上海');
    } finally {
        date_default_timezone_set('Asia/Shanghai');
    }
}

/** 3. AI 给了时间就尊重它：中文相对说法、datetime-local、带时区的 ISO 都换算到上海 */
function test_model_supplied_lead_time_is_converted_to_shanghai(): void
{
    $user = ltAdmin();
    $yesterday = date('Y-m-d', strtotime(appNow('Y-m-d') . ' -1 day'));

    $cases = [
        ['昨天下午3点半', $yesterday . ' 15:30:00', '中文相对日期 + 中文钟点'],
        ['2026-09-07T06:30:00Z', '2026-09-07 14:30:00', 'UTC 的 ISO 要 +8 换算成上海'],
        ['2026-09-07T14:30', '2026-09-07 14:30:00', '页面控件那种写法'],
        ['2026-09-07 09:05:12', '2026-09-07 09:05:12', '标准写法原样保留'],
    ];
    foreach ($cases as [$raw, $want, $why]) {
        $r = ltRun('create_lead', ['title' => '带时间的线索：' . $why, 'lead_time' => $raw], $user);
        assertTrue((bool) ($r['ok'] ?? false), "{$why} 建线索应成功：{$raw} → " . (string) ($r['message'] ?? ''));
        assertEquals($want, ltLeadTime((int) $r['id']), "{$why}（输入 {$raw}）");
    }
}

/** 4. 认不出来的时间不许脏写：校验层就拒，而不是落一栏自由文本 */
function test_unparsable_lead_time_is_rejected(): void
{
    $user = ltAdmin();
    $r = ltRun('create_lead', ['title' => '时间写不明白的线索', 'lead_time' => '上次开会那阵子'], $user);
    assertTrue(!($r['ok'] ?? true), '非法线索时间必须被拒绝');
    assertContains('无法识别为时间', (string) $r['message'], '错误消息要告诉模型怎么写');

    $r2 = ltRun('create_lead', ['title' => '时间留空的线索', 'lead_time' => ''], $user);
    assertTrue((bool) ($r2['ok'] ?? false), '空串按没给处理：' . (string) ($r2['message'] ?? ''));
    $t = ltLeadTime((int) $r2['id']);
    assertTrue($t !== '', '留空也不会漏掉线索时间，实测 ' . $t);
}

/** 5. 字段引擎：lead_time 是 datetime 而不是自由文本，提示词里也带着这个形式 */
function test_lead_time_is_typed_as_datetime_everywhere(): void
{
    ltAdmin();
    $spec = Ai::fieldsFor('leads')['lead_time'] ?? [];
    assertEquals('datetime', (string) ($spec['type'] ?? ''), '线索时间的参数类型');
    assertEquals('线索时间', (string) ($spec['label'] ?? ''), '中文名');
    assertContains('上海时间', (string) ($spec['hint'] ?? ''), '提示里要写清时区');

    $prompt = Ai::systemPrompt();
    assertContains('lead_time:datetime', $prompt, '提示词要把这一栏标成 datetime');
    assertContains('不写就按上海时间', (string) (Ai::tools()['create_lead']['hint'] ?? ''),
        'create_lead 的工具说明要写清不填就自动落当前时间');
}

/** 6. 页面那条路：表单值入库、入库值回填控件，两边格式必须对得上 */
function test_the_web_form_round_trips_lead_time(): void
{
    [$data, $errors] = (new Lead())->sanitizeInput([
        'title' => '页面新建的线索',
        'lead_time' => '2026-09-07T14:30',        // <input type="datetime-local"> 的提交值
    ]);
    assertEquals([], $errors, '表单校验通过');
    assertEquals('2026-09-07 14:30:00', (string) $data['lead_time'], '提交值统一成上海时间标准写法');

    // 回填：库里那行值必须能被 datetime-local 认出来，否则用户改个手机号就把线索时间清空了
    assertEquals('2026-09-07T14:30', appDateTimeLocal($data['lead_time']), '控件回填格式');
    assertEquals('', appDateTimeLocal(null), '没有时间就交空串，别交一个控件认不出的字符串');
    assertEquals('2026-09-07T14:30', appDateTimeLocal('2026-09-07T14:30'), '历史遗留的 T 写法也吃得下');

    $edit = (new Lead())->sanitizeInput(['id' => 1, 'title' => '编辑时没动这一栏',
                                         'lead_time' => appDateTimeLocal('2026-09-07 14:30:00')]);
    assertEquals('2026-09-07 14:30:00', (string) $edit[0]['lead_time'], '编辑再提交不改变时间');
}

/** 7. 历史数据：迁移把空的线索时间按 created_at(UTC)+8h 补齐 */
function test_migration_backfills_existing_lead_times(): void
{
    $file = BASE_PATH . '/database/migrations/015_backfill_lead_time.sql';
    assertTrue(is_file($file), '存在 015 回填迁移');
    $sql = (string) file_get_contents($file);
    assertContains("created_at, '+8 hours'", preg_replace('~\s+~', ' ', $sql), '按 created_at +8 小时换算');
    assertContains("WHERE lead_time IS NULL OR lead_time = ''", $sql, '只补空值，重复执行安全');

    // 真跑一遍 migrate：演示数据那两条线索（建表时不给 lead_time）必须不再是空的
    $db = sys_get_temp_dir() . '/crm_leadtime_' . getmypid() . '_' . bin2hex(random_bytes(3)) . '.sqlite';
    try {
        $out = [];
        $code = 0;
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(BASE_PATH . '/database/migrate.php')
            . ' --db=' . escapeshellarg($db) . ' 2>&1', $out, $code);
        assertEquals(0, $code, 'migrate.php 成功：' . implode("\n", $out));
        $pdo = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $blank = (int) $pdo->query("SELECT COUNT(*) FROM leads WHERE lead_time IS NULL OR lead_time = ''")
                           ->fetchColumn();
        assertEquals(0, $blank, '迁移后没有一条线索的线索时间是空的');
        $row = $pdo->query("SELECT created_at, lead_time FROM leads ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $want = (new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(APP_TIMEZONE))->format('Y-m-d H:i:s');
        assertEquals($want, (string) $row['lead_time'], '回填值 = created_at(UTC) 换算成上海时间');
    } finally {
        @unlink($db);
    }
}

runCase();
