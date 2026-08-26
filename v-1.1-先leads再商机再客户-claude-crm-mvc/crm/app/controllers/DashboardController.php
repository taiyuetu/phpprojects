<?php

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $customerModel = $this->model('Customer');
        $leadModel = $this->model('Lead');
        $dealModel = $this->model('Deal');

        $stats = [
            'total_customers' => $customerModel->count(),
            'active_customers' => $customerModel->count('status = :s', [':s' => 'active']),
            'open_leads' => $leadModel->count("status NOT IN ('lost')"),
            'pipeline_value' => $dealModel->openPipelineValue(),
            'won_value' => $dealModel->sumValueByStage('closed_won'),
        ];

        $recentCustomers = array_slice($customerModel->allWithOwner(), 0, 5);
        $recentDeals = array_slice($dealModel->allWithCustomer(), 0, 5);
        $recentLeads = array_slice($leadModel->allLeads(), 0, 5);

        $this->view('dashboard/index', [
            'stats' => $stats,
            'recentCustomers' => $recentCustomers,
            'recentDeals' => $recentDeals,
            'recentLeads' => $recentLeads,
        ]);
    }
}
