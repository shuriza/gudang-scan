<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockAlert;

class StockAlertService
{
    public function sync(Product $product): void
    {
        $openAlert = StockAlert::query()
            ->where('product_id', $product->id)
            ->where('status', StockAlert::STATUS_OPEN)
            ->first();

        if ($product->isArchived() || ! $product->isLowStock()) {
            if ($openAlert) {
                $openAlert->forceFill([
                    'status' => StockAlert::STATUS_RESOLVED,
                    'resolved_at' => now(),
                ])->save();
            }

            return;
        }

        if ($openAlert) {
            return;
        }

        StockAlert::create([
            'product_id' => $product->id,
            'status' => StockAlert::STATUS_OPEN,
            'stock_at_alert' => $product->stock,
        ]);
    }
}
