<?php
/**
 * Autoloader regression tests.
 *
 * The exact failure mode these guard against: views call model static helpers
 * directly (Order::statusLabel, OrderItem::unitOptions, Attachment::fileIcon…),
 * and previously every controller had to manually "pre-load" those classes
 * before rendering a view — if one forgot, PHP threw a fatal "Class not found".
 *
 * Here we render REAL view partials with NO controller involvement. The
 * autoloader must resolve every model class on first static reference.
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
require __DIR__ . '/../bootstrap.php';

/** Render a view file with a given variable scope (like Controller::view does). */
function renderViewFile(string $viewFile, array $vars): string
{
    extract($vars);
    ob_start();
    require $viewFile;
    return ob_get_clean();
}

function test_deals_form_partial_renders_without_preload(): void
{
    // Seed a customer + deal through the models (this loads Customer/Deal),
    // but deliberately do NOT instantiate OrderItem / Order.
    $c = new Customer();
    $custId = $c->create(['name' => 'Form C', 'status' => 'active']);
    $d = new Deal();
    $dealId = $d->create(['title' => 'Form Deal', 'customer_id' => (int) $custId, 'owner_id' => 1]);
    $deal = $d->find((int) $dealId);

    $customers = $c->all('name ASC');

    $html = renderViewFile(BASE_PATH . '/app/views/deals/_form.php', [
        'deal' => $deal,
        'customers' => $customers,
    ]);

    // deals/_form.php calls OrderItem::unitOptions() — must not fatal.
    assertContains('选择客户', $html, '_form rendered');
    assertContains('商机名称', $html, 'form fields present');
    assertContains('CRM', $html, 'no crash on static unitOptions'); // weak, see below
}

function test_orders_form_partial_renders_without_preload(): void
{
    // Order static helpers (statusOptions etc.) are only reached in views
    // via Order::statusOptions() — render the real partial to force them.
    $c = new Customer();
    $custId = $c->create(['name' => 'OrderForm C', 'status' => 'active']);
    $customers = $c->all('name ASC');

    $o = new Order();
    $order = $o->find(0) ?: ['id' => 0, 'status' => 'pending', 'payment_status' => 'unpaid']; // not used by _form directly

    $html = renderViewFile(BASE_PATH . '/app/views/orders/_form.php', [
        'order' => $order,
        'customers' => $customers,
        'deals' => [],
        'orderNumber' => 'ORD-2026-TEST',
        'items' => [],
    ]);

    assertContains('订单编号', $html, 'order form rendered');
    assertContains('待确认', $html, 'Order::statusOptions loaded via autoloader');
    assertContains('未付款', $html, 'payment status options loaded');
}

function test_attachments_partial_renders_without_model_in_view(): void
{
    // Seed an attachment through the model (loads Attachment), then render the
    // partial purely with the passed-in rows — the view must NOT instantiate
    // models or hit the DB itself anymore.
    $att = new Attachment();
    $attId = $att->create([
        'related_type' => 'deal', 'related_id' => 999,
        'filename' => 'spec.pdf', 'original_name' => '合同.pdf',
        'mime_type' => 'application/pdf', 'file_size' => 4096, 'uploaded_by' => 1,
    ]);
    $rows = $att->byRelated('deal', 999);

    $html = renderViewFile(BASE_PATH . '/app/views/partials/_attachments.php', [
        'attachments' => $rows,
        'relatedType' => 'deal',
        'relatedId'   => 999,
        'csrf'        => csrf(),
    ]);

    assertContains('附件', $html, 'partial header');
    assertContains('合同.pdf', $html, 'row rendered from controller-provided data');
    assertContains('bi-file-earmark-pdf', $html, 'icon helper loaded via autoloader');
}

function test_static_helpers_reachable_in_cold_process(): void
{
    // Straight-up static calls that views make; in a fresh case process these
    // classes have never been instantiated, so this only works via autoloading.
    assertEquals('已确认', Order::statusLabel('confirmed'), 'Order autoloads');
    assertTrue(in_array('件', OrderItem::unitOptions(), true), 'OrderItem autoloads');
    assertEquals('已选竞品', Lead::lostReasonLabel('competitor'), 'Lead autoloads');
    assertContains('.xlsx', Attachment::acceptAttribute(), 'Attachment autoloads');
}

/**
 * This PHP build has no mbstring, and a bare "PHP + SQLite" install usually
 * doesn't either: a stray mb_*() call is a fatal inside a view, not a warning.
 * core/helpers.php is the single compat layer (textLength / textTrim / textClip),
 * and it must keep every mb_* behind an explicit function_exists() check.
 */
function test_app_code_does_not_depend_on_mbstring(): void
{
    $names = ['mb_strlen(', 'mb_substr(', 'mb_strimwidth(', 'mb_strtoupper(', 'mb_strtolower(', 'mb_convert_encoding(', 'mb_internal_encoding('];

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_PATH, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $isCompatLayer = strtolower($file->getBasename()) === 'helpers.php';
        $src = (string) file_get_contents($path);
        foreach (explode("
", str_replace("
", "
", $src)) as $n => $line) {
            $code = ltrim($line);
            if (str_starts_with($code, '*') || str_starts_with($code, '/*')
                || str_starts_with($code, '//') || str_starts_with($code, '#')) {
                continue;                   // docblocks may name the functions they replace
            }
            foreach ($names as $call) {
                if (!str_contains($line, $call)) {
                    continue;
                }
                $name = rtrim($call, '(');
                if ($isCompatLayer) {
                    // The compat layer may call it, but only behind a guard.
                    assertTrue(str_contains($src, "function_exists('" . $name . "')"),
                        'helpers.php uses ' . $name . ' without ever checking function_exists for it');
                    continue;
                }
                assertTrue(false, basename($path) . ':' . ($n + 1) . ' calls ' . $name
                    . ' directly — use textLength()/textTrim()/textClip() instead');
            }
        }
    }

    assertTrue(true, 'no unguarded mb_* usage outside the compat layer');
}

runCase();
