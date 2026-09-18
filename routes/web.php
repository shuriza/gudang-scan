<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'dashboard')->name('dashboard');
Route::livewire('/scan', 'scan')->name('scan');
Route::livewire('/products', 'products')->name('products');
Route::livewire('/history', 'history')->name('history');
