<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockOpname;
use Illuminate\Support\Facades\DB;

class StockOpnameService
{
    public function finalize(StockOpname $opname, StockService $stock): StockOpname
    {
        return DB::transaction(function () use ($opname, $stock): StockOpname {
            $locked = StockOpname::lockForUpdate()->with('items')->findOrFail($opname->id);

            if ($locked->status !== StockOpname::STATUS_DRAFT) {
                throw new StockException('Sesi opname sudah difinalisasi.');
            }

            $uncounted = $locked->items->first(fn ($item) => $item->counted_stock === null);

            if ($uncounted) {
                throw new StockException('Semua item harus dihitung sebelum finalisasi.');
            }

            foreach ($locked->items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item->product_id);
                $stock->apply(
                    $product,
                    StockMovement::TYPE_ADJUST,
                    (int) $item->counted_stock,
                    'Opname '.$locked->number,
                );
            }

            $locked->forceFill([
                'status' => StockOpname::STATUS_FINALIZED,
                'finalized_at' => now(),
            ])->save();

            return $locked;
        });
    }
}
