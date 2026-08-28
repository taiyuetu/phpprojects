<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Product;
use App\Models\InventoryTransaction;

class InventoryController extends Controller
{
    public function index(): void
    {
        $q = trim($this->input('q', ''));
        $page = max(1, (int) $this->input('page', 1));

        $result = InventoryTransaction::filterPaginated($q, $page);

        $this->view('inventory/index', [
            'title'        => 'Inventory Ledger',
            'transactions' => $result['rows'],
            'q'            => $q,
            'pagination'   => $result,
        ]);
    }

    public function lowStock(): void
    {
        $this->view('inventory/low_stock', [
            'title'    => 'Low Stock Alerts',
            'products' => Product::lowStock(),
        ]);
    }
}
