<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'dashboard')->name('dashboard');
Route::livewire('/scan', 'scan')->name('scan');
Route::livewire('/products', 'products')->name('products');
Route::livewire('/products/{product}', 'product-detail')->name('products.show');
Route::livewire('/history', 'history')->name('history');
Route::livewire('/locations', 'locations')->name('locations');
Route::livewire('/documents', 'documents')->name('documents');
Route::livewire('/opnames', 'opnames')->name('opnames');
Route::livewire('/reports', 'reports')->name('reports');
Route::livewire('/settings', 'settings')->name('settings');
Route::livewire('/alerts', 'alerts')->name('alerts');
