<?php

use App\Exceptions\StockException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Sandip\Scanner\Native\Attributes\OnNative;
use Sandip\Scanner\Native\Events\Scanner\Cancelled;
use Sandip\Scanner\Native\Events\Scanner\CodeScanned;
use Sandip\Scanner\Native\Facades\Scanner;

new class extends Component
{
    public ?int $productId = null;

    public string $barcode = '';

    public string $manualBarcode = '';

    public string $type = StockMovement::TYPE_IN;

    public int $quantity = 1;

    public string $note = '';

    public ?string $error = null;

    public ?string $success = null;

    /** Barcode yang terbaca tapi belum terdaftar di master produk. */
    public ?string $unknownBarcode = null;

    public function mount(): void
    {
        $barcode = request()->string('barcode')->trim()->toString();
        $type = request()->string('type')->toString();

        if (in_array($type, [StockMovement::TYPE_IN, StockMovement::TYPE_OUT, StockMovement::TYPE_ADJUST], true)) {
            $this->type = $type;
        }

        if ($barcode !== '') {
            $this->resolveBarcode($barcode);
        }
    }

    #[Computed]
    public function product(): ?Product
    {
        return $this->productId ? Product::find($this->productId) : null;
    }

    /**
     * Scanner native hanya tersedia di dalam runtime NativePHP.
     *
     * Di perangkat, nativephp_call() berasal dari ekstensi C sehingga berstatus
     * internal. Di mesin dev, NativeServiceProvider mendefinisikan shim userland
     * bernama sama (Jump mode) — jadi function_exists() saja selalu true dan
     * tidak bisa membedakan keduanya.
     */
    #[Computed]
    public function nativeAvailable(): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        return (new ReflectionFunction('nativephp_call'))->isInternal();
    }

    public function startScan(): void
    {
        $this->reset('error', 'success', 'unknownBarcode');

        if (! $this->nativeAvailable()) {
            $this->error = 'Scanner kamera hanya tersedia saat aplikasi berjalan di perangkat. Gunakan input manual.';

            return;
        }

        Scanner::scan()
            ->prompt('Arahkan kamera ke barcode produk')
            ->formats(['ean13', 'ean8', 'code128', 'code39', 'upca', 'upce', 'qr'])
            ->continuous(false)
            ->id('inventory-scanner')
            ->scan();
    }

    #[OnNative(CodeScanned::class)]
    public function handleScan(string $data, string $format, ?string $id = null): void
    {
        if ($id !== null && $id !== 'inventory-scanner') {
            return;
        }

        $this->resolveBarcode($data);
    }

    #[OnNative(Cancelled::class)]
    public function handleCancelled(?string $reason = null, ?string $id = null): void
    {
        if ($id !== null && $id !== 'inventory-scanner') {
            return;
        }

        if ($reason !== null && $reason !== 'stopped_by_app') {
            $this->error = 'Pemindaian dibatalkan.';
        }
    }

    public function submitManual(): void
    {
        $this->validate([
            'manualBarcode' => ['required', 'string', 'max:64'],
        ], attributes: ['manualBarcode' => 'barcode']);

        $this->resolveBarcode($this->manualBarcode);
        $this->manualBarcode = '';
    }

    /** Cari produk untuk barcode dan siapkan form mutasi. */
    protected function resolveBarcode(string $data): void
    {
        $this->reset('error', 'success', 'unknownBarcode');

        $barcode = trim($data);
        $product = Product::with('location')->where('barcode', $barcode)->first();

        if (! $product) {
            $this->productId = null;
            $this->barcode = $barcode;
            $this->unknownBarcode = $barcode;

            return;
        }

        if ($product->isArchived()) {
            $this->productId = null;
            $this->barcode = $barcode;
            $this->error = "Produk {$product->name} sedang diarsipkan. Aktifkan kembali dari menu Produk.";

            return;
        }

        $this->productId = $product->id;
        $this->barcode = $product->barcode;
        $this->quantity = 1;
        $this->note = '';
    }

    public function save(StockService $stock): void
    {
        $this->reset('error', 'success');

        $product = $this->product();

        if (! $product) {
            $this->error = 'Pindai atau masukkan barcode produk terlebih dahulu.';

            return;
        }

        $this->validate([
            'type' => ['required', 'in:in,out,adjust'],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'quantity' => 'jumlah',
            'note' => 'catatan',
        ]);

        try {
            $movement = $stock->apply($product, $this->type, $this->quantity, $this->note ?: null);
        } catch (StockException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->success = "{$product->name}: {$movement->stock_before} → {$movement->stock_after} {$product->unit}.";
        $this->quantity = 1;
        $this->note = '';
    }

    public function clear(): void
    {
        $this->reset('productId', 'barcode', 'unknownBarcode', 'error', 'success', 'note');
        $this->quantity = 1;
    }
};
?>

<div class="space-y-4" wire:key="scan-page">
    <button type="button" wire:click="startScan"
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-4 text-base font-semibold text-white active:bg-slate-700">
        <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875A1.125 1.125 0 0 1 4.875 3.75h4.5A1.125 1.125 0 0 1 10.5 4.875v4.5A1.125 1.125 0 0 1 9.375 10.5h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625A1.125 1.125 0 0 1 4.875 13.5h4.5A1.125 1.125 0 0 1 10.5 14.625v4.5A1.125 1.125 0 0 1 9.375 20.25h-4.5A1.125 1.125 0 0 1 3.75 19.125v-4.5ZM13.5 4.875A1.125 1.125 0 0 1 14.625 3.75h4.5A1.125 1.125 0 0 1 20.25 4.875v4.5A1.125 1.125 0 0 1 19.125 10.5h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5ZM13.5 13.5h2.25v2.25H13.5V13.5ZM18 18h2.25v2.25H18V18Z" />
        </svg>
        Pindai Barcode
    </button>

    @unless ($this->nativeAvailable())
        <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
            Mode browser: kamera native tidak aktif. Masukkan barcode manual di bawah.
        </p>
    @endunless

    <form wire:submit="submitManual" class="flex gap-2">
        <input type="text" wire:model="manualBarcode" inputmode="numeric" placeholder="Ketik barcode manual"
               class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-slate-900 focus:outline-none">
        <button type="submit" class="rounded-lg bg-slate-200 px-4 text-sm font-medium active:bg-slate-300">Cari</button>
    </form>
    @error('manualBarcode') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

    @if ($error)
        <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $error }}</p>
    @endif

    @if ($success)
        <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ $success }}</p>
    @endif

    @if ($unknownBarcode)
        <div class="rounded-xl border border-dashed border-slate-300 bg-white p-4 text-sm">
            <p class="font-medium text-slate-900">Barcode tidak terdaftar</p>
            <p class="mt-1 font-mono text-xs text-slate-500">{{ $unknownBarcode }}</p>
            <p class="mt-2 text-xs text-slate-500">Tambahkan produk ini lebih dulu di master produk.</p>
        </div>
    @endif

    @if ($this->product())
        @php($product = $this->product())
        <div class="rounded-xl bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <a href="{{ route('products.show', $product) }}" class="block truncate font-semibold text-slate-900 underline decoration-slate-300 underline-offset-2">
                        {{ $product->name }}
                    </a>
                    <p class="font-mono text-xs text-slate-500">{{ $product->barcode }}</p>
                    <p class="mt-1 text-xs text-slate-500">Lokasi {{ $product->location?->code ?? '-' }}</p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-2xl font-bold tabular-nums {{ $product->isLowStock() ? 'text-red-600' : 'text-slate-900' }}">
                        {{ $product->stock }}
                    </p>
                    <p class="text-xs text-slate-500">{{ $product->unit }}</p>
                </div>
            </div>

            <form wire:submit="save" class="mt-4 space-y-3">
                <div class="grid grid-cols-3 gap-2">
                    @foreach (['in' => 'Masuk', 'out' => 'Keluar', 'adjust' => 'Koreksi'] as $value => $label)
                        <button type="button" wire:click="$set('type', '{{ $value }}')"
                                @class([
                                    'rounded-lg py-2 text-sm font-medium transition',
                                    'bg-slate-900 text-white' => $type === $value,
                                    'bg-slate-100 text-slate-600' => $type !== $value,
                                ])>{{ $label }}</button>
                    @endforeach
                </div>

                <label class="block">
                    <span class="text-xs font-medium text-slate-600">
                        {{ $type === 'adjust' ? 'Stok hasil hitung fisik' : 'Jumlah' }}
                    </span>
                    <input type="number" wire:model="quantity" min="0" inputmode="numeric"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base tabular-nums focus:border-slate-900 focus:outline-none">
                </label>
                @error('quantity') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                <label class="block">
                    <span class="text-xs font-medium text-slate-600">Catatan (opsional)</span>
                    <input type="text" wire:model="note" maxlength="255"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-900 focus:outline-none">
                </label>
                @error('note') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                <div class="flex gap-2">
                    <button type="submit"
                            class="flex-1 rounded-lg bg-emerald-600 py-3 text-sm font-semibold text-white active:bg-emerald-700">
                        Simpan Mutasi
                    </button>
                    <button type="button" wire:click="clear"
                            class="rounded-lg bg-slate-100 px-4 text-sm font-medium text-slate-600 active:bg-slate-200">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
