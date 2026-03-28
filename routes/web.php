<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::livewire('customers', 'pages::admin.customers-index')->name('customers.index');
        Route::livewire('vehicles', 'pages::admin.vehicles-index')->name('vehicles.index');
        Route::livewire('orders', 'pages::admin.orders-index')->name('orders.index');
        Route::livewire('shipments', 'pages::admin.shipments-index')->name('shipments.index');
        Route::livewire('delivery-numbers', 'pages::admin.delivery-numbers-index')->name('delivery-numbers.index');
    });
});

require __DIR__.'/settings.php';
