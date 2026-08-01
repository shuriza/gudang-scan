<?php

use App\Models\Product;
use App\Models\Location;
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

    #[Url(except: false)]
    public bool $showArchived = false;

    public bool $showForm = false;

    public ?int $editingProductId = null;

    public string $barcode = '';

    public string $name = '';

    public string $unit = 'pcs';

    public ?int $locationId = null;

    #[Url(except: '')]
    public string $locationFilter = '';

    public int $stock = 0;

    public int $minStock = 0;

    public ?string $success = null;

    public function mount(): void
    {
        $productId = request()->integer('edit');

        if ($productId > 0) {
            $this->editProduct($productId);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLowOnly(): void
    {
        $this->resetPage();
    }

    public function updatedShowArchived(): void
    {
        $this->lowOnly = false;
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
        $this->locationId = $product->location_id;
        $this->stock = $product->stock;
        $this->minStock = $product->min_stock;
        $this->showForm = true;
    }

    public function saveProduct(StockService $stockService): void
    {
        $this->barcode = trim($this->barcode);
        $this->name = trim($this->name);
        $this->unit = trim($this->unit);

        $validated = $this->validate([
            'barcode' => [
                'required',
                'string',
                'max:64',
                Rule::unique('products', 'barcode')->ignore($this->editingProductId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:16'],
            'locationId' => ['nullable', 'integer', Rule::exists('locations', 'id')->whereNull('archived_at')],
            'stock' => [Rule::requiredIf($this->editingProductId === null), 'integer', 'min:0', 'max:1000000'],
            'minStock' => ['required', 'integer', 'min:0', 'max:1000000'],
        ], attributes: [
            'barcode' => 'barcode',
            'name' => 'nama produk',
            'unit' => 'satuan',
            'locationId' => 'lokasi',
            'stock' => 'stok awal',
            'minStock' => 'batas stok menipis',
        ]);

        $saved = DB::transaction(function () use ($validated, $stockService): bool {
            if ($validated['locationId'] !== null) {
                Location::query()
                    ->whereNull('archived_at')
                    ->lockForUpdate()
                    ->findOrFail($validated['locationId']);
            }

            $product = $this->editingProductId === null
                ? new Product
                : Product::lockForUpdate()->findOrFail($this->editingProductId);

            if ($product->isArchived()) {
                $this->addError('archive', 'Aktifkan kembali produk sebelum mengubah detailnya.');

                return false;
            }

            $product->fill([
                'barcode' => $validated['barcode'],
                'name' => $validated['name'],
                'unit' => $validated['unit'],
                'location_id' => $validated['locationId'],
                'min_stock' => $validated['minStock'],
            ]);
            $product->save();

            if (! $product->wasRecentlyCreated || $validated['stock'] === 0) {
                return true;
            }

            $stockService->apply(
                $product,
                StockMovement::TYPE_IN,
                $validated['stock'],
                'Stok awal produk',
            );

            return true;
        });

        if (! $saved) {
            return;
        }

        $message = $this->editingProductId === null ? 'Produk berhasil ditambahkan.' : 'Produk berhasil diperbarui.';

        $this->resetProductForm();
        $this->success = $message;
    }

    public function cancelProductForm(): void
    {
        $this->resetProductForm();
    }

    public function archiveProduct(): void
    {
        $archived = DB::transaction(function (): bool {
            $product = Product::lockForUpdate()->findOrFail($this->editingProductId);

            if ($product->stock > 0) {
                $this->addError('archive', "Stok {$product->name} masih {$product->stock} {$product->unit}. Koreksi stok ke 0 sebelum mengarsipkan.");

                return false;
            }

            $product->forceFill(['archived_at' => now()])->save();

            return true;
        });

        if (! $archived) {
            return;
        }

        $this->resetProductForm();
        $this->success = 'Produk berhasil diarsipkan.';
    }

    public function restoreProduct(int $productId): void
    {
        $restored = Product::query()
            ->whereKey($productId)
            ->whereNotNull('archived_at')
            ->update(['archived_at' => null]);

        if ($restored === 0) {
            return;
        }

        $this->resetProductForm();
        $this->success = 'Produk berhasil diaktifkan kembali.';
    }

    protected function resetProductForm(): void
    {
        $this->resetValidation();
        $this->reset('showForm', 'editingProductId', 'barcode', 'name', 'locationId', 'stock', 'minStock', 'success');
        $this->unit = 'pcs';
    }

    public function with(): array
    {
        return [
            'products' => Product::query()
                ->with('location:id,code')
                ->when($this->showArchived, fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
                ->when($this->locationFilter !== '', fn ($query) => $query->where('location_id', $this->locationFilter))
                ->when($this->search !== '', function ($query) {
                    $term = '%'.$this->search.'%';
                    $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('barcode', 'like', $term));
                })
                ->when($this->lowOnly, fn ($q) => $q->whereColumn('stock', '<=', 'min_stock')->where('min_stock', '>', 0))
                ->orderBy('name')
                ->paginate(20),
            'locations' => Location::query()->whereNull('archived_at')->orderBy('code')->get(['id', 'code', 'name']),
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
                    <select wire:model="locationId" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-slate-900 focus:outline-none">
                        <option value="">Tanpa lokasi</option>
                        @foreach ($locations as $location)
                            <option wire:key="location-option-{{ $location->id }}" value="{{ $location->id }}">{{ $location->code }} — {{ $location->name }}</option>
                        @endforeach
                    </select>
                    @error('locationId') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
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

            @if ($editingProductId !== null)
                <button type="button" wire:click="archiveProduct"
                        class="w-full rounded-lg bg-red-50 py-2.5 text-sm font-semibold text-red-700 active:bg-red-100">
                    Arsipkan Produk
                </button>
                @error('archive') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            @endif
        </form>
    @endif

    <div class="flex flex-wrap gap-x-4 gap-y-2">
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" wire:model.live="lowOnly" class="size-4 rounded border-slate-300">
            Hanya stok menipis
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" wire:model.live="showArchived" class="size-4 rounded border-slate-300">
            Produk diarsipkan
        </label>
    </div>

    <select wire:model.live="locationFilter" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-slate-900 focus:outline-none">
        <option value="">Semua lokasi</option>
        @foreach ($locations as $location)
            <option wire:key="location-filter-{{ $location->id }}" value="{{ $location->id }}">{{ $location->code }} — {{ $location->name }}</option>
        @endforeach
    </select>

    <div class="space-y-2">
        @forelse ($products as $product)
            <div wire:key="product-{{ $product->id }}" class="flex items-center gap-2 rounded-xl bg-white p-3 shadow-sm">
                <a href="{{ route('products.show', $product) }}"
                   class="flex min-w-0 flex-1 items-center justify-between gap-3 text-left active:opacity-70 {{ $product->isArchived() ? 'opacity-60' : '' }}">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-900">{{ $product->name }}</p>
                        <p class="font-mono text-xs text-slate-500">{{ $product->barcode }}</p>
                        <p class="text-xs text-slate-400">Lokasi {{ $product->location?->code ?? '-' }} &middot; min {{ $product->min_stock }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-xl font-bold tabular-nums {{ $product->isLowStock() ? 'text-red-600' : 'text-slate-900' }}">
                            {{ $product->stock }}
                        </p>
                        <p class="text-xs text-slate-500">{{ $product->unit }}</p>
                    </div>
                </a>
                @if ($product->isArchived())
                    <button type="button" wire:click="restoreProduct({{ $product->id }})"
                            class="shrink-0 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 active:bg-emerald-100">
                        Aktifkan
                    </button>
                @endif
            </div>
        @empty
            <p class="rounded-xl bg-white p-6 text-center text-sm text-slate-500">Tidak ada produk cocok.</p>
        @endforelse
    </div>

    {{ $products->links() }}
</div>
