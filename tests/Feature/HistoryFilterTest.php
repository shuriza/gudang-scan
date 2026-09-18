<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HistoryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_can_be_searched_by_product_barcode_and_note(): void
    {
        $coffee = $this->product('8991000000001', 'Kopi Arabika');
        $soap = $this->product('8991000000002', 'Sabun Mandi');
        $this->movement($coffee, StockMovement::TYPE_IN, 5, 'Penerimaan supplier', '2026-09-10 08:00:00');
        $this->movement($soap, StockMovement::TYPE_OUT, -2, 'Kirim toko', '2026-09-11 08:00:00');

        Livewire::test('history')
            ->set('search', 'Arabika')
            ->assertSee('Kopi Arabika')
            ->assertDontSee('Sabun Mandi')
            ->set('search', '8991000000002')
            ->assertSee('Sabun Mandi')
            ->assertDontSee('Kopi Arabika')
            ->set('search', 'supplier')
            ->assertSee('Penerimaan supplier')
            ->assertDontSee('Kirim toko');
    }

    public function test_history_can_be_filtered_by_type_and_date_range(): void
    {
        $product = $this->product('8991000000001', 'Kopi Arabika');
        $this->movement($product, StockMovement::TYPE_IN, 5, 'Masuk lama', '2026-09-01 08:00:00');
        $this->movement($product, StockMovement::TYPE_OUT, -2, 'Keluar rentang', '2026-09-10 08:00:00');
        $this->movement($product, StockMovement::TYPE_OUT, -1, 'Keluar baru', '2026-09-20 08:00:00');

        Livewire::test('history')
            ->set('type', StockMovement::TYPE_OUT)
            ->set('dateFrom', '2026-09-05')
            ->set('dateTo', '2026-09-15')
            ->assertSee('Keluar rentang')
            ->assertDontSee('Masuk lama')
            ->assertDontSee('Keluar baru')
            ->call('clearFilters')
            ->assertSee('Masuk lama')
            ->assertSee('Keluar baru');
    }

    private function product(string $barcode, string $name): Product
    {
        return Product::create([
            'barcode' => $barcode,
            'name' => $name,
            'unit' => 'pcs',
            'stock' => 10,
            'min_stock' => 2,
        ]);
    }

    private function movement(Product $product, string $type, int $quantity, string $note, string $createdAt): StockMovement
    {
        $movement = $product->movements()->create([
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => 10,
            'stock_after' => 10 + $quantity,
            'note' => $note,
        ]);
        $movement->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $movement;
    }
}
