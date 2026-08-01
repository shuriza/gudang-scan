<?php

use App\Models\Product;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Locked]
    public int $productId;

    public function mount(Product $product): void
{
        $this->productId = $product->id;
    }

    public function with(): array
{
        $product = Product::with('location')->findOrFail($this->productId);

        return [
            'product' => $product,
            'movements' => $product->movements()->latest('id')->paginate(20),
        ];
    }
};
?>

<div class="space-y-4">
    <section class="rounded-xl bg-white p-4 shadow-sm">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="truncate text-lg font-semibold text-slate-900">{{ $product->name }}</h2>
                    @if ($product->isArchived())
                        <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600">Diarsipkan</span>
                    @elseif ($product->isLowStock())
                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Stok menipis</span>
                    @else
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Stok aman</span>
                    @endif
</div>
                <p class="font-mono text-xs text-slate-500">{{ $product->barcode }}</p>
                <p class="mt-2 text-sm text-slate-600">Lokasi {{ $product->location?->code ?? '-' }}</p>
                <p class="text-xs text-slate-400">Batas minimum {{ $product->min_stock }} {{ $product->unit }}</p>
</div>
            <div class="shrink-0 text-right">
                <p class="text-3xl font-bold tabular-nums {{ $product->isLowStock() ? 'text-red-600' : 'text-slate-900' }}">{{ $product->stock }}</p>
                <p class="text-xs text-slate-500">{{ $product->unit }}</p>
</div>
</div>

        @unless ($product->isArchived())
            <div class="mt-4 grid grid-cols-3 gap-2">
                <a href="{{ route('scan', ['barcode' => $product->barcode, 'type' => 'in']) }}"
                   class="rounded-lg bg-emerald-600 px-2 py-2.5 text-center text-sm font-semibold text-white active:bg-emerald-700">
                    Masuk
                </a>
                <a href="{{ route('scan', ['barcode' => $product->barcode, 'type' => 'out']) }}"
                   class="rounded-lg bg-red-600 px-2 py-2.5 text-center text-sm font-semibold text-white active:bg-red-700">
                    Keluar
                </a>
                <a href="{{ route('scan', ['barcode' => $product->barcode, 'type' => 'adjust']) }}"
                   class="rounded-lg bg-slate-700 px-2 py-2.5 text-center text-sm font-semibold text-white active:bg-slate-800">
                    Koreksi
                </a>
</div>
            <div class="mt-2">
                <a href="{{ route('products', ['edit' => $product->id]) }}"
                   class="block rounded-lg bg-slate-100 px-3 py-2.5 text-center text-sm font-semibold text-slate-700 active:bg-slate-200">
                    Edit Master
                </a>
</div>
        @endunless
    </section>

    <section class="space-y-2">
        <h2 class="text-sm font-semibold text-slate-900">Riwayat Produk</h2>

        @forelse ($movements as $movement)
            <div wire:key="product-movement-{{ $movement->id }}" class="rounded-xl bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-900">{{ match ($movement->type) {
                            'in' => 'Masuk',
                            'out' => 'Keluar',
                            default => 'Koreksi',
                        } }}</p>
                        <p class="text-xs text-slate-500">{{ $movement->created_at->format('d M Y H:i') }}</p>
                        @if ($movement->note)
                            <p class="mt-1 text-xs text-slate-400">{{ $movement->note }}</p>
                        @endif
</div>
                    <div class="shrink-0 text-right">
                        <p @class([
                            'text-sm font-semibold tabular-nums',
                            'text-emerald-600' => $movement->quantity > 0,
                            'text-red-600' => $movement->quantity < 0,
                            'text-slate-600' => $movement->quantity === 0,
                        ])>{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</p>
                        <p class="text-xs text-slate-400 tabular-nums">{{ $movement->stock_before }} → {{ $movement->stock_after }}</p>
</div>
</div>
</div>
        @empty
            <p class="rounded-xl bg-white p-5 text-center text-sm text-slate-500">Belum ada mutasi untuk produk ini.</p>
        @endforelse

        {{ $movements->links() }}
    </section>
</div>