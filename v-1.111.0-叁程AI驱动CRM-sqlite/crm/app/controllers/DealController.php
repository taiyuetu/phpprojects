<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

class DealController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $deals = $this->model('Deal')->allWithCustomer();

        // Group by stage for a simple kanban-style board.
        // 丢单(closed_lost)商机会自动归档，不占用看板列。
        $stages = ['open' => [], 'proposal' => [], 'negotiation' => [], 'closed_won' => []];
        foreach ($deals as $deal) {
            if (!array_key_exists($deal['stage'], $stages)) {
                continue; // 兜底：忽略归档/未归档列表之外的阶段
            }
            $stages[$deal['stage']][] = $deal;
        }

        $this->view('deals/index', ['stages' => $stages        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        // 新建时明细必然是空的；表单里的“至少一行空行”由局部负责
        $itemsForForm = [];

        $this->view('deals/create', [
            'customers' => $this->model('Customer')->all('name ASC'),
            'csrf' => $this->csrfToken(),
            'old' => [],
            'errors' => [],
                    'products' => (new Product())->pickList(),
                    'items'     => $itemsForForm,

        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        [$data, $errors] = $this->validate($_POST);
        // 成交阶段的明细是在这里一起提交的，校验错误必须并进同一个表单回显，
        // 否则“选了不存在的商品”会被静默丢掉，用户以为保存了。
        if (strtolower(trim((string) ($_POST['stage'] ?? ''))) === 'closed_won') {
            $dealItemCheck = OrderItem::normalizeRows(
                is_array($_POST['items'] ?? null) ? (array) $_POST['items'] : [],
                (string) Setting::get('items_require_product', '1') !== '0'
            );
            $errors = array_merge($errors, $dealItemCheck['errors']);
        }

        if ($errors) {
            $this->view('deals/create', [
                'customers' => $this->model('Customer')->all('name ASC'),
                'csrf' => $this->csrfToken(),
                'old' => $_POST,
                'errors' => $errors,
                            'products' => (new Product())->pickList(),
                            'items'     => $itemsForForm,

        ]);
            return;
        }

        $data['owner_id'] = $_SESSION['user_id'];
        $data['stage_open_at'] = date('Y-m-d H:i:s'); // new deals start as 'open'
        $this->model('Deal')->create($data);

        $this->setFlash('success', '商机已创建。');
        $this->redirect('/deals');
    }

    public function edit(string $id): void
    {
        $this->requireAuth();

        $dealModel = $this->model('Deal');
        $deal = $dealModel->find((int) $id);
        if (!$deal) {
            $this->setFlash('error', '商机不存在。');
            $this->redirect('/deals');
            return;
        }

        // 成交后的商机会带一张自动生成的订单：编辑时把它的明细带回来，
        // 否则用户看到的是空明细，一保存就把已有的行清空了。
        $linkedOrders = $dealModel->orders((int) $id);
        $itemsForForm = $linkedOrders
            ? $this->model('OrderItem')->byOrder((int) $linkedOrders[0]['id'])
            : [];

        $this->view('deals/edit', [
            'deal' => $deal,
            'customers' => $this->model('Customer')->all('name ASC'),
            'orders' => $linkedOrders,
            'attachments' => $this->model('Attachment')->byRelated('deal', (int) $id),
            'csrf' => $this->csrfToken(),
            'errors' => [],
                    'products' => (new Product())->pickList(),
                    'items'     => $itemsForForm,

        ]);
    }

    public function update(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $dealModel = $this->model('Deal');
        $oldDeal = $dealModel->find((int) $id);
        if (!$oldDeal) {
            $this->setFlash('error', '商机不存在。');
            $this->redirect('/deals');
            return;
        }

        [$data, $errors] = $this->validate($_POST);
        // 成交阶段的明细是在这里一起提交的，校验错误必须并进同一个表单回显，
        // 否则“选了不存在的商品”会被静默丢掉，用户以为保存了。
        if (strtolower(trim((string) ($_POST['stage'] ?? ''))) === 'closed_won') {
            $dealItemCheck = OrderItem::normalizeRows(
                is_array($_POST['items'] ?? null) ? (array) $_POST['items'] : [],
                (string) Setting::get('items_require_product', '1') !== '0'
            );
            $errors = array_merge($errors, $dealItemCheck['errors']);
        }

        if ($errors) {
            $this->view('deals/edit', [
                'deal' => array_merge(['id' => $id], $_POST),
                'customers' => $this->model('Customer')->all('name ASC'),
                'orders' => $dealModel->orders((int) $id),
                'attachments' => $this->model('Attachment')->byRelated('deal', (int) $id),
                'csrf' => $this->csrfToken(),
                'errors' => $errors,
                            'products' => (new Product())->pickList(),
                            'items'     => $itemsForForm,

        ]);
            return;
        }

        // Auto-record stage transition time
        if ($oldDeal['stage'] !== $data['stage']) {
            $stageCol = 'stage_' . $data['stage'] . '_at';
            $data[$stageCol] = date('Y-m-d H:i:s');
        }

        $dealModel->update((int) $id, $data);

        // ==========================================
        // 商机变更为 closed_won（成交）：自动创建订单，但不再归档，
        // 商机保留在看板的"成交"列供查阅。
        // ==========================================
        if ($oldDeal['stage'] !== 'closed_won' && $data['stage'] === 'closed_won') {
            $this->autoCreateOrderFromDeal($oldDeal, $_POST);

            $this->setFlash('success', '商机已成交并自动转为订单。');
            $this->redirect('/orders');
            return;
        }

        // ==========================================
        // 商机变更为 closed_lost（丢单）：自动归档，移出看板
        // ==========================================
        if ($oldDeal['stage'] !== 'closed_lost' && $data['stage'] === 'closed_lost') {
            $dealModel->archive((int) $id);

            $this->setFlash('success', '商机已标记为丢单并归档。');
            $this->redirect('/deals/archived');
            return;
        }

        $this->setFlash('success', '商机已更新。');
        $this->redirect('/deals');
    }

    /**
     * 商机成交时自动创建订单 + 商品明细
     */
    private function autoCreateOrderFromDeal(array $deal, array $post): void
    {
        $orderModel = $this->model('Order');
        $itemModel = $this->model('OrderItem');

        // Check if order already exists for this deal
        $existingOrders = $orderModel->byDeal((int) $deal['id'        ]);
        if (!empty($existingOrders)) {
            // Ensure attachments are copied even for previously created orders
            $existingOrderId = (int) $existingOrders[0]['id'];
            $existingAtts = $this->model('Attachment')->byRelated('order', $existingOrderId);
            if (empty($existingAtts)) {
                $this->model('Attachment')->copyTo('deal', (int) $deal['id'], 'order', $existingOrderId, (int) $_SESSION['user_id'        ]);
            }
            return;
        }

        // 明细走与订单同一套校验（OrderItem::normalizeRows）：
        // 商机成交生成的订单，商品也得是从商品库里选出来的那一个。
        $parsed = OrderItem::normalizeRows(
            is_array($post['items'] ?? null) ? (array) $post['items'] : [],
            (string) Setting::get('items_require_product', '1') !== '0'
        );
        $items = $parsed['items'];

        // Calculate total from items, fallback to deal value
        $total = 0;
        foreach ($items as $item) {
            $total += $item['quantity'] * $item['unit_price'];
        }

        // Create order
        $orderId = $orderModel->create([
            'order_number'    => $orderModel->generateOrderNumber(),
            'deal_id'         => $deal['id'],
            'customer_id'     => $deal['customer_id'],
            'title'           => $deal['title'] . ' - 订单',
            'amount'          => $total > 0 ? $total : $deal['value'],
            'status'          => 'pending',
            'payment_status'  => 'unpaid',
            'order_date'      => date('Y-m-d'),
            'owner_id'        => $_SESSION['user_id'],
                ]);

        // Create items if provided
        if (!empty($items)) {
            $itemModel->syncItems($orderId, $items);
        }

        // Copy attachments from deal to order
        $this->model('Attachment')->copyTo(
            'deal',
            (int) $deal['id'],
            'order',
            (int) $orderId,
            (int) $_SESSION['user_id']
        );
    }

    public function destroy(string $id): void
    {
        $this->requireAuth();

        $dealModel = $this->model('Deal');
        $deal = $dealModel->find((int) $id);
        if (!$deal) {
            $this->setFlash('error', '商机不存在。');
            $this->redirect('/deals');
            return;
        }

        // Delete orders for this deal (set deal_id to null)
        $orderModel = $this->model('Order');
        foreach ($orderModel->byDeal((int) $id) as $order) {
            $orderModel->update((int) $order['id'], ['deal_id' => null        ]);
        }

        $dealModel->delete((int) $id);
        $this->setFlash('success', '商机已删除。');
        $this->redirect('/deals');
    }

    /** 已归档商机列表 */
    public function archived(): void
    {
        $this->requireAuth();

        $deals = $this->model('Deal')->allArchived();

        $this->view('deals/archived', ['deals' => $deals        ]);
    }

    /** 取消归档 —— 丢单商机恢复后回到"进行中"列继续跟进 */
    public function unarchive(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $dealModel = $this->model('Deal');
        $deal = $dealModel->find((int) $id);
        if (!$deal) {
            $this->setFlash('error', '商机不存在。');
            $this->redirect('/deals');
            return;
        }

        // 看板没有"丢单"列：model::unarchive() 会把商机重置回"进行中"(open)。
        $dealModel->unarchive((int) $id);

        $this->setFlash('success', '商机已恢复，回到"进行中"列。');
        $this->redirect('/deals');
    }

    /**
     * Upload attachment for a deal.
     */
    public function uploadAttachment(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $deal = $this->model('Deal')->find((int) $id);
        if (!$deal) {
            $this->setFlash('error', '商机不存在。');
            $this->redirect('/deals');
            return;
        }

        if (empty($_FILES['attachment'])) {
            $this->setFlash('error', '请选择要上传的文件。');
            $this->redirect('/deals/' . $id . '/edit');
            return;
        }

        $result = $this->model('Attachment')->upload(
            $_FILES['attachment'],
            'deal',
            (int) $id,
            (int) $_SESSION['user_id']
        );

        if ($result['success']) {
            $this->setFlash('success', '附件上传成功。');
        } else {
            $this->setFlash('error', $result['error'        ]);
        }

        $this->redirect('/deals/' . $id . '/edit');
    }

    /**
     * Delete attachment from a deal.
     */
    public function deleteAttachment(string $dealId, string $attachmentId): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $deal = $this->model('Deal')->find((int) $dealId);
        if (!$deal) {
            $this->setFlash('error', '商机不存在。');
            $this->redirect('/deals');
            return;
        }

        $attachmentModel = $this->model('Attachment');
        $attachment = $attachmentModel->find((int) $attachmentId);

        if (!$attachment || $attachment['related_type'] !== 'deal' || (int) $attachment['related_id'] !== (int) $dealId) {
            $this->setFlash('error', '附件不存在。');
            $this->redirect('/deals/' . $dealId . '/edit');
            return;
        }

        $attachmentModel->remove((int) $attachmentId);
        $this->setFlash('success', '附件已删除。');
        $this->redirect('/deals/' . $dealId . '/edit');
    }

    private function validate(array $input): array
    {
        $validStages = ['open', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];

        $data = [
            'title'       => trim($input['title'] ?? ''),
            'customer_id' => (int) ($input['customer_id'] ?? 0),
            'value'       => is_numeric($input['value'] ?? null) ? (float) $input['value'] : 0,
            'stage'       => in_array($input['stage'] ?? '', $validStages, true) ? $input['stage'] : 'open',
            'close_date'  => !empty($input['close_date']) ? $input['close_date'] : null,
        ];

        $errors = [];
        if ($data['title'] === '') {
            $errors[] = '商机名称不能为空。';
        }
        if ($data['customer_id'] <= 0) {
            $errors[] = '请选择一个客户。';
        }

        return [$data, $errors];
    }
}
