<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summarizes_only_active_inventory(): void
    {
        $healthy = $this->product('8991000000001', 'Stok Aman', 20, 5, 'pcs');
        $low = $this->product('8991000000002', 'Perlu Restock', 2, 10);
        $this->product('8991000000003', 'Stok Karton', 4, 0, 'karton');
        $archived = $this->product('8991000000004', 'Produk Arsip', 50, 10);
        $archived->forceFill(['archived_at' => now()])->save();
        $this->movement($healthy, StockMovement::TYPE_IN, 5, now());
        $this->movement($low, StockMovement::TYPE_OUT, -2, now()->subDay());

        Livewire::test('dashboard')
            ->assertSee('Produk aktif')
            ->assertSee('Stok per satuan')
            ->assertSeeInOrder(['Produk aktif', '3'])
            ->assertSee('22 pcs')
            ->assertSee('4 karton')
            ->assertSeeInOrder(['Stok menipis', '1'])
            ->assertSeeInOrder(['Mutasi hari ini', '1'])
            ->assertSee('Perlu Restock')
            ->assertDontSee('Produk Arsip');
    }

    public function test_restock_priority_orders_largest_shortage_first_and_limits_results(): void
    {
        foreach ([
            ['Paling Mendesak', 0, 20],
            ['Prioritas Dua', 2, 15],
            ['Prioritas Tiga', 3, 12],
            ['Prioritas Empat', 5, 10],
            ['Prioritas Lima', 6, 10],
            ['Di Luar Batas', 9, 10],
        ] as $index => [$name, $stock, $minimum]) {
            $this->product('89910000000'.($index + 10), $name, $stock, $minimum);
        }

        Livewire::test('dashboard')
            ->assertSeeInOrder(['Paling Mendesak', 'Prioritas Dua', 'Prioritas Tiga', 'Prioritas Empat', 'Prioritas Lima'])
            ->assertDontSee('Di Luar Batas');
    }

    private function product(string $barcode, string $name, int $stock, int $minimum, string $unit = 'pcs'): Product
    {
        return Product::create([
            'barcode' => $barcode,
            'name' => $name,
            'unit' => $unit,
            'stock' => $stock,
            'min_stock' => $minimum,
        ]);
    }

    private function movement(Product $product, string $type, int $quantity, mixed $createdAt): StockMovement
    {
        $movement = $product->movements()->create([
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => 10,
            'stock_after' => 10 + $quantity,
        ]);
        $movement->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $movement;
    }
}
