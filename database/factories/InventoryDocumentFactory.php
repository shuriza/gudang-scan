<?php

namespace Database\Factories;

use App\Models\InventoryDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryDocument>
 */
class InventoryDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => strtoupper(fake()->unique()->bothify('DOC-####')),
            'type' => InventoryDocument::TYPE_RECEIPT,
            'status' => InventoryDocument::STATUS_DRAFT,
            'document_date' => now()->toDateString(),
        ];
    }
}
