<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Product;

class ReportController extends Controller
{
    public function salesReport(): void
    {
        $from = $this->input('from', date('Y-m-01'));
        $to   = $this->input('to', date('Y-m-d'));

        $rows = Sale::raw(
            "SELECT sa.invoice_no, sa.sale_date, c.name AS customer_name, sa.total
             FROM sales sa LEFT JOIN customers c ON c.id = sa.customer_id
             WHERE sa.sale_date BETWEEN ? AND ?
             ORDER BY sa.sale_date DESC",
            [$from, $to]
        );

        $total = array_sum(array_column($rows, 'total'));

        $this->view('reports/sales', compact('rows', 'total', 'from', 'to') + ['title' => 'Sales Report']);
    }

    public function purchaseReport(): void
    {
        $from = $this->input('from', date('Y-m-01'));
        $to   = $this->input('to', date('Y-m-d'));

        $rows = Purchase::raw(
            "SELECT pu.invoice_no, pu.purchase_date, s.name AS supplier_name, pu.total
             FROM purchases pu JOIN suppliers s ON s.id = pu.supplier_id
             WHERE pu.purchase_date BETWEEN ? AND ?
             ORDER BY pu.purchase_date DESC",
            [$from, $to]
        );

        $total = array_sum(array_column($rows, 'total'));

        $this->view('reports/purchases', compact('rows', 'total', 'from', 'to') + ['title' => 'Purchase Report']);
    }

    public function stockReport(): void
    {
        $products = Product::allWithCategory();
        $totalValue = 0;
        foreach ($products as $p) {
            $totalValue += $p['quantity'] * $p['cost_price'];
        }

        $this->view('reports/stock', ['title' => 'Stock Valuation Report', 'products' => $products, 'totalValue' => $totalValue]);
    }
}
