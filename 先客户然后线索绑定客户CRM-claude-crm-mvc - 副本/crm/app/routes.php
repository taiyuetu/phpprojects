<?php

/** @var Router $router */

// ---- Auth ----
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->get('/register', 'AuthController@showRegister');
$router->post('/register', 'AuthController@register');
$router->post('/logout', 'AuthController@logout');

// ---- Dashboard ----
$router->get('/', 'DashboardController@index');

// ---- Customers ----
$router->get('/customers', 'CustomerController@index');
$router->get('/customers/create', 'CustomerController@create');
$router->post('/customers', 'CustomerController@store');
$router->get('/customers/{id}', 'CustomerController@show');
$router->get('/customers/{id}/edit', 'CustomerController@edit');
$router->put('/customers/{id}', 'CustomerController@update');
$router->delete('/customers/{id}', 'CustomerController@destroy');
$router->post('/customers/{id}/notes', 'CustomerController@addNote');

// ---- Leads ----
$router->get('/leads', 'LeadController@index');
$router->get('/leads/create', 'LeadController@create');
$router->post('/leads', 'LeadController@store');
$router->get('/leads/{id}/edit', 'LeadController@edit');
$router->put('/leads/{id}', 'LeadController@update');
$router->delete('/leads/{id}', 'LeadController@destroy');
$router->post('/leads/{id}/convert', 'LeadController@convert');

// ---- Help ----
$router->get('/help', 'HelpController@index');

// ---- Deals ----
$router->get('/deals', 'DealController@index');
$router->get('/deals/create', 'DealController@create');
$router->post('/deals', 'DealController@store');
$router->get('/deals/{id}/edit', 'DealController@edit');
$router->put('/deals/{id}', 'DealController@update');
$router->delete('/deals/{id}', 'DealController@destroy');
