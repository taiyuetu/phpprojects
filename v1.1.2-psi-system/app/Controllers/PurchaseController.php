<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Product;

class PurchaseController extends Controller
{
    public function index(): void
    {
        $q = trim($this->input('q', ''));
        $dateFrom = trim($this->input('date_from', ''));
        $dateTo   = trim($this->input('date_to', ''));
        $page = max(1, (int) $this->input('page', 1));

        $result = Purchase::filterPaginated($q, $dateFrom, $dateTo, $page);

        $this->view('purchases/index', [
            'title'     => 'Purchases',
            'purchases' => $result['rows'],
            'q'         => $q,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'pagination' => $result,
        ]);
    }

    public function create(): void
    {
        $this->view('purchases/form', [
            'title'     => 'New Purchase',
            'suppliers' => Supplier::all('name'),
            'products'  => Product::all('name'),
            'nextInvoice' => 'PO-' . date('Ymd') . '-' . str_pad((string)(Purchase::count() + 1), 4, '0', STR_PAD_LEFT),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $productIds = $this->input('product_id', []);
        $qtys       = $this->input('qty', []);
        $costs      = $this->input('unit_cost', []);

        $items = [];
        foreach ($productIds as $i => $productId) {
            if (!$productId || !$qtys[$i]) continue;
            $items[] = [
                'product_id' => (int) $productId,
                'qty'        => (int) $qtys[$i],
                'unit_cost'  => (float) $costs[$i],
            ];
        }

        if (empty($items)) {
            $this->flash('error', 'Add at least one product line before saving.');
            $this->redirect('/purchases/create');
        }

        $header = [
            'invoice_no'            => trim($this->input('invoice_no')),
            'supplier_id'           => (int) $this->input('supplier_id'),
            'purchase_date'         => $this->input('purchase_date', date('Y-m-d')),
            'expected_arrival_date' => $this->input('expected_arrival_date') ?: null,
            'actual_arrival_date'   => $this->input('actual_arrival_date') ?: null,
            'actual_arrival_qty'    => (int) $this->input('actual_arrival_qty', 0),
            'notes'                 => trim($this->input('notes', '')),
            'created_by'            => Auth::user()['id'] ?? null,
        ];

        try {
            $id = Purchase::createWithItems($header, $items);
            $this->flash('success', 'Purchase recorded. Please confirm arrival to update stock.');
            $this->redirect('/purchases/' . $id);
        } catch (\Throwable $e) {
            $this->flash('error', 'Could not save purchase: ' . $e->getMessage());
            $this->redirect('/purchases/create');
        }
    }

    public function show(string $id): void
    {
        $purchase = Purchase::withItems((int) $id);
        if (!$purchase) { $this->flash('error', 'Purchase not found.'); $this->redirect('/purchases'); }

        $this->view('purchases/show', ['title' => 'Purchase #' . $purchase['invoice_no'], 'purchase' => $purchase]);
    }

    public function recordArrival(string $id): void
    {
        $this->verifyCsrf();

        $purchase = Purchase::find((int) $id);
        if (!$purchase) {
            $this->flash('error', 'Purchase not found.');
            $this->redirect('/purchases');
        }

        $arrivalDate = $this->input('arrival_date', date('Y-m-d'));
        $qty = (int) $this->input('qty', 0);
        $notes = trim($this->input('notes', ''));

        if ($qty <= 0) {
            $this->flash('error', 'Arrival qty must be greater than 0.');
            $this->redirect('/purchases/' . $id);
        }

        try {
            PurchaseArrival::recordArrival((int) $id, $arrivalDate, $qty, $notes);
            $this->flash('success', 'Arrival recorded! Stock updated by ' . $qty . ' units.');
            $this->redirect('/purchases/' . $id);
        } catch (\Throwable $e) {
            $this->flash('error', 'Could not record arrival: ' . $e->getMessage());
            $this->redirect('/purchases/' . $id);
        }
    }
}
