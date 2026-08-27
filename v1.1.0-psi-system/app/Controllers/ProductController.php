<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\InventoryTransaction;

class ProductController extends Controller
{
    public function index(): void
    {
        $this->view('products/index', [
            'title'    => 'Products',
            'products' => Product::allWithCategory(),
        ]);
    }

    public function create(): void
    {
        $this->view('products/form', [
            'title'      => 'Add Product',
            'product'    => null,
            'categories' => Category::all('name'),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->productData();
        $id = Product::create($data);

        if ((int)$data['quantity'] > 0) {
            InventoryTransaction::create([
                'product_id'    => $id,
                'type'          => 'adjustment',
                'qty_change'    => (int)$data['quantity'],
                'balance_after' => (int)$data['quantity'],
                'reference'     => 'Initial stock',
                'notes'         => 'Opening balance on product creation',
            ]);
        }

        $this->flash('success', 'Product added.');
        $this->redirect('/products');
    }

    public function edit(string $id): void
    {
        $product = Product::find($id);
        if (!$product) { $this->flash('error', 'Product not found.'); $this->redirect('/products'); }
        $this->view('products/form', [
            'title'      => 'Edit Product',
            'product'    => $product,
            'categories' => Category::all('name'),
        ]);
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $product = Product::find($id);
        $data = $this->productData();

        // Any manual quantity edit here is logged as an adjustment.
        $diff = (int)$data['quantity'] - (int)$product['quantity'];
        Product::update($id, $data);

        if ($diff !== 0) {
            InventoryTransaction::create([
                'product_id'    => $id,
                'type'          => 'adjustment',
                'qty_change'    => $diff,
                'balance_after' => (int)$data['quantity'],
                'reference'     => 'Manual edit',
                'notes'         => 'Quantity corrected via product edit form',
            ]);
        }

        $this->flash('success', 'Product updated.');
        $this->redirect('/products');
    }

    public function delete(string $id): void
    {
        $this->verifyCsrf();
        Product::delete($id);
        $this->flash('success', 'Product deleted.');
        $this->redirect('/products');
    }

    public function show(string $id): void
    {
        $product = Product::find($id);
        if (!$product) { $this->flash('error', 'Product not found.'); $this->redirect('/products'); }

        $this->view('products/show', [
            'title'        => 'Product Detail',
            'product'      => $product,
            'transactions' => InventoryTransaction::forProduct((int)$id),
        ]);
    }

    private function productData(): array
    {
        return [
            'sku'           => trim($this->input('sku')),
            'name'          => trim($this->input('name')),
            'category_id'   => $this->input('category_id') ?: null,
            'unit'          => trim($this->input('unit', 'pcs')),
            'cost_price'    => (float) $this->input('cost_price', 0),
            'sale_price'    => (float) $this->input('sale_price', 0),
            'quantity'      => (int) $this->input('quantity', 0),
            'reorder_level' => (int) $this->input('reorder_level', 0),
        ];
    }
}
