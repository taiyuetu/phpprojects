<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\CsvImportExport;
use App\Core\Model;
use App\Models\Customer;

class CustomerController extends Controller
{
    use CsvImportExport;

    protected function csvModelClass(): string { return Customer::class; }
    protected function csvColumns(): array { return ['name', 'phone', 'email', 'address']; }
    protected function csvMatchField(): string { return 'name'; }
    protected function csvBasePath(): string { return '/customers'; }
    protected function csvEntityLabel(): string { return 'customer'; }

    public function index(): void
    {
        $filters = ['q' => trim($this->input('q', ''))] + $this->customFieldFilters(Customer::customFields());

        $this->view('customers/index', [
            'title'        => 'Customers',
            'customers'    => Customer::filterWithCustomFields($filters, ['name', 'phone', 'email'], 'name'),
            'customFields' => Customer::customFields(),
            'filters'      => $filters,
        ]);
    }

    public function create(): void
    {
        $this->view('customers/form', [
            'title'        => 'Add Customer',
            'customer'     => null,
            'customFields' => Customer::customFields(),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        Customer::create([
            'name'       => trim($this->input('name')),
            'phone'      => trim($this->input('phone')),
            'email'      => trim($this->input('email')),
            'address'    => trim($this->input('address')),
            'attributes' => json_encode($this->collectAttributes(), JSON_UNESCAPED_UNICODE),
        ]);
        $this->flash('success', 'Customer added.');
        $this->redirect('/customers');
    }

    public function edit(string $id): void
    {
        $customer = Customer::find($id);
        if (!$customer) { $this->flash('error', 'Customer not found.'); $this->redirect('/customers'); }
        $this->view('customers/form', [
            'title'        => 'Edit Customer',
            'customer'     => $customer,
            'customFields' => Customer::customFields(),
        ]);
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $customer = Customer::find($id);
        if (!$customer) { $this->flash('error', 'Customer not found.'); $this->redirect('/customers'); }
        $existingAttrs = Customer::parseCustomFields($customer['attributes'] ?? '{}');
        Customer::update($id, [
            'name'       => trim($this->input('name')),
            'phone'      => trim($this->input('phone')),
            'email'      => trim($this->input('email')),
            'address'    => trim($this->input('address')),
            'attributes' => json_encode($this->collectAttributes($existingAttrs), JSON_UNESCAPED_UNICODE),
        ]);
        $this->flash('success', 'Customer updated.');
        $this->redirect('/customers');
    }

    public function delete(string $id): void
    {
        $this->verifyCsrf();
        // Check if customer is referenced by any sales
        $saleCount = Model::raw('SELECT COUNT(*) AS cnt FROM sales WHERE customer_id = ?', [$id])[0]['cnt'] ?? 0;
        if ($saleCount > 0) {
            $this->flash('error', 'Cannot delete this customer because it is referenced by existing sale records.');
            $this->redirect('/customers');
        }
        Model::raw("DELETE FROM change_logs WHERE table_name = 'customers' AND record_id = ?", [$id]);
        Customer::delete($id);
        $this->flash('success', 'Customer deleted.');
        $this->redirect('/customers');
    }

    private function collectAttributes(array $existing = []): array
    {
        $attrs = $this->customFieldValues(Customer::customFields());
        return Customer::handleCustomFieldUploads(array_merge($existing, $attrs));
    }
}
