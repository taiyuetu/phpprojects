<?php

class LeadController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $status = trim($_GET['status'] ?? '');
        $leads = $this->model('Lead')->allLeads($status);

        $this->view('leads/index', ['leads' => $leads, 'status' => $status]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $this->view('leads/create', [
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

    /**
     * Convert a qualified lead into a customer + deal.
     * Auto-creates a customer from the lead's contact info,
     * then creates a deal linked to that customer.
     */
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

        // Auto-create customer from lead contact info
        $customerName = $lead['contact_name'] ?: $lead['title'];
        $customerId = $this->model('Customer')->create([
            'name'    => $customerName,
            'email'   => $lead['contact_email'],
            'owner_id' => $_SESSION['user_id'],
        ]);

        // Create deal linked to the new customer
        $this->model('Deal')->create([
            'title'       => $lead['title'],
            'customer_id' => $customerId,
            'value'       => $lead['value'],
            'stage'       => 'open',
            'owner_id'    => $_SESSION['user_id'],
        ]);

        // Mark lead as qualified
        $leadModel->update((int) $id, ['status' => 'qualified']);

        $this->setFlash('success', '线索已转为商机，并自动创建了客户。');
        $this->redirect('/deals');
    }

    private function validate(array $input): array
    {
        $data = [
            'title'         => trim($input['title'] ?? ''),
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
