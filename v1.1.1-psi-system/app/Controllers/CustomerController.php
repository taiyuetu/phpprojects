<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Customer;

class CustomerController extends Controller
{
    public function index(): void
    {
        $this->view('customers/index', [
            'title'     => 'Customers',
            'customers' => Customer::all('name'),
        ]);
    }

    public function create(): void
    {
        $this->view('customers/form', ['title' => 'Add Customer', 'customer' => null]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        Customer::create([
            'name'    => trim($this->input('name')),
            'phone'   => trim($this->input('phone')),
            'email'   => trim($this->input('email')),
            'address' => trim($this->input('address')),
        ]);
        $this->flash('success', 'Customer added.');
        $this->redirect('/customers');
    }

    public function edit(string $id): void
    {
        $customer = Customer::find($id);
        if (!$customer) { $this->flash('error', 'Customer not found.'); $this->redirect('/customers'); }
        $this->view('customers/form', ['title' => 'Edit Customer', 'customer' => $customer]);
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        Customer::update($id, [
            'name'    => trim($this->input('name')),
            'phone'   => trim($this->input('phone')),
            'email'   => trim($this->input('email')),
            'address' => trim($this->input('address')),
        ]);
        $this->flash('success', 'Customer updated.');
        $this->redirect('/customers');
    }

    public function delete(string $id): void
    {
        $this->verifyCsrf();
        Customer::delete($id);
        $this->flash('success', 'Customer deleted.');
        $this->redirect('/customers');
    }
}
