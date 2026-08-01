<?php

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Location;
use Livewire\Component;

new class extends Component
{
    public function with(): array
    {
        $activeProducts = Product::query()->whereNull('archived_at');

        return [
            'totalProducts' => (clone $activeProducts)->count(),
            'stockByUnit' => (clone $activeProducts)
                ->selectRaw('unit, SUM(stock) as total_stock')
                ->groupBy('unit')
                ->orderBy('unit')
                ->pluck('total_stock', 'unit'),
            'lowStockCount' => (clone $activeProducts)
                ->where('min_stock', '>', 0)
                ->whereColumn('stock', '<=', 'min_stock')
                ->count(),
            'todayMovements' => StockMovement::query()->whereDate('created_at', today())->count(),
            'restockProducts' => (clone $activeProducts)
                ->with('location:id,code')
                ->where('min_stock', '>', 0)
                ->whereColumn('stock', '<=', 'min_stock')
                ->orderByRaw('(min_stock - stock) DESC')
                ->orderBy('name')
                ->limit(5)
                ->get(),
            'recentMovements' => StockMovement::query()
                ->with('product:id,name')
                ->latest('id')
                ->limit(5)
                ->get(),
            'locationSummaries' => Location::query()
                ->whereNull('archived_at')
                ->withCount(['products as active_products_count' => fn ($query) => $query->whereNull('archived_at')])
                ->orderByDesc('active_products_count')
                ->orderBy('code')
                ->limit(5)
                ->get(),
        ];
    }
};
?>

<div class="space-y-4">
    <section class="grid grid-cols-2 gap-3">
        <div class="rounded-xl bg-white p-3 shadow-sm">
            <p class="text-xs font-medium text-slate-500">Produk aktif</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ $totalProducts }}</p>
        </div>
        <div class="rounded-xl bg-white p-3 shadow-sm">
            <p class="text-xs font-medium text-slate-500">Stok per satuan</p>
            <div class="mt-1 flex flex-wrap gap-x-2 gap-y-1">
                @forelse ($stockByUnit as $unit => $total)
                    <span wire:key="stock-unit-{{ $unit }}" class="text-sm font-bold tabular-nums text-slate-900">{{ $total }} {{ $unit }}</span>
                @empty
                    <span class="text-sm text-slate-400">Belum ada stok</span>
                @endforelse
            </div>
        </div>
        <div class="rounded-xl bg-white p-3 shadow-sm">
            <p class="text-xs font-medium text-slate-500">Stok menipis</p>
            <p class="mt-1 text-2xl font-bold tabular-nums {{ $lowStockCount > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $lowStockCount }}</p>
        </div>
        <div class="rounded-xl bg-white p-3 shadow-sm">
            <p class="text-xs font-medium text-slate-500">Mutasi hari ini</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ $todayMovements }}</p>
        </div>
    </section>

    <a href="{{ route('scan') }}" class="block rounded-xl bg-slate-900 px-4 py-3 text-center text-sm font-semibold text-white active:bg-slate-700">
        Pindai atau Input Barcode
    </a>

    <section class="space-y-2">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900">Lokasi Gudang</h2>
            <a href="{{ route('locations') }}" class="text-xs font-medium text-slate-500">Kelola lokasi</a>
        </div>

        <div class="grid grid-cols-2 gap-2">
            @forelse ($locationSummaries as $location)
                <div wire:key="dashboard-location-{{ $location->id }}" class="rounded-xl bg-white p-3 shadow-sm">
                    <p class="text-sm font-semibold text-slate-900">{{ $location->code }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $location->name }}</p>
                    <p class="mt-1 text-xs font-medium text-slate-600">{{ $location->active_products_count }} produk</p>
                </div>
            @empty
                <p class="col-span-2 rounded-xl bg-white p-4 text-center text-sm text-slate-500">Belum ada lokasi.</p>
            @endforelse
        </div>
    </section>

    <section class="space-y-2">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900">Prioritas Restock</h2>
            <a href="{{ route('products', ['lowOnly' => true]) }}" class="text-xs font-medium text-slate-500">Lihat semua</a>
        </div>

        @forelse ($restockProducts as $product)
            <a href="{{ route('products.show', $product) }}" wire:key="restock-{{ $product->id }}"
               class="flex items-center justify-between gap-3 rounded-xl bg-white p-3 shadow-sm active:bg-slate-50">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-slate-900">{{ $product->name }}</p>
                    <p class="text-xs text-slate-500">{{ $product->location?->code ?? 'Tanpa lokasi' }} &middot; minimum {{ $product->min_stock }} {{ $product->unit }}</p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-lg font-bold tabular-nums text-red-600">{{ $product->stock }}</p>
                    <p class="text-xs text-slate-500">{{ $product->unit }}</p>
                </div>
            </a>
        @empty
            <p class="rounded-xl bg-emerald-50 p-4 text-center text-sm text-emerald-700">Semua stok berada di atas batas minimum.</p>
        @endforelse
    </section>

    <section class="space-y-2">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900">Aktivitas Terbaru</h2>
            <a href="{{ route('history') }}" class="text-xs font-medium text-slate-500">Lihat riwayat</a>
        </div>

        @forelse ($recentMovements as $movement)
            <div wire:key="recent-{{ $movement->id }}" class="flex items-center justify-between gap-3 rounded-xl bg-white p-3 shadow-sm">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-slate-900">{{ $movement->product?->name ?? 'Produk dihapus' }}</p>
                    <p class="text-xs text-slate-500">{{ $movement->created_at->format('d M H:i') }}</p>
                </div>
                <p @class([
                    'shrink-0 text-sm font-semibold tabular-nums',
                    'text-emerald-600' => $movement->quantity > 0,
                    'text-red-600' => $movement->quantity < 0,
                    'text-slate-600' => $movement->quantity === 0,
                ])>{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</p>
            </div>
        @empty
            <p class="rounded-xl bg-white p-4 text-center text-sm text-slate-500">Belum ada aktivitas stok.</p>
        @endforelse
    </section>
</div>