<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\Product;

class SaleController extends Controller
{
    public function index(): void
    {
        $q = trim($this->input('q', ''));
        $dateFrom = trim($this->input('date_from', ''));
        $dateTo   = trim($this->input('date_to', ''));
        $page = max(1, (int) $this->input('page', 1));

        $filters = ['q' => $q, 'date_from' => $dateFrom, 'date_to' => $dateTo]
                 + $this->customFieldFilters(Sale::customFields());

        $cfFilters = $this->customFieldFilters(Sale::customFields());
        $result = Sale::filterPaginated($q, $dateFrom, $dateTo, $page, 20, $cfFilters);

        $this->view('sales/index', [
            'title'        => 'Sales',
            'sales'        => $result['rows'],
            'q'            => $q,
            'date_from'    => $dateFrom,
            'date_to'      => $dateTo,
            'customFields' => Sale::customFields(),
            'filters'      => $filters,
            'pagination'   => $result,
        ]);
    }

    public function create(): void
    {
        $this->view('sales/form', [
            'title'        => 'New Sale',
            'customers'    => Customer::all('name'),
            'products'     => Product::allWithCategory(),
            'nextInvoice'  => 'INV-' . date('Ymd') . '-' . str_pad((string)(Sale::count() + 1), 4, '0', STR_PAD_LEFT),
            'customFields' => Sale::customFields(),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $productIds = $this->input('product_id', []);
        $qtys       = $this->input('qty', []);
        $prices     = $this->input('unit_price', []);

        $items = [];
        foreach ($productIds as $i => $productId) {
            if (!$productId || !$qtys[$i]) continue;
            $items[] = [
                'product_id' => (int) $productId,
                'qty'        => (int) $qtys[$i],
                'unit_price' => (float) $prices[$i],
            ];
        }

        if (empty($items)) {
            $this->flash('error', 'Add at least one product line before saving.');
            $this->redirect('/sales/create');
        }

        // Collect and validate custom fields
        $cfValues = $this->customFieldValues(Sale::customFields());
        $this->validateCustomFieldsOrFail(Sale::class, $cfValues, '/sales/create');

        $header = [
            'invoice_no'  => trim($this->input('invoice_no')),
            'customer_id' => $this->input('customer_id') ?: null,
            'sale_date'   => $this->input('sale_date', date('Y-m-d')),
            'attributes'  => json_encode($cfValues, JSON_UNESCAPED_UNICODE),
            'created_by'  => Auth::user()['id'] ?? null,
        ];

        try {
            $id = Sale::createWithItems($header, $items);
            $this->flash('success', 'Sale recorded and stock updated.');
            $this->redirect('/sales/' . $id);
        } catch (\Throwable $e) {
            $this->flash('error', 'Could not save sale: ' . $e->getMessage());
            $this->redirect('/sales/create');
        }
    }

    public function show(string $id): void
    {
        $sale = Sale::withItems((int) $id);
        if (!$sale) { $this->flash('error', 'Sale not found.'); $this->redirect('/sales'); }

        $this->view('sales/show', [
            'title'        => 'Sale #' . $sale['invoice_no'],
            'sale'         => $sale,
            'customFields' => Sale::customFields(),
        ]);
    }
}
