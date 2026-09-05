<?php
/** Order / OrderItem model tests — numbering, item sync, amount rollups.
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
require __DIR__ . '/../bootstrap.php';

function seedOrderCustomer(): array
{
    $c = new Customer();
    $id = $c->create(['name' => 'OrderCustomer ' . bin2hex(random_bytes(3)), 'status' => 'active']);
    return $c->find((int) $id);
}

function makeOrder(int $customerId): array
{
    $o = new Order();
    $id = $o->create([
        'order_number' => $o->generateOrderNumber(),
        'customer_id'  => $customerId,
        'title'        => 'Test order',
        'amount'       => 0,
        'status'       => 'pending',
        'payment_status' => 'unpaid',
        'order_date'   => date('Y-m-d'),
        'owner_id'     => 1,
    ]);
    return ['id' => (int) $id, 'model' => $o];
}

function test_order_number_sequence(): void
{
    $cust = seedOrderCustomer();
    $o = new Order();
    $n1 = $o->generateOrderNumber();
    $id1 = $o->create([
        'order_number' => $n1, 'customer_id' => (int) $cust['id'], 'title' => 'A', 'owner_id' => 1,
    ]);
    $n2 = $o->generateOrderNumber();
    assertTrue($n1 !== $n2, 'numbers differ');
    assertTrue((int) substr($n2, -3) === (int) substr($n1, -3) + 1, 'sequence increments');
    assertEquals('ORD-' . date('Y') . '-', substr($n1, 0, 9), 'year prefix');
}

function test_order_item_sync_and_totals(): void
{
    $cust = seedOrderCustomer();
    $order = makeOrder((int) $cust['id']);

    $itemModel = new OrderItem();
    $items = [
        ['product_name' => 'Widget', 'quantity' => 2, 'unit_price' => 10, 'unit' => '件'],
        ['product_name' => 'Gadget', 'quantity' => 1, 'unit_price' => 15.5, 'unit' => '件'],
    ];
    $itemModel->syncItems($order['id'], $items);

    $saved = $itemModel->byOrder($order['id']);
    assertEquals(2, count($saved), 'two items inserted');
    assertEquals(35.5, $itemModel->totalByOrder($order['id']), 'item subtotals sum');

    // syncItems also rewrites the order amount.
    $fresh = $order['model']->find($order['id']);
    assertEquals(35.5, (float) $fresh['amount'], 'order amount updated by syncItems');

    // Re-sync with fewer items removes stale rows.
    $itemModel->syncItems($order['id'], [
        ['product_name' => 'OnlyOne', 'quantity' => 1, 'unit_price' => 5],
    ]);
    assertEquals(1, count($itemModel->byOrder($order['id'])), 'resync replaces items');
}

function test_order_queries_and_status_helpers(): void
{
    $cust = seedOrderCustomer();
    $order = makeOrder((int) $cust['id']);
    $o = $order['model'];

    assertEquals(1, $o->countOrders(''), 'countOrders');
    assertEquals(1, $o->countByStatus('pending'), 'countByStatus pending');

    $list = $o->allOrders('', 1, 15);
    assertEquals($order['id'], (int) $list[0]['id'], 'allOrders newest first');
    assertTrue(str_starts_with($list[0]['customer_name'], 'OrderCustomer'), 'customer join');

    $details = $o->findWithDetails($order['id']);
    assertEquals($order['id'], (int) $details['id'], 'findWithDetails');
    assertEquals('pending', $details['status'], 'details row has status');

    assertEquals('待确认', Order::statusLabel('pending'), 'status label');
    assertEquals('已付款', Order::paymentStatusLabel('paid'), 'payment label');
    assertTrue(!isset(Order::statusOptions()['paid']), 'statusOptions excludes payment statuses');

    // Customer-scoped list
    assertEquals(1, count($o->byCustomer((int) $cust['id'])), 'byCustomer');

    // totalAmount rollup across all orders
    assertEquals(0.0, $o->totalAmount('cancelled'), 'no cancelled orders yet');
}

/** 生成器必须跳过已占用的编号；numberInUse 能排除自身（改自己订单号时不算撞车） */
function test_generate_order_number_skips_taken_and_number_in_use(): void
{
    $cust = seedOrderCustomer();
    $o = new Order();
    $n1 = $o->generateOrderNumber();
    $id1 = $o->create([
        'order_number' => $n1, 'customer_id' => (int) $cust['id'],
        'title' => 'Occupied number', 'owner_id' => 1,
    ]);
    assertTrue($o->numberInUse($n1), '已存在的编号能被查出来');
    assertTrue(!$o->numberInUse($n1, (int) $id1), '排除自身后不算占用');
    $n2 = $o->generateOrderNumber();
    assertTrue($n1 !== $n2, '生成器不会把已占用的编号再吐出来');
}

/**
 * 明细行局部必须给每一行递增索引。
 * 回归：两行都渲染成 items[0][...] 时，提交后同名表单字段互相覆盖，只剩最后一行。
 */
function test_items_fields_render_unique_row_indexes(): void
{
    $rows = [
        ['product_id' => '11', 'product_name' => '第一行 轴承6206', 'quantity' => '2', 'unit_price' => '10', 'unit' => '件'],
        ['product_id' => '12', 'product_name' => '第二行 轴承6306', 'quantity' => '1', 'unit_price' => '20', 'unit' => '件'],
    ];
    ob_start();
    $items = $rows;
    $products = [];
    include APP_PATH . '/views/partials/_items_fields.php';
    $html = (string) ob_get_clean();

    assertTrue(substr_count($html, 'name="items[0][product_id]"') === 1, '第一行使用索引 0');
    assertTrue(substr_count($html, 'name="items[1][product_id]"') === 1, '第二行使用索引 1');
    assertContains('第一行 轴承6206', $html, '两行内容都在');
    assertContains('第二行 轴承6306', $html);
}

runCase();
