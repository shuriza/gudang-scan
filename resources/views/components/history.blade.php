<?php

use App\Models\StockMovement;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public function with(): array
    {
        return [
            'movements' => StockMovement::with('product')->latest('id')->paginate(25),
        ];
    }
};
?>

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
        <p class="rounded-xl bg-white p-6 text-center text-sm text-slate-500">Belum ada mutasi stok.</p>
    @endforelse

    {{ $movements->links() }}
</div>
