<?php

/** @var Router $router
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

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
$router->post('/customers/{id}/follow-ups', 'CustomerController@addFollowUp');
$router->post('/customers/{id}/attachments', 'CustomerController@uploadAttachment');
$router->post('/customers/{id}/attachments/{attachmentId}/delete', 'CustomerController@deleteAttachment');

// ---- Leads ----
$router->get('/leads', 'LeadController@index');
$router->get('/leads/create', 'LeadController@create');
$router->post('/leads', 'LeadController@store');
$router->get('/leads/{id}/edit', 'LeadController@edit');
$router->put('/leads/{id}', 'LeadController@update');
$router->delete('/leads/{id}', 'LeadController@destroy');
$router->post('/leads/{id}/convert', 'LeadController@convert');
$router->post('/leads/{id}/lost', 'LeadController@markLost');
$router->post('/leads/{id}/reactivate', 'LeadController@reactivate');

// ---- AI 助手 ----
$router->get('/ai', 'AiController@index');
$router->post('/ai/plan', 'AiController@plan');
$router->post('/ai/apply', 'AiController@apply');
$router->post('/ai/cancel', 'AiController@cancel');
$router->get('/ai/history', 'AiController@history');
$router->post('/ai/history/{id}/delete', 'AiController@destroyRequest');
$router->post('/ai/test', 'AiController@test');

// ---- Help ----
$router->get('/help', 'HelpController@index');
$router->get('/help/context', 'HelpController@context');

// ---- Settings (应用信息 + 个人信息) ----
$router->get('/settings', 'SettingController@index');
$router->post('/settings/app', 'SettingController@updateApp');
$router->post('/settings/app/reset', 'SettingController@resetApp');
$router->post('/settings/profile', 'SettingController@updateProfile');
$router->post('/settings/password', 'SettingController@updatePassword');

// ---- Deals ----
$router->get('/deals', 'DealController@index');
$router->get('/deals/create', 'DealController@create');
$router->post('/deals', 'DealController@store');
$router->get('/deals/{id}/edit', 'DealController@edit');
$router->put('/deals/{id}', 'DealController@update');
$router->delete('/deals/{id}', 'DealController@destroy');
$router->get('/deals/archived', 'DealController@archived');
$router->post('/deals/{id}/unarchive', 'DealController@unarchive');
$router->post('/deals/{id}/create-order', 'OrderController@createFromDeal');
$router->post('/deals/{id}/attachments', 'DealController@uploadAttachment');
$router->post('/deals/{id}/attachments/{attachmentId}/delete', 'DealController@deleteAttachment');

// ---- Orders ----
$router->get('/orders', 'OrderController@index');
$router->get('/orders/create', 'OrderController@create');
$router->post('/orders', 'OrderController@store');
$router->get('/orders/{id}', 'OrderController@show');
$router->get('/orders/{id}/edit', 'OrderController@edit');
$router->put('/orders/{id}', 'OrderController@update');
$router->delete('/orders/{id}', 'OrderController@destroy');
$router->post('/orders/{id}/attachments', 'OrderController@uploadAttachment');
$router->post('/orders/{id}/attachments/{attachmentId}/delete', 'OrderController@deleteAttachment');
