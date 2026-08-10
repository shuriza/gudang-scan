<?php

use App\Exceptions\StockException;
use App\Models\Product;
use App\Models\StockOpname;
use App\Services\StockOpnameService;
use App\Services\StockService;
use Livewire\Component;

new class extends Component
{
    public string $number = '';
    public array $counts = [];
    public ?string $message = null;

    public function startSession(): void
    {
        $validated = $this->validate([
            'number' => ['required', 'string', 'max:64', 'unique:stock_opnames,number'],
        ]);

        $opname = StockOpname::create([
            'number' => trim($validated['number']),
            'status' => StockOpname::STATUS_DRAFT,
        ]);

        Product::query()->whereNull('archived_at')->orderBy('name')->each(function (Product $product) use ($opname): void {
            $opname->items()->create([
                'product_id' => $product->id,
                'system_stock' => $product->stock,
            ]);
        });

        $this->reset('number');
        $this->message = 'Sesi opname dibuat. Isi stok fisik, lalu finalisasi.';
    }

    public function saveCounts(int $opnameId): void
    {
        $opname = StockOpname::with('items')->findOrFail($opnameId);

        foreach ($opname->items as $item) {
            $counted = $this->counts[$item->id] ?? null;

            if ($counted === null || $counted === '') {
                continue;
            }

            $item->forceFill(['counted_stock' => (int) $counted])->save();
        }

        $this->message = 'Hitungan fisik disimpan.';
    }

    public function finalize(int $opnameId, StockOpnameService $opnames, StockService $stock): void
    {
        $opname = StockOpname::findOrFail($opnameId);
        $this->saveCounts($opnameId);

        try {
            $opnames->finalize($opname, $stock);
        } catch (StockException $exception) {
            $this->addError('opname', $exception->getMessage());

            return;
        }

        $this->message = 'Opname berhasil difinalisasi.';
    }

    public function with(): array
    {
        return [
            'opnames' => StockOpname::query()->with(['items.product'])->latest('id')->get(),
        ];
    }
};
?>

<div class="space-y-4">
    <form wire:submit="startSession" class="space-y-2 rounded-xl bg-white p-4 shadow-sm">
        <input wire:model="number" placeholder="Nomor opname" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        @error('number') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        <button type="submit" class="w-full rounded-lg bg-slate-900 py-3 text-sm font-semibold text-white">Buat Sesi Opname</button>
    </form>

    @if ($message)
        <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ $message }}</p>
    @endif
    @error('opname') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

    @foreach ($opnames as $opname)
        <section wire:key="opname-{{ $opname->id }}" class="space-y-2 rounded-xl bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <p class="font-semibold">{{ $opname->number }}</p>
                <p class="text-xs uppercase text-slate-500">{{ $opname->status }}</p>
            </div>
            @foreach ($opname->items as $item)
                <label wire:key="opname-item-{{ $item->id }}" class="flex items-center gap-2">
                    <span class="min-w-0 flex-1 truncate text-sm">{{ $item->product?->name }} (sistem {{ $item->system_stock }})</span>
                    <input type="number" min="0" wire:model="counts.{{ $item->id }}" @disabled($opname->status !== 'draft') class="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                </label>
            @endforeach
            @if ($opname->status === 'draft')
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" wire:click="saveCounts({{ $opname->id }})" class="rounded-lg bg-slate-100 py-2 text-sm font-medium">Simpan Draft</button>
                    <button type="button" wire:click="finalize({{ $opname->id }})" class="rounded-lg bg-emerald-600 py-2 text-sm font-semibold text-white">Finalisasi</button>
                </div>
            @endif
        </section>
    @endforeach
</div>
