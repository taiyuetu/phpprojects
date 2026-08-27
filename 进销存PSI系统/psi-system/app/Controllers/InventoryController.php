<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Product;
use App\Models\InventoryTransaction;

class InventoryController extends Controller
{
    public function index(): void
    {
        $this->view('inventory/index', [
            'title'        => 'Inventory Ledger',
            'transactions' => InventoryTransaction::recent(200),
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
