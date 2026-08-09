<?php

namespace Database\Seeders;

use App\Models\InventoryDocument;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class InventoryDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $supplier = Supplier::query()->first();
        $product = Product::query()->whereNull('archived_at')->first();

        if (! $supplier || ! $product) {
            return;
        }

        InventoryDocument::query()->firstOrCreate(
            ['number' => 'RCV-SEED'],
            [
                'type' => InventoryDocument::TYPE_RECEIPT,
                'status' => InventoryDocument::STATUS_DRAFT,
                'supplier_id' => $supplier->id,
                'document_date' => now()->toDateString(),
                'notes' => 'Draft penerimaan contoh',
            ]
        );
    }
}
