<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'scan')->name('scan');
Route::livewire('/products', 'products')->name('products');
Route::livewire('/history', 'history')->name('history');
