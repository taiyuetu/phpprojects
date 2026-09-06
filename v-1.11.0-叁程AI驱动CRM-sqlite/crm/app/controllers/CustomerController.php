<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

class CustomerController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $search = trim($_GET['q'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 15;

        $customerModel = $this->model('Customer');
        $total = $customerModel->countWithOwner($search);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);

        $this->view('customers/index', [
            'customers'  => $customerModel->allWithOwner($search, $page, $perPage),
            'search'     => $search,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->view('customers/create', ['csrf' => $this->csrfToken(), 'old' => [], 'errors' => []]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        [$data, $errors] = $this->validate($_POST);

        if ($errors) {
            $this->view('customers/create', ['csrf' => $this->csrfToken(), 'old' => $_POST, 'errors' => $errors]);
            return;
        }

        $data['owner_id'] = $_SESSION['user_id'];
        $id = $this->model('Customer')->create($data);

        $this->setFlash('success', '客户已创建。');
        $this->redirect('/customers/' . $id);
    }

    public function show(string $id): void
    {
        $this->requireAuth();

        $customerModel = $this->model('Customer');
        $followUpModel = $this->model('FollowUp');
        $orderModel = $this->model('Order');
        $customer = $customerModel->findWithOwner((int) $id);

        if (!$customer) {
            $this->setFlash('error', '客户不存在。');
            $this->redirect('/customers');
        }

        $this->view('customers/show', [
            'customer' => $customer,
            'deals' => $customerModel->deals((int) $id),
            'orders' => $orderModel->byCustomer((int) $id),
            'convertedLead' => $customerModel->convertedLead((int) $id),
            'followUps' => $followUpModel->byCustomer((int) $id),
            'activities' => $customerModel->activities((int) $id),
            'attachments' => $this->model('Attachment')->byRelated('customer', (int) $id),
            'csrf' => $this->csrfToken(),
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireAuth();

        $customer = $this->model('Customer')->find((int) $id);
        if (!$customer) {
            $this->setFlash('error', '客户不存在。');
            $this->redirect('/customers');
            return;
        }

        $this->view('customers/edit', ['customer' => $customer, 'csrf' => $this->csrfToken(), 'errors' => []]);
    }

    public function update(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $customerModel = $this->model('Customer');
        $customer = $customerModel->find((int) $id);
        if (!$customer) {
            $this->setFlash('error', '客户不存在。');
            $this->redirect('/customers');
            return;
        }

        [$data, $errors] = $this->validate($_POST);

        if ($errors) {
            $this->view('customers/edit', [
                'customer' => array_merge(['id' => $id], $_POST),
                'csrf' => $this->csrfToken(),
                'errors' => $errors,
            ]);
            return;
        }

        $customerModel->update((int) $id, $data);
        $this->setFlash('success', '客户已更新。');
        $this->redirect('/customers/' . $id);
    }

    public function destroy(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $customerId = (int) $id;
        $customerModel = $this->model('Customer');
        $customer = $customerModel->find($customerId);

        if (!$customer) {
            $this->setFlash('error', '客户不存在。');
            $this->redirect('/customers');
            return;
        }

        // 客户 + 名下线索/商机/订单是一笔事务：中途失败不能留下“订单在、客户没了”的半成品
        $db = Database::connection();
        $db->beginTransaction();
        try {
            // Delete orders for this customer
            $orderModel = $this->model('Order');
            foreach ($orderModel->where('customer_id', $customerId) as $order) {
                $orderModel->delete((int) $order['id']);
            }

            // Delete leads that were converted to create this customer
            $leadModel = $this->model('Lead');
            foreach ($leadModel->where('customer_id', $customerId) as $lead) {
                $leadModel->delete((int) $lead['id']);
            }

            // Delete deals (CASCADE would handle this, but be explicit)
            $dealModel = $this->model('Deal');
            foreach ($dealModel->where('customer_id', $customerId) as $deal) {
                $dealModel->delete((int) $deal['id']);
            }

            $customerModel->delete($customerId);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            $this->setFlash('error', '客户删除失败：' . $e->getMessage());
            $this->redirect('/customers');
            return;
        }

        $this->setFlash('success', '客户及关联的线索、商机、订单已删除。');
        $this->redirect('/customers');
    }

    public function addNote(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $customer = $this->model('Customer')->find((int) $id);
        if (!$customer) {
            $this->setFlash('error', '客户不存在。');
            $this->redirect('/customers');
            return;
        }

        $note = trim($_POST['description'] ?? '');
        if ($note !== '') {
            $this->model('Customer')->addActivity((int) $id, $_SESSION['user_id'], 'note', $note);
            $this->setFlash('success', '备注已添加。');
        }
        $this->redirect('/customers/' . $id);
    }

    public function addFollowUp(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $customer = $this->model('Customer')->find((int) $id);
        if (!$customer) {
            $this->setFlash('error', '客户不存在。');
            $this->redirect('/customers');
            return;
        }

        $title = trim($_POST['title'] ?? '');
        if ($title !== '') {
            $this->model('FollowUp')->addFollowUp((int) $id, $_SESSION['user_id'], [
                'type' => $_POST['type'] ?? 'price_comparison',
                'title' => $title,
                'description' => trim($_POST['description'] ?? '') ?: null,
                'next_action' => trim($_POST['next_action'] ?? '') ?: null,
                'next_date' => $_POST['next_date'] ?? null,
            ]);
            $this->setFlash('success', '跟进记录已添加。');
        }
        $this->redirect('/customers/' . $id);
    }

    /**
     * Upload attachment for a customer.
     */
    public function uploadAttachment(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $customer = $this->model('Customer')->find((int) $id);
        if (!$customer) {
            $this->setFlash('error', '客户不存在。');
            $this->redirect('/customers');
            return;
        }

        if (empty($_FILES['attachment']) || $_FILES['attachment']['error'] === UPLOAD_ERR_NO_FILE) {
            $this->setFlash('error', '请选择要上传的文件。');
            $this->redirect('/customers/' . $id);
            return;
        }

        $result = $this->model('Attachment')->upload(
            $_FILES['attachment'],
            'customer',
            (int) $id,
            (int) $_SESSION['user_id']
        );

        if ($result['success']) {
            $this->setFlash('success', '附件上传成功。');
        } else {
            $this->setFlash('error', $result['error']);
        }

        $this->redirect('/customers/' . $id);
    }

    /**
     * Delete attachment from a customer.
     */
    public function deleteAttachment(string $customerId, string $attachmentId): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $customer = $this->model('Customer')->find((int) $customerId);
        if (!$customer) {
            $this->setFlash('error', '客户不存在。');
            $this->redirect('/customers');
            return;
        }

        $attachmentModel = $this->model('Attachment');
        $attachment = $attachmentModel->find((int) $attachmentId);

        if (!$attachment || $attachment['related_type'] !== 'customer' || (int) $attachment['related_id'] !== (int) $customerId) {
            $this->setFlash('error', '附件不存在。');
            $this->redirect('/customers/' . $customerId);
            return;
        }

        $attachmentModel->remove((int) $attachmentId);
        $this->setFlash('success', '附件已删除。');
        $this->redirect('/customers/' . $customerId);
    }

    /** @return array{0: array, 1: array} [validated data, errors] */
    private function validate(array $input): array
    {
        // 规则白名单在 Customer::$fields（见 core/Fields.php），控制器只做委托
        return (new Customer())->sanitizeInput($input);
    }
}
