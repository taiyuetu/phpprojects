<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\InventoryTransaction;

class DashboardController extends Controller
{
    public function index(): void
    {
        $totalProducts = Product::count();
        $lowStock = Product::lowStock();

        $totalPurchases = Purchase::count();
        $totalSales = Sale::count();

        $salesTotal = (float) (Product::raw('SELECT COALESCE(SUM(total),0) AS t FROM sales')[0]['t'] ?? 0);
        $purchaseTotal = (float) (Product::raw('SELECT COALESCE(SUM(total),0) AS t FROM purchases')[0]['t'] ?? 0);

        $stockValue = (float) (Product::raw('SELECT COALESCE(SUM(quantity * cost_price),0) AS v FROM products')[0]['v'] ?? 0);

        $recentTransactions = InventoryTransaction::recent(10);

        $this->view('dashboard/index', [
            'title'              => 'Dashboard',
            'totalProducts'      => $totalProducts,
            'lowStock'           => $lowStock,
            'totalPurchases'     => $totalPurchases,
            'totalSales'         => $totalSales,
            'salesTotal'         => $salesTotal,
            'purchaseTotal'      => $purchaseTotal,
            'stockValue'         => $stockValue,
            'recentTransactions' => $recentTransactions,
        ]);
    }
}
