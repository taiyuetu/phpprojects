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
        $this->model('Lead'); // load class for static lostReasonOptions() in _form.php

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

        // Update lead with customer_id and mark as qualified
        $leadModel->update((int) $id, [
            'status' => 'qualified',
            'customer_id' => $customerId,
        ]);

        $this->setFlash('success', '线索已转为商机，并自动创建了客户。');
        $this->redirect('/deals');
    }

    /**
     * Mark lead as lost with reason
     */
    public function markLost(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $reason = $_POST['lost_reason'] ?? '';
        $validReasons = array_keys(Lead::lostReasonOptions());

        if (!in_array($reason, $validReasons)) {
            $this->setFlash('error', '请选择流失原因。');
            $this->redirect('/leads/' . $id . '/edit');
            return;
        }

        $this->model('Lead')->markAsLost((int) $id, $reason);
        $this->setFlash('success', '线索已标记为流失。');
        $this->redirect('/leads');
    }

    /**
     * Reactivate a lost lead
     */
    public function reactivate(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $lead = $this->model('Lead')->find((int) $id);

        if (!$lead || $lead['status'] !== 'lost') {
            $this->setFlash('error', '只能重新激活已流失的线索。');
            $this->redirect('/leads');
            return;
        }

        $this->model('Lead')->reactivate((int) $id);
        $this->setFlash('success', '线索已重新激活。');
        $this->redirect('/leads');
    }

    private function validate(array $input): array
    {
        $data = [
            'title'         => trim($input['title'] ?? ''),
            'company'       => trim($input['company'] ?? '') ?: null,
            'contact_name'  => trim($input['contact_name'] ?? '') ?: null,
            'contact_email' => trim($input['contact_email'] ?? '') ?: null,
            'lead_time'     => trim($input['lead_time'] ?? '') ?: null,
            'whatsapp'      => trim($input['whatsapp'] ?? '') ?: null,
            'phone'         => trim($input['phone'] ?? '') ?: null,
            'facebook'      => trim($input['facebook'] ?? '') ?: null,
            'tiktok'        => trim($input['tiktok'] ?? '') ?: null,
            'website'       => trim($input['website'] ?? '') ?: null,
            'source_country'=> trim($input['source_country'] ?? '') ?: null,
            'source_city'   => trim($input['source_city'] ?? '') ?: null,
            'address'       => trim($input['address'] ?? '') ?: null,
            'first_purchase_from_china' => isset($input['first_purchase_from_china']) ? 1 : 0,
            'has_import_capability'     => isset($input['has_import_capability']) ? 1 : 0,
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
