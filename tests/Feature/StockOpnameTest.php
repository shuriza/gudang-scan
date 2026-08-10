<?php

namespace Tests\Feature;

use App\Exceptions\StockException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Services\StockOpnameService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOpnameTest extends TestCase
{
    use RefreshDatabase;

    public function test_opname_finalizes_only_counted_items_and_posts_adjustments(): void
    {
        $product = Product::create(['barcode' => '8991000000099', 'name' => 'Opname Item', 'unit' => 'pcs', 'stock' => 10, 'min_stock' => 0]);
        $opname = StockOpname::create(['number' => 'OP-1', 'status' => StockOpname::STATUS_DRAFT]);
        $item = $opname->items()->create(['product_id' => $product->id, 'system_stock' => 10, 'counted_stock' => 7]);

        app(StockOpnameService::class)->finalize($opname, app(StockService::class));

        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(StockOpname::STATUS_FINALIZED, $opname->fresh()->status);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovement::TYPE_ADJUST,
            'quantity' => -3,
            'stock_before' => 10,
            'stock_after' => 7,
        ]);
        $this->assertNotNull($item->fresh()->counted_stock);
    }

    public function test_uncounted_opname_items_cannot_be_finalized(): void
    {
        $product = Product::create(['barcode' => '8991000000077', 'name' => 'Belum Dihitung', 'unit' => 'pcs', 'stock' => 4, 'min_stock' => 0]);
        $opname = StockOpname::create(['number' => 'OP-2', 'status' => StockOpname::STATUS_DRAFT]);
        $opname->items()->create(['product_id' => $product->id, 'system_stock' => 4]);

        $this->expectException(StockException::class);
        app(StockOpnameService::class)->finalize($opname, app(StockService::class));
    }
}
