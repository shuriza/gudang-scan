<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Models\InventoryDocument;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class InventoryDocumentService
{
    public function post(InventoryDocument $document): InventoryDocument
    {
        return DB::transaction(function () use ($document): InventoryDocument {
            $lockedDocument = InventoryDocument::lockForUpdate()->with('items')->findOrFail($document->id);

            if ($lockedDocument->status !== InventoryDocument::STATUS_DRAFT) {
                throw new StockException('Dokumen ini sudah diproses.');
            }

            if ($lockedDocument->items->isEmpty()) {
                throw new StockException('Dokumen harus memiliki minimal satu item.');
            }

            $products = Product::query()
                ->whereIn('id', $lockedDocument->items->pluck('product_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($lockedDocument->items as $item) {
                $product = $products->get($item->product_id);

                if (! $product || $product->isArchived()) {
                    throw new StockException('Dokumen memuat produk yang tidak aktif.');
                }

                $before = $product->stock;
                $delta = $lockedDocument->type === InventoryDocument::TYPE_RECEIPT ? $item->quantity : -$item->quantity;
                $after = $before + $delta;

                if ($after < 0) {
                    throw new StockException("Stok {$product->name} tidak mencukupi.");
                }

                $product->forceFill(['stock' => $after])->save();
                $product->movements()->create([
                    'inventory_document_id' => $lockedDocument->id,
                    'inventory_document_item_id' => $item->id,
                    'type' => $delta > 0 ? StockMovement::TYPE_IN : StockMovement::TYPE_OUT,
                    'quantity' => $delta,
                    'stock_before' => $before,
                    'stock_after' => $after,
                    'note' => $lockedDocument->number,
                ]);
            }

            $lockedDocument->forceFill([
                'status' => InventoryDocument::STATUS_POSTED,
                'posted_at' => now(),
            ])->save();

            return $lockedDocument;
        });
    }

    public function cancel(InventoryDocument $document): InventoryDocument
    {
        return DB::transaction(function () use ($document): InventoryDocument {
            $lockedDocument = InventoryDocument::lockForUpdate()->with(['movements' => fn ($query) => $query->orderByDesc('id')])->findOrFail($document->id);

            if ($lockedDocument->status !== InventoryDocument::STATUS_POSTED) {
                throw new StockException('Hanya dokumen selesai yang dapat dibatalkan.');
            }

            $products = Product::query()->whereIn('id', $lockedDocument->movements->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($lockedDocument->movements as $movement) {
                $product = $products->get($movement->product_id);
                $after = $product->stock - $movement->quantity;

                if ($after < 0) {
                    throw new StockException("Pembatalan membuat stok {$product->name} negatif.");
                }

                $before = $product->stock;
                $product->forceFill(['stock' => $after])->save();
                $product->movements()->create([
                    'inventory_document_id' => $lockedDocument->id,
                    'inventory_document_item_id' => $movement->inventory_document_item_id,
                    'type' => StockMovement::TYPE_ADJUST,
                    'quantity' => -$movement->quantity,
                    'stock_before' => $before,
                    'stock_after' => $after,
                    'note' => "Pembatalan {$lockedDocument->number}",
                ]);
            }

            $lockedDocument->forceFill(['status' => InventoryDocument::STATUS_CANCELLED, 'cancelled_at' => now()])->save();

            return $lockedDocument;
        });
    }
}
