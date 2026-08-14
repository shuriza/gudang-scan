<?php

use App\Models\StockAlert;
use Livewire\Component;

new class extends Component
{
    public function with(): array
    {
        return [
            'alerts' => StockAlert::query()
                ->with('product')
                ->where('status', StockAlert::STATUS_OPEN)
                ->latest('id')
                ->get(),
        ];
    }
};
?>

<div class="space-y-3">
    <p class="text-sm text-slate-600">Alert terbuka hanya dibuat sekali per produk sampai stok pulih.</p>

    @forelse ($alerts as $alert)
        <a href="{{ route('products.show', $alert->product) }}" wire:key="alert-{{ $alert->id }}" class="block rounded-xl bg-white p-3 shadow-sm">
            <p class="font-medium text-slate-900">{{ $alert->product?->name }}</p>
            <p class="text-xs text-slate-500">Stok saat alert: {{ $alert->stock_at_alert }} {{ $alert->product?->unit }}</p>
        </a>
    @empty
        <p class="rounded-xl bg-emerald-50 p-4 text-center text-sm text-emerald-700">Tidak ada stok menipis yang perlu ditindak.</p>
    @endforelse
</div>
