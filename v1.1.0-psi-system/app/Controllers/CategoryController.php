<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index(): void
    {
        $this->view('categories/index', [
            'title'      => 'Categories',
            'categories' => Category::all('name'),
        ]);
    }

    public function create(): void
    {
        $this->view('categories/form', ['title' => 'Add Category', 'category' => null]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        Category::create(['name' => trim($this->input('name'))]);
        $this->flash('success', 'Category added.');
        $this->redirect('/categories');
    }

    public function edit(string $id): void
    {
        $category = Category::find($id);
        if (!$category) { $this->flash('error', 'Category not found.'); $this->redirect('/categories'); }
        $this->view('categories/form', ['title' => 'Edit Category', 'category' => $category]);
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        Category::update($id, ['name' => trim($this->input('name'))]);
        $this->flash('success', 'Category updated.');
        $this->redirect('/categories');
    }

    public function delete(string $id): void
    {
        $this->verifyCsrf();
        Category::delete($id);
        $this->flash('success', 'Category deleted.');
        $this->redirect('/categories');
    }
}
