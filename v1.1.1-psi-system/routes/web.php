<?php
/**
 * Route table.
 * Add a new module? Add its routes here — nothing else to configure.
 */
use App\Core\Router;

// Auth
Router::get('/login', 'AuthController@showLogin');
Router::post('/login', 'AuthController@login');
Router::get('/logout', 'AuthController@logout');

// Dashboard
Router::get('/', 'DashboardController@index');
Router::get('/dashboard', 'DashboardController@index');

// Categories
Router::get('/categories', 'CategoryController@index');
Router::get('/categories/create', 'CategoryController@create');
Router::post('/categories', 'CategoryController@store');
Router::get('/categories/{id}/edit', 'CategoryController@edit');
Router::post('/categories/{id}', 'CategoryController@update');
Router::post('/categories/{id}/delete', 'CategoryController@delete');

// Suppliers
Router::get('/suppliers', 'SupplierController@index');
Router::get('/suppliers/create', 'SupplierController@create');
Router::post('/suppliers', 'SupplierController@store');
Router::get('/suppliers/{id}/edit', 'SupplierController@edit');
Router::post('/suppliers/{id}', 'SupplierController@update');
Router::post('/suppliers/{id}/delete', 'SupplierController@delete');

// Customers
Router::get('/customers', 'CustomerController@index');
Router::get('/customers/create', 'CustomerController@create');
Router::post('/customers', 'CustomerController@store');
Router::get('/customers/{id}/edit', 'CustomerController@edit');
Router::post('/customers/{id}', 'CustomerController@update');
Router::post('/customers/{id}/delete', 'CustomerController@delete');

// Products
Router::get('/products', 'ProductController@index');
Router::get('/products/create', 'ProductController@create');
Router::post('/products', 'ProductController@store');
Router::get('/products/{id}', 'ProductController@show');
Router::get('/products/{id}/edit', 'ProductController@edit');
Router::post('/products/{id}', 'ProductController@update');
Router::post('/products/{id}/delete', 'ProductController@delete');

// Purchases
Router::get('/purchases', 'PurchaseController@index');
Router::get('/purchases/create', 'PurchaseController@create');
Router::post('/purchases', 'PurchaseController@store');
Router::get('/purchases/{id}', 'PurchaseController@show');

// Sales
Router::get('/sales', 'SaleController@index');
Router::get('/sales/create', 'SaleController@create');
Router::post('/sales', 'SaleController@store');
Router::get('/sales/{id}', 'SaleController@show');

// Inventory
Router::get('/inventory', 'InventoryController@index');
Router::get('/inventory/low-stock', 'InventoryController@lowStock');

// Reports
Router::get('/reports/sales', 'ReportController@salesReport');
Router::get('/reports/purchases', 'ReportController@purchaseReport');
Router::get('/reports/stock', 'ReportController@stockReport');

// Change Logs
Router::get('/changelogs', 'ChangeLogController@index');
Router::get('/changelogs/table/{tableName}', 'ChangeLogController@table');
Router::get('/changelogs/record/{tableName}/{recordId}', 'ChangeLogController@record');
Router::get('/changelogs/{id}', 'ChangeLogController@show');

// Change Log Admin
Router::get('/admin/changelogs', 'ChangeLogAdminController@index');
Router::post('/admin/changelogs/archive', 'ChangeLogAdminController@archive');
Router::post('/admin/changelogs/cleanup', 'ChangeLogAdminController@cleanup');
Router::get('/admin/changelogs/stats', 'ChangeLogAdminController@stats');
