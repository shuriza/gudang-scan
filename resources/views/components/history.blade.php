<?php

use App\Models\StockMovement;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $dateFrom = '';

    #[Url(except: '')]
    public string $dateTo = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'type', 'dateFrom', 'dateTo');
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'movements' => StockMovement::query()
                ->with('product')
                ->when($this->search !== '', function ($query): void {
                    $term = '%'.$this->search.'%';
                    $query->where(function ($query) use ($term): void {
                        $query->where('note', 'like', $term)
                            ->orWhereHas('product', fn ($productQuery) => $productQuery
                                ->where('name', 'like', $term)
                                ->orWhere('barcode', 'like', $term));
                    });
                })
                ->when(in_array($this->type, [StockMovement::TYPE_IN, StockMovement::TYPE_OUT, StockMovement::TYPE_ADJUST], true), fn ($query) => $query->where('type', $this->type))
                ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $this->dateFrom))
                ->when($this->dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $this->dateTo))
                ->latest('id')
                ->paginate(25),
        ];
    }
};
?>

<div class="space-y-3">
    <div class="space-y-2 rounded-xl bg-white p-3 shadow-sm">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari produk, barcode, atau catatan"
               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-900 focus:outline-none">

        <div class="grid grid-cols-3 gap-2">
            <button type="button" wire:click="$set('type', '{{ StockMovement::TYPE_IN }}')"
                    @class(['rounded-lg py-2 text-xs font-medium', 'bg-emerald-600 text-white' => $type === StockMovement::TYPE_IN, 'bg-slate-100 text-slate-600' => $type !== StockMovement::TYPE_IN])>
                Masuk
            </button>
            <button type="button" wire:click="$set('type', '{{ StockMovement::TYPE_OUT }}')"
                    @class(['rounded-lg py-2 text-xs font-medium', 'bg-red-600 text-white' => $type === StockMovement::TYPE_OUT, 'bg-slate-100 text-slate-600' => $type !== StockMovement::TYPE_OUT])>
                Keluar
            </button>
            <button type="button" wire:click="$set('type', '{{ StockMovement::TYPE_ADJUST }}')"
                    @class(['rounded-lg py-2 text-xs font-medium', 'bg-slate-700 text-white' => $type === StockMovement::TYPE_ADJUST, 'bg-slate-100 text-slate-600' => $type !== StockMovement::TYPE_ADJUST])>
                Koreksi
            </button>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <label>
                <span class="text-xs font-medium text-slate-500">Dari tanggal</span>
                <input type="date" wire:model.live="dateFrom"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 text-xs focus:border-slate-900 focus:outline-none">
            </label>
            <label>
                <span class="text-xs font-medium text-slate-500">Sampai tanggal</span>
                <input type="date" wire:model.live="dateTo"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 text-xs focus:border-slate-900 focus:outline-none">
            </label>
        </div>

        @if ($search !== '' || $type !== '' || $dateFrom !== '' || $dateTo !== '')
            <button type="button" wire:click="clearFilters" class="text-xs font-medium text-slate-600 underline underline-offset-2">
                Hapus filter
            </button>
        @endif
    </div>

    <div class="space-y-2">
    @forelse ($movements as $movement)
        @php
            $label = match ($movement->type) {
                'in' => 'Masuk',
                'out' => 'Keluar',
                default => 'Koreksi',
            };
        @endphp
        <div wire:key="movement-{{ $movement->id }}" class="rounded-xl bg-white p-3 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-slate-900">{{ $movement->product?->name ?? 'Produk dihapus' }}</p>
                    <p class="text-xs text-slate-500">{{ $movement->created_at->format('d M Y H:i') }}</p>
                    @if ($movement->note)
                        <p class="mt-1 truncate text-xs text-slate-400">{{ $movement->note }}</p>
                    @endif
                </div>
                <div class="shrink-0 text-right">
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-emerald-100 text-emerald-700' => $movement->type === 'in',
                        'bg-red-100 text-red-700' => $movement->type === 'out',
                        'bg-slate-100 text-slate-600' => $movement->type === 'adjust',
                    ])>{{ $label }}</span>
                    <p class="mt-1 text-sm font-semibold tabular-nums">
                        {{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}
                    </p>
                    <p class="text-xs text-slate-400 tabular-nums">{{ $movement->stock_before }} → {{ $movement->stock_after }}</p>
                </div>
            </div>
        </div>
    @empty
        <p class="rounded-xl bg-white p-6 text-center text-sm text-slate-500">Tidak ada mutasi yang cocok.</p>
    @endforelse
    </div>

    {{ $movements->links() }}
</div>
