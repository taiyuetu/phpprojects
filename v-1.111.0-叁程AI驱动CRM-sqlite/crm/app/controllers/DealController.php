<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

class DealController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $search = trim($_GET['q'] ?? '');
        $deals = $this->model('Deal')->allWithCustomer($search);

        // Group by stage for a simple kanban-style board.
        // 丢单(closed_lost)商机会自动归档，不占用看板列。
        $stages = ['open' => [], 'proposal' => [], 'negotiation' => [], 'closed_won' => []];
        foreach ($deals as $deal) {
            if (!array_key_exists($deal['stage'], $stages)) {
                continue; // 兜底：忽略归档/未归档列表之外的阶段
            }
            $stages[$deal['stage']][] = $deal;
        }

        $this->view('deals/index', ['stages' => $stages, 'search' => $search]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $this->view('deals/create', [
            'customers' => $this->model('Customer')->all('name ASC'),
            'csrf' => $this->csrfToken(),
            'old' => [],
            'errors' => [],
            'products' => (new Product())->pickList(),
            // 新建时明细必然是空的；表单里的“至少一行空行”由局部负责
            'items' => [],
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        [$data, $errors] = $this->validate($_POST);
        $this->errorsFromItems($_POST, $errors);

        // 商机从“进行中”开始：成交/丢单是流转的终点，只能由 update() 到达。
        // 不然会出现“没有订单的成交商机”这类半成品（成交必须自动生成订单，
        // 而那条生成链路在 update() 里，create 时不具备）。
        if (in_array($data['stage'] ?? '', ['closed_won', 'closed_lost'], true)) {
            $errors[] = '新建商机不能直接选「成交」或「丢单」：请先用「进行中」创建，再在编辑页推进到成交 / 丢单。';
        }

        if ($errors) {
            $this->view('deals/create', [
                'customers' => $this->model('Customer')->all('name ASC'),
                'csrf' => $this->csrfToken(),
                'old' => $_POST,
                'errors' => $errors,
                'products' => (new Product())->pickList(),
                'items' => $this->itemsEcho($_POST),
            ]);
            return;
        }

        // 建档 + 阶段时间戳 + 明细草稿一次落库：任一失败就整体回滚。
        $items = $this->normalizedItems($_POST);
        $data['owner_id'] = $_SESSION['user_id'];
        $stageAt = 'stage_' . $data['stage'] . '_at';
        $data[$stageAt] = date('Y-m-d H:i:s');

        $dealModel = $this->model('Deal');
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $dealId = $dealModel->create($data);
            // 未成交阶段的行先存草稿：后面打开编辑页还在，推进到成交时再转成订单
            $dealModel->setDraftItems($dealId, $items);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            $this->setFlash('error', '商机创建失败：' . $e->getMessage());
            $this->redirect('/deals');
            return;
        }

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

        // 明细的单一事实来源：已成交的商机看它自动生成的订单；还没成交的看草稿。
        // 否则用户看到的行要么是空的（没建单），要么一保存就把已有的行清空了。
        $linkedOrders = $dealModel->orders((int) $id);
        $itemsForForm = $linkedOrders
            ? $this->model('OrderItem')->byOrder((int) $linkedOrders[0]['id'])
            : $dealModel->draftItems((int) $id);

        $this->view('deals/edit', [
            'deal' => $deal,
            'customers' => $this->model('Customer')->all('name ASC'),
            'orders' => $linkedOrders,
            'attachments' => $this->model('Attachment')->byRelated('deal', (int) $id),
            'csrf' => $this->csrfToken(),
            'errors' => [],
            'products' => (new Product())->pickList(),
            'items' => $itemsForForm,
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
        $this->errorsFromItems($_POST, $errors);

        if ($errors) {
            $this->view('deals/edit', [
                'deal' => array_merge(['id' => $id], $_POST),
                'customers' => $this->model('Customer')->all('name ASC'),
                'orders' => $dealModel->orders((int) $id),
                'attachments' => $this->model('Attachment')->byRelated('deal', (int) $id),
                'csrf' => $this->csrfToken(),
                'errors' => $errors,
                'products' => (new Product())->pickList(),
                'items' => $this->itemsEcho($_POST),
            ]);
            return;
        }

        $items = $this->normalizedItems($_POST);
        // 这张商机名下有没有已生成的订单（曾经成交过）——决定明细写到哪里
        $linkedOrders = $dealModel->orders((int) $id);
        $itemModel = $this->model('OrderItem');

        // 改字段 + 阶段流转 + 明细落库（草稿或订单）是一笔事务：任一步失败全部回滚。
        $db = Database::connection();
        $db->beginTransaction();
        try {
            // Auto-record stage transition time
            if ($oldDeal['stage'] !== $data['stage']) {
                $stageCol = 'stage_' . $data['stage'] . '_at';
                $data[$stageCol] = date('Y-m-d H:i:s');
            }

            $dealModel->update((int) $id, $data);

            if ($data['stage'] === 'closed_won') {
                // 成交：行落到订单里（首次成交自动建单；已成交过的商机同步进它的订单），
                // 草稿清空。商机保留在看板"成交"列供查阅，不归档。
                if (!$linkedOrders) {
                    $this->createOrderFromDealItems($oldDeal, $items);
                } elseif ($items) {
                    // 没提交任何行 = 用户没动行，不重写订单（避免把“按商机金额兜底”的旧单清成 0）
                    $itemModel->syncItems((int) $linkedOrders[0]['id'], $items);
                }
                $dealModel->setDraftItems((int) $id, []);
                $wasFirstClose = $oldDeal['stage'] !== 'closed_won';
                $this->setFlash('success', $wasFirstClose
                    ? '商机已成交并自动转为订单。'
                    : '商机已更新，订单明细已同步。');
                $db->commit();
                $this->redirect($wasFirstClose ? '/orders' : '/deals');
                return;
            }

            if ($data['stage'] === 'closed_lost') {
                // 丢单：归档、移出看板；草稿没有意义，清掉
                if ($oldDeal['stage'] !== 'closed_lost') {
                    $dealModel->archive((int) $id);
                }
                $dealModel->setDraftItems((int) $id, []);
                $this->setFlash('success', '商机已标记为丢单并归档。');
                $db->commit();
                $this->redirect('/deals/archived');
                return;
            }

            // 未成交：有订单（被重新打开过的成交商机）→ 行同步进订单；
            // 没有订单 → 行存成草稿，下次打开还在。空提交视为没动行，不重写。
            if ($linkedOrders) {
                if ($items) {
                    $itemModel->syncItems((int) $linkedOrders[0]['id'], $items);
                }
            } else {
                $dealModel->setDraftItems((int) $id, $items);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            $this->setFlash('error', '商机更新失败：' . $e->getMessage());
            $this->redirect('/deals');
            return;
        }

        $this->setFlash('success', '商机已更新。');
        $this->redirect('/deals');
    }

    // --------------------------------------------------------- 明细 helpers

    /** 提交的明细行统一校验入口（任何阶段有行都要校验，页面与 AI 同一套 normalizeRows）。 */
    private function errorsFromItems(array $post, array &$errors): void
    {
        $dealItemCheck = OrderItem::normalizeRows(
            is_array($post['items'] ?? null) ? (array) $post['items'] : [],
            (string) Setting::get('items_require_product', '1') !== '0'
        );
        $errors = array_merge($errors, $dealItemCheck['errors']);
    }

    /** 提交的明细行 → 洗好可落库的行。 */
    private function normalizedItems(array $post): array
    {
        return OrderItem::normalizeRows(
            is_array($post['items'] ?? null) ? (array) $post['items'] : [],
            (string) Setting::get('items_require_product', '1') !== '0'
        )['items'];
    }

    /** 首次成交：以商机明细创建订单，并继承商机附件。 */
    private function createOrderFromDealItems(array $deal, array $items): int
    {
        $orderModel = $this->model('Order');

        // 行是成交事实：有行按行合计；没行退回商机金额兜底（与旧行为一致）
        $total = 0;
        foreach ($items as $item) {
            $total += $item['quantity'] * $item['unit_price'];
        }

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

        if (!empty($items)) {
            $this->model('OrderItem')->syncItems($orderId, $items);
        }

        $this->ensureOrderAttachments((int) $deal['id'], $orderId);
        return $orderId;
    }

    /** 商机附件继承到订单（已有则不动）。 */
    private function ensureOrderAttachments(int $dealId, int $orderId): void
    {
        $existingAtts = $this->model('Attachment')->byRelated('order', $orderId);
        if (empty($existingAtts)) {
            $this->model('Attachment')->copyTo('deal', $dealId, 'order', $orderId, (int) $_SESSION['user_id']);
        }
    }

    public function destroy(string $id): void
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

        // 解绑订单 + 删商机是一笔事务：中途失败不能留下“订单还指着已删除商机”的悬空状态
        $db = Database::connection();
        $db->beginTransaction();
        try {
            // Delete orders for this deal (set deal_id to null)
            $orderModel = $this->model('Order');
            foreach ($orderModel->byDeal((int) $id) as $order) {
                $orderModel->update((int) $order['id'], ['deal_id' => null]);
            }

            $dealModel->delete((int) $id);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            $this->setFlash('error', '商机删除失败：' . $e->getMessage());
            $this->redirect('/deals');
            return;
        }

        $this->setFlash('success', '商机已删除。');
        $this->redirect('/deals');
    }

    /** 已归档商机列表 */
    public function archived(): void
    {
        $this->requireAuth();

        $search = trim($_GET['q'] ?? '');
        $deals = $this->model('Deal')->allArchived($search);

        $this->view('deals/archived', ['deals' => $deals, 'search' => $search]);
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

    /**
     * 校验失败重回表单时，把用户刚填的行原样贴回去（别让人重打一遍明细）。
     *
     * 与 OrderController::itemsEcho() 同构；额外透传 legacy_name / legacy_price——
     * 升级前的历史行不改动时靠这两个字段在 OrderItem::normalizeRows() 里原样保留。
     */
    private function itemsEcho(array $post): array
    {
        $out = [];
        foreach ((array) ($post['items'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (trim((string) ($row['product_name'] ?? '')) === ''
                && trim((string) ($row['product_id'] ?? '')) === ''
                && trim((string) ($row['legacy_name'] ?? '')) === '') {
                continue;                                 // 全空行
            }
            $out[] = [
                'product_id'   => trim((string) ($row['product_id'] ?? '')),
                'product_name' => trim((string) ($row['product_name'] ?? '')),
                'sku'          => trim((string) ($row['sku'] ?? '')),
                'quantity'     => (string) ($row['quantity'] ?? '1'),
                'unit_price'   => (string) ($row['unit_price'] ?? '0'),
                'unit'         => trim((string) ($row['unit'] ?? '件')),
                'notes'        => trim((string) ($row['notes'] ?? '')),
                'legacy_name'  => trim((string) ($row['legacy_name'] ?? '')),
                'legacy_price' => trim((string) ($row['legacy_price'] ?? '')),
            ];
        }
        return $out;
    }

    private function validate(array $input): array
    {
        // 规则白名单在 Deal::$fields（见 core/Fields.php），控制器只做委托
        return (new Deal())->sanitizeInput($input);
    }
}
