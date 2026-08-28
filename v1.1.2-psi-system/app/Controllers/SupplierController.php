<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\CsvImportExport;
use App\Core\Model;
use App\Models\Supplier;

class SupplierController extends Controller
{
    use CsvImportExport;

    protected function csvModelClass(): string { return Supplier::class; }
    protected function csvColumns(): array { return ['name', 'phone', 'email', 'address']; }
    protected function csvMatchField(): string { return 'name'; }
    protected function csvBasePath(): string { return '/suppliers'; }
    protected function csvEntityLabel(): string { return 'supplier'; }

    public function index(): void
    {
        $filters = ['q' => trim($this->input('q', ''))] + $this->customFieldFilters(Supplier::customFields());
        $page = max(1, (int) $this->input('page', 1));

        $result = Supplier::filterWithCustomFieldsPaginated($filters, ['name', 'phone', 'email'], 'name', $page);

        $this->view('suppliers/index', [
            'title'        => 'Suppliers',
            'suppliers'    => $result['rows'],
            'customFields' => Supplier::customFields(),
            'filters'      => $filters,
            'pagination'   => $result,
        ]);
    }

    public function create(): void
    {
        $this->view('suppliers/form', [
            'title'        => 'Add Supplier',
            'supplier'     => null,
            'customFields' => Supplier::customFields(),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        Supplier::create([
            'name'       => trim($this->input('name')),
            'phone'      => trim($this->input('phone')),
            'email'      => trim($this->input('email')),
            'address'    => trim($this->input('address')),
            'attributes' => json_encode($this->collectAttributes(), JSON_UNESCAPED_UNICODE),
        ]);
        $this->flash('success', 'Supplier added.');
        $this->redirect('/suppliers');
    }

    public function edit(string $id): void
    {
        $supplier = Supplier::find($id);
        if (!$supplier) { $this->flash('error', 'Supplier not found.'); $this->redirect('/suppliers'); }
        $this->view('suppliers/form', [
            'title'        => 'Edit Supplier',
            'supplier'     => $supplier,
            'customFields' => Supplier::customFields(),
        ]);
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $supplier = Supplier::find($id);
        if (!$supplier) { $this->flash('error', 'Supplier not found.'); $this->redirect('/suppliers'); }
        $existingAttrs = Supplier::parseCustomFields($supplier['attributes'] ?? '{}');
        Supplier::update($id, [
            'name'       => trim($this->input('name')),
            'phone'      => trim($this->input('phone')),
            'email'      => trim($this->input('email')),
            'address'    => trim($this->input('address')),
            'attributes' => json_encode($this->collectAttributes($existingAttrs), JSON_UNESCAPED_UNICODE),
        ]);
        $this->flash('success', 'Supplier updated.');
        $this->redirect('/suppliers');
    }

    public function delete(string $id): void
    {
        $this->verifyCsrf();
        // Check if supplier is referenced by any purchases
        $purchaseCount = Model::raw('SELECT COUNT(*) AS cnt FROM purchases WHERE supplier_id = ?', [$id])[0]['cnt'] ?? 0;
        if ($purchaseCount > 0) {
            $this->flash('error', 'Cannot delete this supplier because it is referenced by existing purchase records.');
            $this->redirect('/suppliers');
        }
        Model::raw("DELETE FROM change_logs WHERE table_name = 'suppliers' AND record_id = ?", [$id]);
        Supplier::delete($id);
        $this->flash('success', 'Supplier deleted.');
        $this->redirect('/suppliers');
    }

    private function collectAttributes(array $existing = []): array
    {
        $attrs = $this->customFieldValues(Supplier::customFields());
        return Supplier::handleCustomFieldUploads(array_merge($existing, $attrs));
    }
}
