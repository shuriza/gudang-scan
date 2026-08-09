<?php

use App\Exceptions\StockException;
use App\Models\InventoryDocument;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\InventoryDocumentService;
use Livewire\Component;

new class extends Component
{
    public string $type = InventoryDocument::TYPE_RECEIPT;
    public string $number = '';
    public ?int $supplierId = null;
    public string $destination = '';
    public string $notes = '';
    public array $quantities = [];
    public ?string $message = null;

    public function saveDraft(): void
{
        $validated = $this->validate([
            'type' => ['required', 'in:receipt,issue'],
            'number' => ['required', 'string', 'max:64'],
            'supplierId' => ['nullable', 'required_if:type,receipt', 'exists:suppliers,id'],
            'destination' => ['nullable', 'required_if:type,issue', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        $items = collect($validated['quantities'])->filter(fn ($quantity) => (int) $quantity > 0);

        if ($items->isEmpty()) {
            $this->addError('quantities', 'Isi minimal satu jumlah produk.');

            return;
        }

        $document = InventoryDocument::create([
            'number' => trim($validated['number']),
            'type' => $validated['type'],
            'status' => InventoryDocument::STATUS_DRAFT,
            'supplier_id' => $validated['supplierId'],
            'destination' => filled($validated['destination']) ? trim($validated['destination']) : null,
            'document_date' => today(),
            'notes' => filled($validated['notes']) ? trim($validated['notes']) : null,
        ]);

        foreach ($items as $productId => $quantity) {
            $document->items()->create(['product_id' => $productId, 'quantity' => $quantity]);
        }

        $this->reset('number', 'supplierId', 'destination', 'notes', 'quantities');
        $this->message = 'Draft dokumen disimpan.';
    }

    public function post(int $documentId, InventoryDocumentService $service): void
{
        try {
            $service->post(InventoryDocument::findOrFail($documentId));
        } catch (StockException $exception) {
            $this->addError('document', $exception->getMessage());

            return;
        }

        $this->message = 'Dokumen berhasil diposting.';
    }

    public function cancel(int $documentId, InventoryDocumentService $service): void
{
        try {
            $service->cancel(InventoryDocument::findOrFail($documentId));
        } catch (StockException $exception) {
            $this->addError('document', $exception->getMessage());

            return;
        }

        $this->message = 'Dokumen dibatalkan dengan mutasi reversal.';
    }

    public function with(): array
{
        return [
            'products' => Product::query()->whereNull('archived_at')->orderBy('name')->get(),
            'suppliers' => Supplier::query()->whereNull('archived_at')->orderBy('name')->get(),
            'documents' => InventoryDocument::query()->with(['supplier', 'items'])->latest('id')->limit(20)->get(),
        ];
    }
};
?>

<div class="space-y-4">
    <form wire:submit="saveDraft" class="space-y-3 rounded-xl bg-white p-4 shadow-sm">
        <div class="grid grid-cols-2 gap-2">
            <button type="button" wire:click="$set('type', 'receipt')" class="rounded-lg py-2 text-sm font-semibold {{ $type === 'receipt' ? 'bg-emerald-600 text-white' : 'bg-slate-100' }}">Penerimaan</button>
            <button type="button" wire:click="$set('type', 'issue')" class="rounded-lg py-2 text-sm font-semibold {{ $type === 'issue' ? 'bg-red-600 text-white' : 'bg-slate-100' }}">Pengeluaran</button>
</div>
        <input wire:model="number" placeholder="Nomor dokumen" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        @error('number') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        @if ($type === 'receipt')
            <select wire:model="supplierId" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                <option value="">Pilih supplier</option>
                @foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach
            </select>
            @error('supplierId') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        @else
            <input wire:model="destination" placeholder="Tujuan pengeluaran" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            @error('destination') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        @endif
        <div class="max-h-72 space-y-2 overflow-y-auto">
            @foreach ($products as $product)
                <label wire:key="document-product-{{ $product->id }}" class="flex items-center gap-2 rounded-lg bg-slate-50 p-2">
                    <span class="min-w-0 flex-1 truncate text-sm">{{ $product->name }} <span class="text-xs text-slate-400">({{ $product->stock }} {{ $product->unit }})</span></span>
                    <input type="number" min="0" wire:model="quantities.{{ $product->id }}" class="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                </label>
            @endforeach
</div>
        @error('quantities') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        @error('document') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        <input wire:model="notes" placeholder="Catatan (opsional)" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        <button type="submit" class="w-full rounded-lg bg-slate-900 py-3 text-sm font-semibold text-white">Simpan Draft</button>
    </form>
    @if ($message)<p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ $message }}</p>@endif
    <div class="space-y-2">
        @foreach ($documents as $document)
            <div wire:key="document-{{ $document->id }}" class="rounded-xl bg-white p-3 shadow-sm">
                <div class="flex justify-between gap-2"><span class="font-medium">{{ $document->number }}</span><span class="text-xs uppercase text-slate-500">{{ $document->status }}</span></div>
                <p class="text-xs text-slate-500">{{ $document->type === 'receipt' ? 'Penerimaan' : 'Pengeluaran' }} &middot; {{ $document->items->count() }} item</p>
                @if ($document->status === 'draft')
                    <button type="button" wire:click="post({{ $document->id }})" class="mt-2 w-full rounded-lg bg-emerald-600 py-2 text-sm font-semibold text-white">Posting</button>
                @elseif ($document->status === 'posted')
                    <button type="button" wire:click="cancel({{ $document->id }})" class="mt-2 w-full rounded-lg bg-red-50 py-2 text-sm font-semibold text-red-700">Batalkan</button>
                @endif
</div>
        @endforeach
</div>
</div>