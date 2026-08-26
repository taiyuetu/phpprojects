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

        $deal = $this->model('Deal')->find((int) $id);
        if (!$deal) {
            $this->setFlash('error', '商机不存在。');
            $this->redirect('/deals');
        }

        $this->view('deals/edit', [
            'deal' => $deal,
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
            $this->view('deals/edit', [
                'deal' => array_merge(['id' => $id], $_POST),
                'customers' => $this->model('Customer')->all('name ASC'),
                'csrf' => $this->csrfToken(),
                'errors' => $errors,
            ]);
            return;
        }

        // Auto-record stage transition time
        $oldDeal = $this->model('Deal')->find((int) $id);
        if ($oldDeal && $oldDeal['stage'] !== $data['stage']) {
            $stageCol = 'stage_' . $data['stage'] . '_at';
            $data[$stageCol] = date('Y-m-d H:i:s');
        }

        $this->model('Deal')->update((int) $id, $data);
        $this->setFlash('success', '商机已更新。');
        $this->redirect('/deals');
    }

    public function destroy(string $id): void
    {
        $this->requireAuth();
        $this->model('Deal')->delete((int) $id);
        $this->setFlash('success', '商机已删除。');
        $this->redirect('/deals');
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
