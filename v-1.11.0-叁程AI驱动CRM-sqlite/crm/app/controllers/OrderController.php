<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

class OrderController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $status = trim($_GET['status'] ?? '');
        $search = trim($_GET['q'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 15;

        $orderModel = $this->model('Order');
        $total = $orderModel->countOrders($status, $search);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);

        $this->view('orders/index', [
            'orders'      => $orderModel->allOrders($status, $page, $perPage, $search),
            'status'      => $status,
            'search'      => $search,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'total'       => $total,
            'totalAmount' => $orderModel->totalAmount(),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $this->view('orders/create', [
            'customers' => $this->model('Customer')->all('name ASC'),
            'deals'     => $this->model('Deal')->allWithCustomer(),
            'orderNumber' => $this->model('Order')->generateOrderNumber(),
            'csrf'      => $this->csrfToken(),
            'old'       => [],
            'errors'    => [],
            // 明细的“新增商品”只能从商品库里选，所以目录随表单一起带出去
            'products' => (new Product())->pickList(),
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        [$data, $errors] = $this->validate($_POST);

        // 明细校验放在建单之前：不然一张“头对、行错”的订单会先落库再报错，
        // 用户回头看到的是一张没有明细的订单。
        $parsed = $this->parseItems($_POST);
        $errors = array_merge($errors, $parsed['errors']);

        if ($errors) {
            $this->view('orders/create', [
                'customers' => $this->model('Customer')->all('name ASC'),
                'deals'     => $this->model('Deal')->allWithCustomer(),
                'orderNumber' => $_POST['order_number'] ?? '',
                'csrf'      => $this->csrfToken(),
                'old'       => $_POST,
                'errors'    => $errors,
                'items'     => $this->itemsEcho($_POST),
                // 明细的“新增商品”只能从商品库里选，所以目录随表单一起带出去
                'products' => (new Product())->pickList(),
            ]);
            return;
        }

        $data['owner_id'] = $_SESSION['user_id'];
        $orderModel = $this->model('Order');

        // 编号是表单上的“建议值”：撞上已存在的编号（并发同时开单、或有人手改）
        // 就换一个新号再存，绝不落到 500（DB 的 UNIQUE 是最后防线，不是唯一防线）。
        if ($data['order_number'] === '' || $orderModel->numberInUse($data['order_number'])) {
            $data['order_number'] = $orderModel->generateOrderNumber();
        }
        try {
            $orderId = $orderModel->create($data);
        } catch (PDOException $e) {
            if (stripos((string) $e->getMessage(), 'unique') === false) {
                throw $e;
            }
            // 极端竞态：预检后仍然撞了 UNIQUE —— 换号重试一次
            $data['order_number'] = $orderModel->generateOrderNumber();
            $orderId = $orderModel->create($data);
        }

        // Sync items
        if (!empty($parsed['items'])) {
            $this->model('OrderItem')->syncItems($orderId, $parsed['items']);
        }

        $this->setFlash('success', '订单已创建。');
        $this->redirect('/orders/' . $orderId);
    }

    public function show(string $id): void
    {
        $this->requireAuth();

        $order = $this->model('Order')->findWithDetails((int) $id);
        if (!$order) {
            $this->setFlash('error', '订单不存在。');
            $this->redirect('/orders');
            return;
        }

        $this->view('orders/show', [
            'order'       => $order,
            'items'       => $this->model('OrderItem')->byOrder((int) $id),
            'attachments' => $this->model('Attachment')->byRelated('order', (int) $id),
            'csrf'        => $this->csrfToken(),
                ]);
    }

    public function edit(string $id): void
    {
        $this->requireAuth();

        $order = $this->model('Order')->find((int) $id);
        if (!$order) {
            $this->setFlash('error', '订单不存在。');
            $this->redirect('/orders');
            return;
        }

        $this->view('orders/edit', [
            'order'       => $order,
            'customers'   => $this->model('Customer')->all('name ASC'),
            'deals'       => $this->model('Deal')->allWithCustomer(),
            'items'       => $this->model('OrderItem')->byOrder((int) $id),
            'attachments' => $this->model('Attachment')->byRelated('order', (int) $id),
            'csrf'        => $this->csrfToken(),
            'errors'      => [],
            // 明细的“新增商品”只能从商品库里选，所以目录随表单一起带出去
            'products' => (new Product())->pickList(),
        ]);
    }

    public function update(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $orderModel = $this->model('Order');
        $order = $orderModel->find((int) $id);
        if (!$order) {
            $this->setFlash('error', '订单不存在。');
            $this->redirect('/orders');
            return;
        }

        [$data, $errors] = $this->validate($_POST);

        $parsed = $this->parseItems($_POST);
        $errors = array_merge($errors, $parsed['errors']);

        if ($errors) {
            $this->view('orders/edit', [
                'order'     => array_merge(['id' => $id], $_POST),
                'customers' => $this->model('Customer')->all('name ASC'),
                'deals'     => $this->model('Deal')->allWithCustomer(),
                'items'     => $this->itemsEcho($_POST),
                'attachments' => $this->model('Attachment')->byRelated('order', (int) $id),
                'csrf'      => $this->csrfToken(),
                'errors'    => $errors,
                // 明细的“新增商品”只能从商品库里选，所以目录随表单一起带出去
                'products' => (new Product())->pickList(),
            ]);
            return;
        }

        // 编号不能撞到别的订单（自己的旧号不算）；撞了就换一个新号，不 500。
        if ($data['order_number'] === '' || $orderModel->numberInUse($data['order_number'], (int) $order['id'])) {
            $data['order_number'] = $orderModel->generateOrderNumber();
        }
        try {
            $orderModel->update((int) $id, $data);
        } catch (PDOException $e) {
            if (stripos((string) $e->getMessage(), 'unique') === false) {
                throw $e;
            }
            $data['order_number'] = $orderModel->generateOrderNumber();
            $orderModel->update((int) $id, $data);
        }

        // Sync items
        $this->model('OrderItem')->syncItems((int) $id, $parsed['items']);

        $this->setFlash('success', '订单已更新。');
        $this->redirect('/orders/' . $id);
    }

    public function destroy(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $orderModel = $this->model('Order');
        $order = $orderModel->find((int) $id);
        if (!$order) {
            $this->setFlash('error', '订单不存在。');
            $this->redirect('/orders');
            return;
        }

        $orderModel->delete((int) $id);
        $this->setFlash('success', '订单已删除。');
        $this->redirect('/orders');
    }

    /** Create order from a won deal. */
    public function createFromDeal(string $dealId): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $deal = $this->model('Deal')->find((int) $dealId);
        if (!$deal) {
            $this->setFlash('error', '商机不存在。');
            $this->redirect('/deals');
            return;
        }

        // Check if order already exists for this deal
        $existingOrders = $this->model('Order')->byDeal((int) $dealId);
        if (!empty($existingOrders)) {
            // Ensure attachments are copied even for previously created orders
            $existingOrderId = (int) $existingOrders[0]['id'];
            $existingAtts = $this->model('Attachment')->byRelated('order', $existingOrderId);
            if (empty($existingAtts)) {
                $this->model('Attachment')->copyTo('deal', (int) $deal['id'], 'order', $existingOrderId, (int) $_SESSION['user_id'        ]);
            }
            $this->setFlash('success', '该商机已有订单。');
            $this->redirect('/orders/' . $existingOrderId);
            return;
        }

        $orderModel = $this->model('Order');
        $orderId = $orderModel->create([
            'order_number' => $orderModel->generateOrderNumber(),
            'deal_id'      => $deal['id'],
            'customer_id'  => $deal['customer_id'],
            'title'        => $deal['title'] . ' - 订单',
            'amount'       => $deal['value'],
            'status'       => 'pending',
            'payment_status' => 'unpaid',
            'order_date'   => date('Y-m-d'),
            'owner_id'     => $_SESSION['user_id'],
                ]);

        // Copy attachments from deal to order
        $this->model('Attachment')->copyTo('deal', (int) $deal['id'], 'order', (int) $orderId, (int) $_SESSION['user_id'        ]);

        $this->setFlash('success', '订单已从商机创建。');
        $this->redirect('/orders/' . $orderId);
    }

    /** Parse items from POST data. */
    /**
     * 表单明细 → 可落库的行 + 校验错误。
     *
     * 商品的规则全部收在 OrderItem::normalizeRows()（页面与 AI 共用一处），
     * 所以不会出现“页面上选得了、AI 却写不进”这种两套规矩。
     *
     * @return array{items:array<int,array<string,mixed>>,errors:array<int,string>}
     */
    private function parseItems(array $post): array
    {
        $raw = is_array($post['items'] ?? null) ? (array) $post['items'] : [];
        return OrderItem::normalizeRows($raw, (string) Setting::get('items_require_product', '1') !== '0');
    }

    /** 校验失败重回表单时，把用户刚填的行原样贴回去（别让人重打一遍明细） */
    private function itemsEcho(array $post): array
    {
        $out = [];
        foreach ((array) ($post['items'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (trim((string) ($row['product_name'] ?? '')) === '' && trim((string) ($row['product_id'] ?? '')) === '') {
                continue;
            }
            $out[] = [
                'product_id'   => trim((string) ($row['product_id'] ?? '')),
                'product_name' => trim((string) ($row['product_name'] ?? '')),
                'sku'          => trim((string) ($row['sku'] ?? '')),
                'quantity'     => (string) ($row['quantity'] ?? '1'),
                'unit_price'   => (string) ($row['unit_price'] ?? '0'),
                'unit'         => trim((string) ($row['unit'] ?? '件')),
                'notes'        => trim((string) ($row['notes'] ?? '')),
            ];
        }
        return $out;
    }

    /**
     * Upload attachment for an order.
     */
    public function uploadAttachment(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $order = $this->model('Order')->find((int) $id);
        if (!$order) {
            $this->setFlash('error', '订单不存在。');
            $this->redirect('/orders');
            return;
        }

        if (empty($_FILES['attachment']) || $_FILES['attachment']['error'] === UPLOAD_ERR_NO_FILE) {
            $this->setFlash('error', '请选择要上传的文件。');
            $this->redirect('/orders/' . $id);
            return;
        }

        $result = $this->model('Attachment')->upload(
            $_FILES['attachment'],
            'order',
            (int) $id,
            (int) $_SESSION['user_id']
        );

        if ($result['success']) {
            $this->setFlash('success', '附件上传成功。');
        } else {
            $this->setFlash('error', $result['error'        ]);
        }

        $this->redirect('/orders/' . $id);
    }

    /**
     * Delete attachment from an order.
     */
    public function deleteAttachment(string $orderId, string $attachmentId): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $order = $this->model('Order')->find((int) $orderId);
        if (!$order) {
            $this->setFlash('error', '订单不存在。');
            $this->redirect('/orders');
            return;
        }

        $attachmentModel = $this->model('Attachment');
        $attachment = $attachmentModel->find((int) $attachmentId);

        if (!$attachment || $attachment['related_type'] !== 'order' || (int) $attachment['related_id'] !== (int) $orderId) {
            $this->setFlash('error', '附件不存在。');
            $this->redirect('/orders/' . $orderId);
            return;
        }

        $attachmentModel->remove((int) $attachmentId);
        $this->setFlash('success', '附件已删除。');
        $this->redirect('/orders/' . $orderId);
    }

    private function validate(array $input): array
    {
        // 规则白名单在 Order::$fields（见 core/Fields.php），控制器只做委托
        return (new Order())->sanitizeInput($input);
    }
}
