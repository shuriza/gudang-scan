<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockAlert;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_creates_one_open_alert_and_resolves_when_restored(): void
    {
        $product = Product::create([
            'barcode' => '8991000000088',
            'name' => 'Alert Item',
            'unit' => 'pcs',
            'stock' => 2,
            'min_stock' => 5,
        ]);

        $service = app(StockService::class);
        $service->apply($product, StockMovement::TYPE_OUT, 1);
        $service->apply($product, StockMovement::TYPE_OUT, 1);

        $this->assertSame(1, StockAlert::query()->where('status', StockAlert::STATUS_OPEN)->count());

        $service->apply($product->fresh(), StockMovement::TYPE_IN, 10);

        $this->assertSame(0, StockAlert::query()->where('status', StockAlert::STATUS_OPEN)->count());
        $this->assertSame(1, StockAlert::query()->where('status', StockAlert::STATUS_RESOLVED)->count());
    }
}
