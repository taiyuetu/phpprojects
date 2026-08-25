<?php

class LeadController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $status = trim($_GET['status'] ?? '');
        $leads = $this->model('Lead')->allWithCustomer($status);

        $this->view('leads/index', ['leads' => $leads, 'status' => $status]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $customers = $this->model('Customer')->all('name ASC');
        $this->view('leads/create', [
            'customers' => $customers,
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
            $this->view('leads/create', [
                'customers' => $this->model('Customer')->all('name ASC'),
                'csrf' => $this->csrfToken(),
                'old' => $_POST,
                'errors' => $errors,
            ]);
            return;
        }

        $data['owner_id'] = $_SESSION['user_id'];
        $this->model('Lead')->create($data);

        $this->setFlash('success', '线索已创建。');
        $this->redirect('/leads');
    }

    public function edit(string $id): void
    {
        $this->requireAuth();

        $lead = $this->model('Lead')->find((int) $id);
        if (!$lead) {
            $this->setFlash('error', '线索不存在。');
            $this->redirect('/leads');
        }

        $this->view('leads/edit', [
            'lead' => $lead,
            'customers' => $this->model('Customer')->all('name ASC'),
            'csrf' => $this->csrfToken(),
            'errors' => [],
        ]);
    }

    public function update(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        [$data, $errors] = $this->validate($_POST);

        if ($errors) {
            $this->view('leads/edit', [
                'lead' => array_merge(['id' => $id], $_POST),
                'customers' => $this->model('Customer')->all('name ASC'),
                'csrf' => $this->csrfToken(),
                'errors' => $errors,
            ]);
            return;
        }

        $this->model('Lead')->update((int) $id, $data);
        $this->setFlash('success', '线索已更新。');
        $this->redirect('/leads');
    }

    public function destroy(string $id): void
    {
        $this->requireAuth();
        $this->model('Lead')->delete((int) $id);
        $this->setFlash('success', '线索已删除。');
        $this->redirect('/leads');
    }

    /** Mark a qualified lead as won and spin up a Deal for it. */
    public function convert(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $leadModel = $this->model('Lead');
        $lead = $leadModel->find((int) $id);

        if (!$lead) {
            $this->setFlash('error', '线索不存在。');
            $this->redirect('/leads');
        }

        if (!$lead['customer_id']) {
            $this->setFlash('error', '请先将此线索关联到客户再转换。');
            $this->redirect('/leads');
        }

        $this->model('Deal')->create([
            'title'       => $lead['title'],
            'customer_id' => $lead['customer_id'],
            'value'       => $lead['value'],
            'stage'       => 'open',
            'owner_id'    => $_SESSION['user_id'],
        ]);

        $leadModel->update((int) $id, ['status' => 'qualified']);

        $this->setFlash('success', '线索已转为商机。');
        $this->redirect('/deals');
    }

    private function validate(array $input): array
    {
        $data = [
            'title'         => trim($input['title'] ?? ''),
            'customer_id'   => !empty($input['customer_id']) ? (int) $input['customer_id'] : null,
            'contact_name'  => trim($input['contact_name'] ?? '') ?: null,
            'contact_email' => trim($input['contact_email'] ?? '') ?: null,
            'source'        => trim($input['source'] ?? '') ?: null,
            'status'        => in_array($input['status'] ?? '', ['new', 'contacted', 'qualified', 'lost'], true)
                ? $input['status'] : 'new',
            'value'         => is_numeric($input['value'] ?? null) ? (float) $input['value'] : 0,
            'notes'         => trim($input['notes'] ?? '') ?: null,
        ];

        $errors = [];
        if ($data['title'] === '') {
            $errors[] = '线索标题不能为空。';
        }

        return [$data, $errors];
    }
}
