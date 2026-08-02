<?php

namespace Tests\Feature;

use App\Exceptions\StockException;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_can_be_created_from_product_page(): void
    {
        $location = Location::create(['code' => 'D-01', 'name' => 'Rak D-01']);

        Livewire::test('products')
            ->call('createProduct')
            ->set('barcode', ' 8999999999999 ')
            ->set('name', ' Produk Baru ')
            ->set('unit', ' dus ')
            ->set('locationId', $location->id)
            ->set('stock', 12)
            ->set('minStock', 4)
            ->call('saveProduct')
            ->assertSet('showForm', false)
            ->assertSee('Produk berhasil ditambahkan.');

        $this->assertDatabaseHas('products', [
            'barcode' => '8999999999999',
            'name' => 'Produk Baru',
            'unit' => 'dus',
            'location_id' => $location->id,
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
        $location = Location::create(['code' => 'A-01', 'name' => 'Rak A-01']);
        $product = Product::create([
            'barcode' => '8999999999999',
            'name' => 'Produk Lama',
            'unit' => 'pcs',
            'location_id' => $location->id,
            'stock' => 25,
            'min_stock' => 5,
        ]);

        Livewire::test('products')
            ->call('editProduct', $product->id)
            ->set('barcode', '8999999999998')
            ->set('name', 'Produk Diperbarui')
            ->set('unit', 'karton')
            ->set('locationId', null)
            ->set('stock', 999)
            ->set('minStock', 8)
            ->call('saveProduct')
            ->assertSet('showForm', false)
            ->assertSee('Produk berhasil diperbarui.');

        $product->refresh();

        $this->assertSame('8999999999998', $product->barcode);
        $this->assertSame('Produk Diperbarui', $product->name);
        $this->assertSame('karton', $product->unit);
        $this->assertNull($product->location_id);
        $this->assertSame(25, $product->stock);
        $this->assertSame(8, $product->min_stock);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_product_with_stock_cannot_be_archived(): void
    {
        $product = Product::create([
            'barcode' => '8999999999999',
            'name' => 'Produk Aktif',
            'unit' => 'pcs',
            'stock' => 5,
            'min_stock' => 1,
        ]);

        Livewire::test('products')
            ->call('editProduct', $product->id)
            ->call('archiveProduct')
            ->assertHasErrors(['archive']);

        $this->assertNull($product->fresh()->archived_at);
    }

    public function test_zero_stock_product_can_be_archived_and_restored(): void
    {
        $product = Product::create([
            'barcode' => '8999999999999',
            'name' => 'Produk Kosong',
            'unit' => 'pcs',
            'stock' => 0,
            'min_stock' => 1,
        ]);

        Livewire::test('products')
            ->call('editProduct', $product->id)
            ->call('archiveProduct')
            ->assertSet('showForm', false)
            ->assertSee('Produk berhasil diarsipkan.')
            ->set('showArchived', true)
            ->assertSee('Produk Kosong')
            ->call('restoreProduct', $product->id)
            ->assertSee('Produk berhasil diaktifkan kembali.');

        $this->assertNull($product->fresh()->archived_at);
    }

    public function test_archived_product_cannot_receive_stock_movements(): void
    {
        $product = Product::create([
            'barcode' => '8999999999999',
            'name' => 'Produk Arsip',
            'unit' => 'pcs',
            'stock' => 0,
            'min_stock' => 1,
        ]);
        $product->forceFill(['archived_at' => now()])->save();

        $this->expectException(StockException::class);
        $this->expectExceptionMessage('sudah diarsipkan');

        app(StockService::class)->apply($product, StockMovement::TYPE_IN, 1);
    }
}
