<?php
/** Lead model tests — status transitions, lost/reactivate lifecycle, listing.
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
require __DIR__ . '/../bootstrap.php';

function seedLead(array $overrides = []): array
{
    $lead = new Lead();
    $id = $lead->create(array_merge([
        'title'         => 'Lead ' . bin2hex(random_bytes(3)),
        'contact_name'  => 'Contact',
        'contact_email' => 'c@test.local',
        'value'         => 5000,
        'status'        => 'new',
        'owner_id'      => 1,
    ], $overrides));
    return $lead->find((int) $id);
}

function test_lead_create_and_find(): void
{
    $row = seedLead();
    assertTrue((int) $row['id'] > 0, 'lead created');
    assertEquals('new', $row['status'], 'default status new');

    $l = new Lead();
    $again = $l->find((int) $row['id']);
    assertEquals($row['title'], $again['title'], 'find returns the lead');
}

function test_lead_mark_lost_and_reactivate(): void
{
    $row = seedLead();
    $l = new Lead();
    assertTrue($l->markAsLost((int) $row['id'], 'competitor'), 'markAsLost ok');

    $lost = $l->find((int) $row['id']);
    assertEquals('lost', $lost['status'], 'status becomes lost');
    assertEquals('competitor', $lost['lost_reason'], 'reason saved');
    assertTrue(!empty($lost['lost_at']), 'lost_at timestamp set');

    assertTrue($l->reactivate((int) $row['id']), 'reactivate ok');
    $back = $l->find((int) $row['id']);
    assertEquals('contacted', $back['status'], 'reactivate -> contacted');
    assertEquals(null, $back['lost_reason'], 'lost_reason cleared');
    assertEquals(null, $back['lost_at'], 'lost_at cleared');
}

function test_lead_lost_reason_options(): void
{
    $opts = Lead::lostReasonOptions();
    assertTrue(isset($opts['competitor'], $opts['no_need'], $opts['other']), 'has known keys');
    assertEquals('已选竞品', Lead::lostReasonLabel('competitor'), 'label resolves');
}

function test_lead_listing_and_count(): void
{
    $l = new Lead();
    seedLead(['status' => 'new']);
    seedLead(['status' => 'contacted']);
    seedLead(['status' => 'qualified']);
    seedLead(['status' => 'lost']);

    assertEquals(4, $l->countLeads(''), 'countLeads total');
    assertEquals(1, $l->countLeads('contacted'), 'countLeads by status');
    assertEquals(1, $l->countByStatus('lost'), 'countByStatus');

    $page1 = $l->allLeads('', 1, 2);
    assertEquals(2, count($page1), 'pagination page size honored');

    // Owner name is joined in the list query.
    $newOnly = $l->allLeads('new', 1, 10);
    assertEquals('Admin User', $newOnly[0]['owner_name'], 'owner join present');
}

/** 线索列表关键词搜索：跨列、可与状态筛选叠加、% 当字面量 */
function test_lead_keyword_search(): void
{
    $l = new Lead();
    seedLead(['title' => 'Solar inverter 10kW', 'contact_name' => 'Alice', 'status' => 'new']);
    seedLead(['title' => 'Wind turbine 5kW', 'contact_name' => 'Bob', 'status' => 'contacted']);
    seedLead(['title' => 'Solar panel 450W', 'contact_name' => 'Carol', 'status' => 'qualified']);
    seedLead(['title' => '100% 优惠专线', 'status' => 'new']);

    assertEquals(2, (int) $l->countLeads('', 'Solar'), '关键词命中 2 条（标题）');
    $found = $l->allLeads('', 1, 15, 'Solar');
    assertEquals(2, count($found), 'allLeads 与 countLeads 同一份 WHERE');

    assertEquals(1, count($l->allLeads('', 1, 15, 'Alice')), '关键词可搜联系人');

    // 状态筛选与关键词叠加是 AND，不是互斥
    $both = $l->allLeads('new', 1, 15, 'Solar');
    assertEquals(1, count($both), '状态 + 关键词同时生效');
    assertEquals('Solar inverter 10kW', $both[0]['title'], '命中那条 new + Solar');

    // '%' 按字面量搜，不会变成“匹配整表”（转义路径与客户搜索同源）
    assertEquals(1, count($l->allLeads('', 1, 15, '%')), '百分号按字面量搜索');
    assertEquals(1, (int) $l->countLeads('', '%'), 'count 与列表口径一致');
}

runCase();
