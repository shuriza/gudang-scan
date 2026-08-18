<?php

use App\Models\Product;
use App\Models\StockMovement;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component
{
    public string $dateFrom = '';
    public string $dateTo = '';

    public function export(): StreamedResponse
    {
        $query = StockMovement::query()->with('product')->latest('id');

        if ($this->dateFrom !== '') {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $movements = $query->get();

        return response()->streamDownload(function () use ($movements): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['tanggal', 'produk', 'jenis', 'delta', 'sebelum', 'sesudah', 'catatan']);

            foreach ($movements as $movement) {
                fputcsv($handle, [
                    $movement->created_at?->toDateTimeString(),
                    $movement->product?->name,
                    $movement->type,
                    $movement->quantity,
                    $movement->stock_before,
                    $movement->stock_after,
                    $movement->note,
                ]);
            }

            fclose($handle);
        }, 'laporan-mutasi.csv');
    }

    public function with(): array
    {
        $lowStock = Product::query()
            ->whereNull('archived_at')
            ->where('min_stock', '>', 0)
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('name')
            ->get();

        $stockByUnit = Product::query()
            ->whereNull('archived_at')
            ->selectRaw('unit, SUM(stock) as total_stock')
            ->groupBy('unit')
            ->orderBy('unit')
            ->pluck('total_stock', 'unit');

        return compact('lowStock', 'stockByUnit');
    }
};
?>

<div class="space-y-4">
    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="text-sm font-semibold">Stok per satuan</h2>
        <div class="mt-2 flex flex-wrap gap-2">
            @forelse ($stockByUnit as $unit => $total)
                <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium">{{ $total }} {{ $unit }}</span>
            @empty
                <p class="text-sm text-slate-500">Belum ada stok.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="text-sm font-semibold">Stok menipis</h2>
        <div class="mt-2 space-y-2">
            @forelse ($lowStock as $product)
                <p class="text-sm">{{ $product->name }}: {{ $product->stock }} / min {{ $product->min_stock }} {{ $product->unit }}</p>
            @empty
                <p class="text-sm text-emerald-700">Tidak ada stok menipis.</p>
            @endforelse
        </div>
    </section>

    <form wire:submit="export" class="space-y-2 rounded-xl bg-white p-4 shadow-sm">
        <h2 class="text-sm font-semibold">Ekspor mutasi CSV</h2>
        <div class="grid grid-cols-2 gap-2">
            <input type="date" wire:model="dateFrom" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input type="date" wire:model="dateTo" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button type="submit" class="w-full rounded-lg bg-slate-900 py-3 text-sm font-semibold text-white">Unduh CSV</button>
    </form>
</div>
