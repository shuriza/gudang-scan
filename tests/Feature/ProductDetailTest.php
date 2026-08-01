<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_detail_shows_inventory_and_only_its_movements(): void
    {
        $product = $this->product('8991000000001', 'Kopi Arabika', 8, 10);
        $other = $this->product('8991000000002', 'Produk Lain', 20, 5);
        $this->movement($product, StockMovement::TYPE_IN, 10, 0, 10, 'Stok awal');
        $this->movement($product, StockMovement::TYPE_OUT, -2, 10, 8, 'Kirim cabang');
        $this->movement($other, StockMovement::TYPE_IN, 20, 0, 20, 'Mutasi produk lain');

        Livewire::test('product-detail', ['product' => $product])
            ->assertSee('Kopi Arabika')
            ->assertSee('Stok menipis')
            ->assertSee('Masuk')
            ->assertSee('Keluar')
            ->assertSee('Koreksi')
            ->assertSee('Edit Master')
            ->assertSee('Stok awal')
            ->assertSee('Kirim cabang')
            ->assertSeeInOrder(['Kirim cabang', 'Stok awal'])
            ->assertDontSee('Mutasi produk lain');
    }

    public function test_archived_product_detail_is_read_only(): void
    {
        $product = $this->product('8991000000001', 'Produk Arsip', 0, 5);
        $product->forceFill(['archived_at' => now()])->save();

        Livewire::test('product-detail', ['product' => $product])
            ->assertSee('Diarsipkan')
            ->assertDontSee('Mutasi Stok')
            ->assertDontSee('Edit Master');
    }

    public function test_product_detail_route_returns_not_found_for_missing_product(): void
    {
        $this->get('/products/999999')->assertNotFound();
    }

    public function test_scan_deep_link_selects_product_and_movement_type(): void
    {
        $product = $this->product('8991000000001', 'Kopi Arabika', 8, 10);

        $this->get('/scan?barcode='.$product->barcode.'&type=out')
            ->assertOk()
            ->assertSee('Kopi Arabika')
            ->assertSee('Simpan Mutasi');

        Livewire::withQueryParams(['barcode' => $product->barcode, 'type' => 'adjust'])
            ->test('scan')
            ->assertSet('productId', $product->id)
            ->assertSet('type', StockMovement::TYPE_ADJUST);
    }

    public function test_edit_deep_link_opens_product_master_form(): void
    {
        $product = $this->product('8991000000001', 'Kopi Arabika', 8, 10);

        Livewire::withQueryParams(['edit' => $product->id])
            ->test('products')
            ->assertSet('showForm', true)
            ->assertSet('editingProductId', $product->id)
            ->assertSet('name', 'Kopi Arabika');
    }

    private function product(string $barcode, string $name, int $stock, int $minimum): Product
    {
        $location = Location::firstOrCreate(['code' => 'A-01'], ['name' => 'Rak A-01']);

        return Product::create([
            'barcode' => $barcode,
            'name' => $name,
            'unit' => 'pcs',
            'location_id' => $location->id,
            'stock' => $stock,
            'min_stock' => $minimum,
        ]);
    }

    private function movement(Product $product, string $type, int $quantity, int $before, int $after, string $note): StockMovement
    {
        return $product->movements()->create([
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => $before,
            'stock_after' => $after,
            'note' => $note,
        ]);
    }
}
