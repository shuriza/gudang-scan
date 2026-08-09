<?php

namespace Database\Factories;

use App\Models\InventoryDocument;
use App\Models\InventoryDocumentItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryDocumentItem>
 */
class InventoryDocumentItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'inventory_document_id' => InventoryDocument::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 10),
        ];
    }
}
