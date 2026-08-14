<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Terapkan mutasi stok secara atomik.
     *
     * Baris produk dikunci di dalam transaksi agar pembacaan stok dan
     * penulisannya tidak dapat disisipi scan lain (lost update).
     *
     * `quantity` yang tersimpan selalu berupa delta bertanda
     * (stock_after - stock_before), sehingga arah mutasi terbaca dari
     * datanya sendiri tanpa perlu menafsirkan kolom `type`.
     *
     * @param  'in'|'out'|'adjust'  $type
     * @param  int  $quantity  Untuk in/out: jumlah positif. Untuk adjust: nilai stok akhir.
     */
    public function apply(Product $product, string $type, int $quantity, ?string $note = null): StockMovement
    {
        if (! in_array($type, [StockMovement::TYPE_IN, StockMovement::TYPE_OUT, StockMovement::TYPE_ADJUST], true)) {
            throw new StockException("Jenis mutasi tidak dikenal: {$type}");
        }

        if ($type === StockMovement::TYPE_ADJUST) {
            if ($quantity < 0) {
                throw new StockException('Stok hasil penyesuaian tidak boleh negatif.');
            }
        } elseif ($quantity < 1) {
            throw new StockException('Jumlah harus minimal 1.');
        }

        return DB::transaction(function () use ($product, $type, $quantity, $note) {
            $locked = Product::lockForUpdate()->findOrFail($product->getKey());

            if ($locked->isArchived()) {
                throw new StockException("Produk {$locked->name} sudah diarsipkan dan tidak dapat dimutasi.");
            }

            $before = $locked->stock;

            $after = match ($type) {
                StockMovement::TYPE_IN => $before + $quantity,
                StockMovement::TYPE_OUT => $before - $quantity,
                StockMovement::TYPE_ADJUST => $quantity,
            };

            if ($after < 0) {
                throw new StockException(
                    "Stok {$locked->name} tinggal {$before} {$locked->unit}, tidak cukup untuk keluar {$quantity}."
                );
            }

            $locked->forceFill(['stock' => $after])->save();

            $movement = $locked->movements()->create([
                'type' => $type,
                'quantity' => $after - $before,
                'stock_before' => $before,
                'stock_after' => $after,
                'note' => $note,
            ]);

            $product->setRawAttributes($locked->getAttributes(), true);

            app(StockAlertService::class)->sync($locked);

            return $movement;
        });
    }
}
