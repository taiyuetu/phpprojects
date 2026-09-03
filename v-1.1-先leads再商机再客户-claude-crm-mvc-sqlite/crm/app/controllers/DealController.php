<?php

class DealController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $deals = $this->model('Deal')->allWithCustomer();

        // Group by stage for a simple kanban-style board.
        $stages = ['open' => [], 'proposal' => [], 'negotiation' => [], 'closed_won' => [], 'closed_lost' => []];
        foreach ($deals as $deal) {
            $stages[$deal['stage']][] = $deal;
        }

        $this->view('deals/index', ['stages' => $stages]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $this->model('OrderItem'); // Load class for static unitOptions() in _form.php

        $this->view('deals/create', [
            'customers' => $this->model('Customer')->all('name ASC'),
            'csrf' => $this->csrfToken(),
            'old' => [],
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        [$data, $errors] = $this->validate($_POST);

        if ($errors) {
            $this->model('OrderItem'); // Load class for static unitOptions() in _form.php
            $this->view('deals/create', [
                'customers' => $this->model('Customer')->all('name ASC'),
                'csrf' => $this->csrfToken(),
                'old' => $_POST,
                'errors' => $errors,
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
        $this->model('OrderItem'); // Load class for static unitOptions() in _form.php
        $deal = $dealModel->find((int) $id);
        if (!$deal) {
            $this->setFlash('error', '商机不存在。');
            $this->redirect('/deals');
            return;
        }

        $this->view('deals/edit', [
            'deal' => $deal,
            'customers' => $this->model('Customer')->all('name ASC'),
            'orders' => $dealModel->orders((int) $id),
            'attachments' => $this->model('Attachment')->byRelated('deal', (int) $id),
            'csrf' => $this->csrfToken(),
            'errors' => [],
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

        if ($errors) {
            $this->view('deals/edit', [
                'deal' => array_merge(['id' => $id], $_POST),
                'customers' => $this->model('Customer')->all('name ASC'),
                'orders' => $dealModel->orders((int) $id),
                'csrf' => $this->csrfToken(),
                'errors' => $errors,
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
        // 当商机变为 closed_won（成交）时，自动创建订单并归档商机
        // ==========================================
        if ($oldDeal['stage'] !== 'closed_won' && $data['stage'] === 'closed_won') {
            $this->autoCreateOrderFromDeal($oldDeal, $_POST);

            // 已转为订单，归档商机（保留历史数据，订单 deal_id 关联不丢失）
            $dealModel->archive((int) $id);

            $this->setFlash('success', '商机已成交并转为订单，商机已自动归档。');
            $this->redirect('/orders');
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
        $existingOrders = $orderModel->byDeal((int) $deal['id']);
        if (!empty($existingOrders)) {
            return; // Already has order, skip
        }

        // Parse items from POST: items[0][product_name], items[0][quantity], etc.
        $items = [];
        if (!empty($post['items']) && is_array($post['items'])) {
            foreach ($post['items'] as $item) {
                $name = trim($item['product_name'] ?? '');
                if ($name === '') continue;
                $items[] = [
                    'product_name' => $name,
                    'sku'          => trim($item['sku'] ?? '') ?: null,
                    'quantity'     => max(1, (float) ($item['quantity'] ?? 1)),
                    'unit_price'   => max(0, (float) ($item['unit_price'] ?? 0)),
                    'unit'         => trim($item['unit'] ?? '件') ?: '件',
                    'notes'        => trim($item['notes'] ?? '') ?: null,
                ];
            }
        }

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
            $orderModel->update((int) $order['id'], ['deal_id' => null]);
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

        $this->view('deals/archived', ['deals' => $deals]);
    }

    /** 取消归档 */
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

        $dealModel->unarchive((int) $id);
        $this->setFlash('success', '商机已取消归档，恢复到看板中。');
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
            $this->setFlash('error', $result['error']);
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
