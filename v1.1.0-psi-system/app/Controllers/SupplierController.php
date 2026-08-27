<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Supplier;

class SupplierController extends Controller
{
    public function index(): void
    {
        $this->view('suppliers/index', [
            'title'     => 'Suppliers',
            'suppliers' => Supplier::all('name'),
        ]);
    }

    public function create(): void
    {
        $this->view('suppliers/form', ['title' => 'Add Supplier', 'supplier' => null]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        Supplier::create([
            'name'    => trim($this->input('name')),
            'phone'   => trim($this->input('phone')),
            'email'   => trim($this->input('email')),
            'address' => trim($this->input('address')),
        ]);
        $this->flash('success', 'Supplier added.');
        $this->redirect('/suppliers');
    }

    public function edit(string $id): void
    {
        $supplier = Supplier::find($id);
        if (!$supplier) { $this->flash('error', 'Supplier not found.'); $this->redirect('/suppliers'); }
        $this->view('suppliers/form', ['title' => 'Edit Supplier', 'supplier' => $supplier]);
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        Supplier::update($id, [
            'name'    => trim($this->input('name')),
            'phone'   => trim($this->input('phone')),
            'email'   => trim($this->input('email')),
            'address' => trim($this->input('address')),
        ]);
        $this->flash('success', 'Supplier updated.');
        $this->redirect('/suppliers');
    }

    public function delete(string $id): void
    {
        $this->verifyCsrf();
        Supplier::delete($id);
        $this->flash('success', 'Supplier deleted.');
        $this->redirect('/suppliers');
    }
}
