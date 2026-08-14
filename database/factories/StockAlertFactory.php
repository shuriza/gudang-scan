<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAlert>
 */
class StockAlertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'status' => StockAlert::STATUS_OPEN,
            'stock_at_alert' => 0,
        ];
    }
}
