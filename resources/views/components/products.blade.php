<?php

use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

    public bool $showForm = false;

    public ?int $editingProductId = null;

    public string $barcode = '';

    public string $name = '';

    public string $unit = 'pcs';

    public string $location = '';

    public int $stock = 0;

    public int $minStock = 0;

    public ?string $success = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLowOnly(): void
    {
        $this->resetPage();
    }

    public function createProduct(): void
    {
        $this->resetProductForm();
        $this->showForm = true;
    }

    public function editProduct(int $productId): void
    {
        $product = Product::findOrFail($productId);

        $this->resetValidation();
        $this->success = null;
        $this->editingProductId = $product->id;
        $this->barcode = $product->barcode;
        $this->name = $product->name;
        $this->unit = $product->unit;
        $this->location = $product->location ?? '';
        $this->stock = $product->stock;
        $this->minStock = $product->min_stock;
        $this->showForm = true;
    }

    public function saveProduct(StockService $stockService): void
    {
        $this->barcode = trim($this->barcode);
        $this->name = trim($this->name);
        $this->unit = trim($this->unit);
        $this->location = trim($this->location);

        $validated = $this->validate([
            'barcode' => [
                'required',
                'string',
                'max:64',
                Rule::unique('products', 'barcode')->ignore($this->editingProductId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:16'],
            'location' => ['nullable', 'string', 'max:32'],
            'stock' => [Rule::requiredIf($this->editingProductId === null), 'integer', 'min:0', 'max:1000000'],
            'minStock' => ['required', 'integer', 'min:0', 'max:1000000'],
        ], attributes: [
            'barcode' => 'barcode',
            'name' => 'nama produk',
            'unit' => 'satuan',
            'location' => 'lokasi',
            'stock' => 'stok awal',
            'minStock' => 'batas stok menipis',
        ]);

        DB::transaction(function () use ($validated, $stockService): void {
            $product = $this->editingProductId === null
                ? new Product
                : Product::findOrFail($this->editingProductId);

            $product->fill([
                'barcode' => $validated['barcode'],
                'name' => $validated['name'],
                'unit' => $validated['unit'],
                'location' => filled($validated['location']) ? $validated['location'] : null,
                'min_stock' => $validated['minStock'],
            ]);
            $product->save();

            if (! $product->wasRecentlyCreated || $validated['stock'] === 0) {
                return;
            }

            $stockService->apply(
                $product,
                StockMovement::TYPE_IN,
                $validated['stock'],
                'Stok awal produk',
            );
        });

        $message = $this->editingProductId === null ? 'Produk berhasil ditambahkan.' : 'Produk berhasil diperbarui.';

        $this->resetProductForm();
        $this->success = $message;
    }

    public function cancelProductForm(): void
    {
        $this->resetProductForm();
    }

    protected function resetProductForm(): void
    {
        $this->resetValidation();
        $this->reset('showForm', 'editingProductId', 'barcode', 'name', 'location', 'stock', 'minStock', 'success');
        $this->unit = 'pcs';
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
    <div class="flex gap-2">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau barcode"
               class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-slate-900 focus:outline-none">
        <button type="button" wire:click="createProduct"
                class="shrink-0 rounded-lg bg-slate-900 px-3 text-sm font-semibold text-white active:bg-slate-700">
            Tambah
        </button>
    </div>

    @if ($success)
        <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ $success }}</p>
    @endif

    @if ($showForm)
        <form wire:submit="saveProduct" class="space-y-3 rounded-xl bg-white p-4 shadow-sm">
            <div>
                <p class="font-semibold text-slate-900">{{ $editingProductId === null ? 'Tambah Produk' : 'Edit Produk' }}</p>
                <p class="text-xs text-slate-500">Stok produk lama diubah melalui menu Scan agar riwayat tetap tercatat.</p>
            </div>

            <label class="block">
                <span class="text-xs font-medium text-slate-600">Barcode</span>
                <input type="text" wire:model="barcode" inputmode="numeric" autocomplete="off"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-900 focus:outline-none">
                @error('barcode') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-xs font-medium text-slate-600">Nama produk</span>
                <input type="text" wire:model="name" autocomplete="off"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-900 focus:outline-none">
                @error('name') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <div class="grid grid-cols-2 gap-2">
                <label class="block">
                    <span class="text-xs font-medium text-slate-600">Satuan</span>
                    <input type="text" wire:model="unit" autocomplete="off"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-900 focus:outline-none">
                    @error('unit') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="text-xs font-medium text-slate-600">Lokasi</span>
                    <input type="text" wire:model="location" autocomplete="off"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-900 focus:outline-none">
                    @error('location') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="grid grid-cols-2 gap-2">
                @if ($editingProductId === null)
                    <label class="block">
                        <span class="text-xs font-medium text-slate-600">Stok awal</span>
                        <input type="number" wire:model="stock" min="0" inputmode="numeric"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm tabular-nums focus:border-slate-900 focus:outline-none">
                        @error('stock') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                @else
                    <div>
                        <span class="text-xs font-medium text-slate-600">Stok saat ini</span>
                        <p class="mt-1 rounded-lg bg-slate-100 px-3 py-2.5 text-sm font-semibold tabular-nums">{{ $stock }} {{ $unit }}</p>
                    </div>
                @endif

                <label class="block">
                    <span class="text-xs font-medium text-slate-600">Batas menipis</span>
                    <input type="number" wire:model="minStock" min="0" inputmode="numeric"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm tabular-nums focus:border-slate-900 focus:outline-none">
                    @error('minStock') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-lg bg-emerald-600 py-3 text-sm font-semibold text-white active:bg-emerald-700">
                    Simpan Produk
                </button>
                <button type="button" wire:click="cancelProductForm"
                        class="rounded-lg bg-slate-100 px-4 text-sm font-medium text-slate-600 active:bg-slate-200">
                    Batal
                </button>
            </div>
        </form>
    @endif

    <label class="flex items-center gap-2 text-sm text-slate-600">
        <input type="checkbox" wire:model.live="lowOnly" class="size-4 rounded border-slate-300">
        Hanya stok menipis
    </label>

    <div class="space-y-2">
        @forelse ($products as $product)
            <button type="button" wire:key="product-{{ $product->id }}" wire:click="editProduct({{ $product->id }})"
                    class="flex w-full items-center justify-between gap-3 rounded-xl bg-white p-3 text-left shadow-sm active:bg-slate-50">
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
            </button>
        @empty
            <p class="rounded-xl bg-white p-6 text-center text-sm text-slate-500">Tidak ada produk cocok.</p>
        @endforelse
    </div>

    {{ $products->links() }}
</div>
