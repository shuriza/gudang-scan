<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_can_be_created_from_product_page(): void
    {
        Livewire::test('products')
            ->call('createProduct')
            ->set('barcode', ' 8999999999999 ')
            ->set('name', ' Produk Baru ')
            ->set('unit', ' dus ')
            ->set('location', ' D-01 ')
            ->set('stock', 12)
            ->set('minStock', 4)
            ->call('saveProduct')
            ->assertSet('showForm', false)
            ->assertSee('Produk berhasil ditambahkan.');

        $this->assertDatabaseHas('products', [
            'barcode' => '8999999999999',
            'name' => 'Produk Baru',
            'unit' => 'dus',
            'location' => 'D-01',
            'stock' => 12,
            'min_stock' => 4,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => Product::where('barcode', '8999999999999')->value('id'),
            'type' => StockMovement::TYPE_IN,
            'quantity' => 12,
            'stock_before' => 0,
            'stock_after' => 12,
            'note' => 'Stok awal produk',
        ]);
    }

    public function test_barcode_must_be_unique(): void
    {
        Product::create([
            'barcode' => '8999999999999',
            'name' => 'Produk Lama',
            'unit' => 'pcs',
            'stock' => 1,
            'min_stock' => 0,
        ]);

        Livewire::test('products')
            ->call('createProduct')
            ->set('barcode', '8999999999999')
            ->set('name', 'Produk Baru')
            ->set('unit', 'pcs')
            ->set('stock', 5)
            ->call('saveProduct')
            ->assertHasErrors(['barcode' => 'unique']);

        $this->assertSame(1, Product::count());
    }

    public function test_product_details_can_be_edited_without_bypassing_stock_history(): void
    {
        $product = Product::create([
            'barcode' => '8999999999999',
            'name' => 'Produk Lama',
            'unit' => 'pcs',
            'location' => 'A-01',
            'stock' => 25,
            'min_stock' => 5,
        ]);

        Livewire::test('products')
            ->call('editProduct', $product->id)
            ->set('barcode', '8999999999998')
            ->set('name', 'Produk Diperbarui')
            ->set('unit', 'karton')
            ->set('location', '')
            ->set('stock', 999)
            ->set('minStock', 8)
            ->call('saveProduct')
            ->assertSet('showForm', false)
            ->assertSee('Produk berhasil diperbarui.');

        $product->refresh();

        $this->assertSame('8999999999998', $product->barcode);
        $this->assertSame('Produk Diperbarui', $product->name);
        $this->assertSame('karton', $product->unit);
        $this->assertNull($product->location);
        $this->assertSame(25, $product->stock);
        $this->assertSame(8, $product->min_stock);
        $this->assertSame(0, StockMovement::count());
    }
}
