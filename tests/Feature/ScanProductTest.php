<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScanProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_archived_barcode_shows_reactivation_guidance(): void
    {
        $product = Product::create([
            'barcode' => '8999999999999',
            'name' => 'Produk Arsip',
            'unit' => 'pcs',
            'stock' => 0,
            'min_stock' => 1,
        ]);
        $product->forceFill(['archived_at' => now()])->save();

        Livewire::test('scan')
            ->set('manualBarcode', $product->barcode)
            ->call('submitManual')
            ->assertSet('productId', null)
            ->assertSet('barcode', $product->barcode)
            ->assertSee('Aktifkan kembali dari menu Produk.');
    }
}
