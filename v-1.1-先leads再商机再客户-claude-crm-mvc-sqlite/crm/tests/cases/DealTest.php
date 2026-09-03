<?php
/** Deal model tests — pipeline queries, archive lifecycle, order lookups. */
require __DIR__ . '/../bootstrap.php';

function seedCustomer(string $name): array
{
    $c = new Customer();
    $id = $c->create(['name' => $name, 'status' => 'active']);
    return $c->find((int) $id);
}

function seedDeal(int $customerId, array $overrides = []): array
{
    $d = new Deal();
    $id = $d->create(array_merge([
        'title'       => 'Deal ' . bin2hex(random_bytes(3)),
        'customer_id' => $customerId,
        'value'       => 10000,
        'stage'       => 'open',
        'owner_id'    => 1,
    ], $overrides));
    return $d->find((int) $id);
}

function test_deal_stage_aggregates(): void
{
    $cust = seedCustomer('Agg C');
    $d = new Deal();
    seedDeal((int) $cust['id'], ['stage' => 'proposal', 'value' => 3000]);
    seedDeal((int) $cust['id'], ['stage' => 'negotiation', 'value' => 7000]);
    seedDeal((int) $cust['id'], ['stage' => 'closed_won', 'value' => 9999]);

    assertEquals(3000.0, $d->sumValueByStage('proposal'), 'sum by stage');
    assertEquals(10000.0, $d->openPipelineValue(), 'open pipeline excludes won');
}

function test_deal_archive_unarchive(): void
{
    $cust = seedCustomer('Arc C');
    $row = seedDeal((int) $cust['id']);
    $d = new Deal();

    // Not archived initially, appears in board.
    $board = $d->allWithCustomer();
    assertEquals(1, count($board), 'deal on board');
    assertEquals('Arc C', $board[0]['customer_name'], 'customer joined');

    assertTrue($d->archive((int) $row['id']), 'archive ok');
    $archived = $d->find((int) $row['id']);
    assertEquals(1, (int) $archived['archived'], 'archived flag set');
    assertTrue(!empty($archived['archived_at']), 'archived_at set');
    assertEquals(0, count($d->allWithCustomer()), 'gone from board');
    assertEquals(1, count($d->allArchived()), 'appears in archived list');

    assertTrue($d->unarchive((int) $row['id']), 'unarchive ok');
    assertEquals(0, (int) $d->find((int) $row['id'])['archived'], 'back on board');
}

function test_deal_orders_lookup(): void
{
    $cust = seedCustomer('Ord C');
    $deal = seedDeal((int) $cust['id']);

    $orderModel = new Order();
    $orderId = $orderModel->create([
        'order_number' => $orderModel->generateOrderNumber(),
        'deal_id'      => (int) $deal['id'],
        'customer_id'  => (int) $cust['id'],
        'title'        => 'Order for deal',
        'amount'       => 100,
        'status'       => 'pending',
        'owner_id'     => 1,
    ]);

    $orders = (new Deal())->orders((int) $deal['id']);
    assertEquals(1, count($orders), 'orders() finds the deal order');
    assertEquals((int) $orderId, (int) $orders[0]['id'], 'correct order returned');
    assertEquals('Ord C', $orders[0]['customer_name'], 'customer joined on order');
}

runCase();
