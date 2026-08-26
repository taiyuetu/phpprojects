<?php

class CustomerController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $search = trim($_GET['q'] ?? '');
        $customers = $this->model('Customer')->allWithOwner($search);

        $this->view('customers/index', [
            'customers' => $customers,
            'search' => $search,
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
        $customer = $customerModel->findWithOwner((int) $id);

        if (!$customer) {
            $this->setFlash('error', '客户不存在。');
            $this->redirect('/customers');
        }

        $this->view('customers/show', [
            'customer' => $customer,
            'deals' => $customerModel->deals((int) $id),
            'activities' => $customerModel->activities((int) $id),
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
        }

        $this->view('customers/edit', ['customer' => $customer, 'csrf' => $this->csrfToken(), 'errors' => []]);
    }

    public function update(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        [$data, $errors] = $this->validate($_POST);

        if ($errors) {
            $this->view('customers/edit', [
                'customer' => array_merge(['id' => $id], $_POST),
                'csrf' => $this->csrfToken(),
                'errors' => $errors,
            ]);
            return;
        }

        $this->model('Customer')->update((int) $id, $data);
        $this->setFlash('success', '客户已更新。');
        $this->redirect('/customers/' . $id);
    }

    public function destroy(string $id): void
    {
        $this->requireAuth();
        $this->model('Customer')->delete((int) $id);
        $this->setFlash('success', '客户已删除。');
        $this->redirect('/customers');
    }

    public function addNote(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $note = trim($_POST['description'] ?? '');
        if ($note !== '') {
            $this->model('Customer')->addActivity((int) $id, $_SESSION['user_id'], 'note', $note);
            $this->setFlash('success', '备注已添加。');
        }
        $this->redirect('/customers/' . $id);
    }

    /** @return array{0: array, 1: array} [validated data, errors] */
    private function validate(array $input): array
    {
        $data = [
            'name'    => trim($input['name'] ?? ''),
            'company' => trim($input['company'] ?? '') ?: null,
            'email'   => trim($input['email'] ?? '') ?: null,
            'phone'   => trim($input['phone'] ?? '') ?: null,
            'address' => trim($input['address'] ?? '') ?: null,
            'status'  => in_array($input['status'] ?? '', ['active', 'inactive'], true) ? $input['status'] : 'active',
            'notes'   => trim($input['notes'] ?? '') ?: null,
        ];

        $errors = [];
        if ($data['name'] === '') {
            $errors[] = '客户姓名不能为空。';
        }
        if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = '请输入有效的邮箱地址。';
        }

        return [$data, $errors];
    }
}
