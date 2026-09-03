<?php
/** Attachment tests — byRelated join, copyTo (deal->order), remove, helpers. */
require __DIR__ . '/../bootstrap.php';

function seedAttachmentData(): array
{
    // A deal + an order under the same customer, then one attachment on the deal.
    $c = new Customer();
    $custId = $c->create(['name' => 'AttachCustomer']);

    $dealModel = new Deal();
    $dealId = $dealModel->create([
        'title' => 'Attach Deal', 'customer_id' => (int) $custId, 'owner_id' => 1,
    ]);

    $orderModel = new Order();
    $orderId = $orderModel->create([
        'order_number' => $orderModel->generateOrderNumber(),
        'customer_id'  => (int) $custId,
        'title'        => 'Attach Order',
        'owner_id'     => 1,
    ]);

    $att = new Attachment();
    $attId = $att->create([
        'related_type'  => 'deal',
        'related_id'    => (int) $dealId,
        'filename'      => 'photo.jpg',
        'original_name' => '产品图片.jpg',
        'mime_type'     => 'image/jpeg',
        'file_size'     => 2048,
        'uploaded_by'   => 1,
    ]);

    return [
        'deal_id' => (int) $dealId,
        'order_id' => (int) $orderId,
        'att_id'  => (int) $attId,
    ];
}

function test_attachment_by_related_with_uploader(): void
{
    $data = seedAttachmentData();
    $att = new Attachment();

    $rows = $att->byRelated('deal', $data['deal_id']);
    assertEquals(1, count($rows), 'deal has one attachment');
    assertEquals('Admin User', $rows[0]['uploader_name'], 'uploader joined via users');
    assertEquals('产品图片.jpg', $rows[0]['original_name'], 'original name round-trips');

    assertEquals(0, count($att->byRelated('order', $data['order_id'])), 'order starts empty');
}

/** Regression test for the earlier bug: copying deal attachments to the order. */
function test_attachment_copy_deal_to_order(): void
{
    $data = seedAttachmentData();
    $att = new Attachment();

    $copied = $att->copyTo('deal', $data['deal_id'], 'order', $data['order_id'], 1);
    assertEquals(1, $copied, 'one attachment copied');

    $dealRows = $att->byRelated('deal', $data['deal_id']);
    $orderRows = $att->byRelated('order', $data['order_id']);
    assertEquals(1, count($dealRows), 'original kept');
    assertEquals(1, count($orderRows), 'order received a copy');

    // Filename is reused (same physical file, new DB row).
    assertEquals($dealRows[0]['filename'], $orderRows[0]['filename'], 'physical file reused');
    assertEquals('Admin User', $orderRows[0]['uploader_name'], 'new uploader recorded');
}

function test_attachment_remove(): void
{
    $data = seedAttachmentData();
    $att = new Attachment();

    assertTrue($att->remove($data['att_id']), 'remove ok');
    assertEquals(0, count($att->byRelated('deal', $data['deal_id'])), 'row deleted');
    // remove() is idempotent / returns false when gone
    assertEquals(false, $att->remove($data['att_id']), 'second remove returns false');
}

function test_attachment_static_helpers(): void
{
    // Public pure helpers used directly by views.
    assertEquals('bi-file-earmark-pdf', Attachment::fileIcon('application/pdf'), 'pdf icon');
    assertEquals('bi-file-earmark-image', Attachment::fileIcon('image/png'), 'image icon');
    assertTrue(Attachment::isImage('image/webp'), 'webp is an image');
    assertTrue(!Attachment::isImage('application/pdf'), 'pdf is not an image');
    assertContains('.pdf', Attachment::acceptAttribute(), 'accept attr lists pdf');
    assertContains(' KB', Attachment::formatSize(2048), 'size formatted');
}

runCase();
