<?php

use App\Models\Product;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: false)]
    public bool $lowOnly = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLowOnly(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'products' => Product::query()
                ->when($this->search !== '', function ($query) {
                    $term = '%'.$this->search.'%';
                    $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('barcode', 'like', $term));
                })
                ->when($this->lowOnly, fn ($q) => $q->whereColumn('stock', '<=', 'min_stock')->where('min_stock', '>', 0))
                ->orderBy('name')
                ->paginate(20),
        ];
    }
};
?>

<div class="space-y-3">
    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau barcode"
           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-slate-900 focus:outline-none">

    <label class="flex items-center gap-2 text-sm text-slate-600">
        <input type="checkbox" wire:model.live="lowOnly" class="size-4 rounded border-slate-300">
        Hanya stok menipis
    </label>

    <div class="space-y-2">
        @forelse ($products as $product)
            <div wire:key="product-{{ $product->id }}" class="flex items-center justify-between gap-3 rounded-xl bg-white p-3 shadow-sm">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-slate-900">{{ $product->name }}</p>
                    <p class="font-mono text-xs text-slate-500">{{ $product->barcode }}</p>
                    <p class="text-xs text-slate-400">Lokasi {{ $product->location ?? '-' }} &middot; min {{ $product->min_stock }}</p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-xl font-bold tabular-nums {{ $product->isLowStock() ? 'text-red-600' : 'text-slate-900' }}">
                        {{ $product->stock }}
                    </p>
                    <p class="text-xs text-slate-500">{{ $product->unit }}</p>
                </div>
            </div>
        @empty
            <p class="rounded-xl bg-white p-6 text-center text-sm text-slate-500">Tidak ada produk cocok.</p>
        @endforelse
    </div>

    {{ $products->links() }}
</div>
