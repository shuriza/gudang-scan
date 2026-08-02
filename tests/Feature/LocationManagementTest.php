<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Product;
use Database\Seeders\LocationSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LocationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_can_be_created_and_code_is_normalized(): void
    {
        Livewire::test('locations')
            ->set('code', ' a-01 ')
            ->set('name', ' Rak Depan ')
            ->call('saveLocation')
            ->assertSee('Lokasi berhasil disimpan.');

        $this->assertDatabaseHas('locations', ['code' => 'A-01', 'name' => 'Rak Depan']);
    }

    public function test_used_location_cannot_be_archived(): void
    {
        $location = Location::create(['code' => 'A-01', 'name' => 'Rak Depan']);
        Product::create([
            'barcode' => '8991000000001',
            'name' => 'Produk',
            'unit' => 'pcs',
            'location_id' => $location->id,
            'stock' => 1,
            'min_stock' => 0,
        ]);

        Livewire::test('locations')
            ->call('archiveLocation', $location->id)
            ->assertHasErrors(['archive']);

        $this->assertNull($location->fresh()->archived_at);
    }

    public function test_unused_location_can_be_archived_and_restored(): void
    {
        $location = Location::create(['code' => 'A-01', 'name' => 'Rak Depan']);

        Livewire::test('locations')
            ->call('archiveLocation', $location->id)
            ->assertSee('Lokasi berhasil diarsipkan.')
            ->set('showArchived', true)
            ->assertSee('A-01')
            ->call('restoreLocation', $location->id)
            ->assertSee('Lokasi berhasil diaktifkan kembali.');

        $this->assertNull($location->fresh()->archived_at);
    }

    public function test_products_can_be_filtered_by_location(): void
    {
        $front = Location::create(['code' => 'A-01', 'name' => 'Rak Depan']);
        $back = Location::create(['code' => 'B-01', 'name' => 'Rak Belakang']);
        $this->product('Produk Depan', '8991000000001', $front);
        $this->product('Produk Belakang', '8991000000002', $back);

        Livewire::test('products')
            ->set('locationFilter', (string) $front->id)
            ->assertSee('Produk Depan')
            ->assertDontSee('Produk Belakang');
    }

    public function test_seeders_link_products_without_overwriting_location_names(): void
    {
        $location = Location::create(['code' => 'A-01', 'name' => 'Rak Depan']);

        $this->seed(LocationSeeder::class);
        $this->seed(ProductSeeder::class);

        $this->assertSame('Rak Depan', $location->fresh()->name);
        $this->assertDatabaseHas('products', [
            'barcode' => '8991002101012',
            'location_id' => $location->id,
        ]);
        $this->assertTrue(Product::where('barcode', '8991002101012')->firstOrFail()->location->is($location));
    }

    private function product(string $name, string $barcode, Location $location): Product
    {
        return Product::create([
            'barcode' => $barcode,
            'name' => $name,
            'unit' => 'pcs',
            'location_id' => $location->id,
            'stock' => 1,
            'min_stock' => 0,
        ]);
    }
}
