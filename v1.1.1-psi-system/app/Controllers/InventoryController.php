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
        $this->view('inventory/index', [
            'title'        => 'Inventory Ledger',
            'transactions' => $q !== '' ? InventoryTransaction::searchRecent($q, 200) : InventoryTransaction::recent(200),
            'q'            => $q,
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
