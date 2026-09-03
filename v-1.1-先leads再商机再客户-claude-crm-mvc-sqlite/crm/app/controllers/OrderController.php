<?php

class OrderController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $status = trim($_GET['status'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 15;

        $orderModel = $this->model('Order');
        $total = $orderModel->countOrders($status);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);

        $this->view('orders/index', [
            'orders'      => $orderModel->allOrders($status, $page, $perPage),
            'status'      => $status,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'total'       => $total,
            'totalAmount' => $orderModel->totalAmount(),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->model('OrderItem'); // Load class for static unitOptions() in _form.php

        $this->view('orders/create', [
            'customers' => $this->model('Customer')->all('name ASC'),
            'deals'     => $this->model('Deal')->allWithCustomer(),
            'orderNumber' => $this->model('Order')->generateOrderNumber(),
            'csrf'      => $this->csrfToken(),
            'old'       => [],
            'errors'    => [],
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        [$data, $errors] = $this->validate($_POST);

        if ($errors) {
            $this->view('orders/create', [
                'customers' => $this->model('Customer')->all('name ASC'),
                'deals'     => $this->model('Deal')->allWithCustomer(),
                'orderNumber' => $_POST['order_number'] ?? '',
                'csrf'      => $this->csrfToken(),
                'old'       => $_POST,
                'errors'    => $errors,
            ]);
            return;
        }

        $data['owner_id'] = $_SESSION['user_id'];
        $orderId = $this->model('Order')->create($data);

        // Sync items
        $items = $this->parseItems($_POST);
        if (!empty($items)) {
            $this->model('OrderItem')->syncItems($orderId, $items);
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
        $this->model('OrderItem'); // Load class for static unitOptions() in _form.php

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

        if ($errors) {
            $this->view('orders/edit', [
                'order'     => array_merge(['id' => $id], $_POST),
                'customers' => $this->model('Customer')->all('name ASC'),
                'deals'     => $this->model('Deal')->allWithCustomer(),
                'items'     => $this->model('OrderItem')->byOrder((int) $id),
                'csrf'      => $this->csrfToken(),
                'errors'    => $errors,
            ]);
            return;
        }

        $orderModel->update((int) $id, $data);

        // Sync items
        $items = $this->parseItems($_POST);
        $this->model('OrderItem')->syncItems((int) $id, $items);

        $this->setFlash('success', '订单已更新。');
        $this->redirect('/orders/' . $id);
    }

    public function destroy(string $id): void
    {
        $this->requireAuth();

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
            $this->setFlash('error', '该商机已有订单。');
            $this->redirect('/orders');
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
        $this->model('Attachment')->copyTo('deal', (int) $deal['id'], 'order', (int) $orderId, (int) $_SESSION['user_id']);

        $this->setFlash('success', '订单已从商机创建。');
        $this->redirect('/orders/' . $orderId);
    }

    /** Parse items from POST data. */
    private function parseItems(array $post): array
    {
        $items = [];
        if (!empty($post['items']) && is_array($post['items'])) {
            foreach ($post['items'] as $item) {
                $name = trim($item['product_name'] ?? '');
                if ($name === '') continue;
                $items[] = [
                    'product_name' => $name,
                    'sku'          => trim($item['sku'] ?? '') ?: null,
                    'quantity'     => max(0, (float) ($item['quantity'] ?? 0)),
                    'unit_price'   => max(0, (float) ($item['unit_price'] ?? 0)),
                    'unit'         => trim($item['unit'] ?? '件') ?: '件',
                    'notes'        => trim($item['notes'] ?? '') ?: null,
                ];
            }
        }
        return $items;
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
            $this->setFlash('error', $result['error']);
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
        $validStatuses = array_keys(Order::statusOptions());
        $validPaymentStatuses = array_keys(Order::paymentStatusOptions());

        $data = [
            'order_number'   => trim($input['order_number'] ?? ''),
            'deal_id'        => !empty($input['deal_id']) ? (int) $input['deal_id'] : null,
            'customer_id'    => (int) ($input['customer_id'] ?? 0),
            'title'          => trim($input['title'] ?? ''),
            'amount'         => is_numeric($input['amount'] ?? null) ? (float) $input['amount'] : 0,
            'status'         => in_array($input['status'] ?? '', $validStatuses, true) ? $input['status'] : 'pending',
            'payment_status' => in_array($input['payment_status'] ?? '', $validPaymentStatuses, true) ? $input['payment_status'] : 'unpaid',
            'order_date'     => !empty($input['order_date']) ? $input['order_date'] : date('Y-m-d'),
            'delivery_date'  => !empty($input['delivery_date']) ? $input['delivery_date'] : null,
            'shipping_address' => trim($input['shipping_address'] ?? '') ?: null,
            'notes'          => trim($input['notes'] ?? '') ?: null,
        ];

        $errors = [];
        if ($data['order_number'] === '') {
            $errors[] = '订单编号不能为空。';
        }
        if ($data['title'] === '') {
            $errors[] = '订单标题不能为空。';
        }
        if ($data['customer_id'] <= 0) {
            $errors[] = '请选择一个客户。';
        }

        return [$data, $errors];
    }
}
