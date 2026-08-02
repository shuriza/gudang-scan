<?php

use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public ?int $editingLocationId = null;

    public string $code = '';

    public string $name = '';

    public bool $showArchived = false;

    public ?string $success = null;

    public function editLocation(int $locationId): void
    {
        $location = Location::findOrFail($locationId);
        $this->editingLocationId = $location->id;
        $this->code = $location->code;
        $this->name = $location->name;
        $this->success = null;
    }

    public function saveLocation(): void
    {
        $this->code = mb_strtoupper(trim($this->code));
        $this->name = trim($this->name);

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('locations', 'code')->ignore($this->editingLocationId)],
            'name' => ['required', 'string', 'max:255'],
        ], attributes: ['code' => 'kode lokasi', 'name' => 'nama lokasi']);

        $location = $this->editingLocationId === null
            ? new Location
            : Location::whereNull('archived_at')->findOrFail($this->editingLocationId);
        $location->fill($validated)->save();

        $this->resetForm();
        $this->success = 'Lokasi berhasil disimpan.';
    }

    public function archiveLocation(int $locationId): void
    {
        $archived = DB::transaction(function () use ($locationId): bool {
            $location = Location::lockForUpdate()->findOrFail($locationId);
            $productsCount = $location->products()->count();

            if ($productsCount > 0) {
                $this->addError('archive', 'Lokasi masih digunakan produk dan tidak dapat diarsipkan.');

                return false;
            }

            $location->forceFill(['archived_at' => now()])->save();

            return true;
        });

        if (! $archived) {
            return;
        }

        $this->success = 'Lokasi berhasil diarsipkan.';
    }

    public function restoreLocation(int $locationId): void
    {
        $restored = Location::whereKey($locationId)->whereNotNull('archived_at')->update(['archived_at' => null]);

        if ($restored > 0) {
            $this->success = 'Lokasi berhasil diaktifkan kembali.';
        }
    }

    public function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingLocationId', 'code', 'name');
    }

    public function with(): array
    {
        return [
            'locations' => Location::query()
                ->withCount('products')
                ->when($this->showArchived, fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
                ->orderBy('code')
                ->get(),
        ];
    }
};
?>

<div class="space-y-4">
    <form wire:submit="saveLocation" class="space-y-3 rounded-xl bg-white p-4 shadow-sm">
        <p class="font-semibold text-slate-900">{{ $editingLocationId ? 'Edit Lokasi' : 'Tambah Lokasi' }}</p>
        <div class="grid grid-cols-2 gap-2">
            <label>
                <span class="text-xs font-medium text-slate-600">Kode</span>
                <input type="text" wire:model="code" placeholder="A-01" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm uppercase focus:border-slate-900 focus:outline-none">
                @error('code') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label>
                <span class="text-xs font-medium text-slate-600">Nama</span>
                <input type="text" wire:model="name" placeholder="Rak A baris 1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-900 focus:outline-none">
                @error('name') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 rounded-lg bg-slate-900 py-2.5 text-sm font-semibold text-white">Simpan Lokasi</button>
            @if ($editingLocationId)
                <button type="button" wire:click="resetForm" class="rounded-lg bg-slate-100 px-4 text-sm font-medium text-slate-600">Batal</button>
            @endif
        </div>
        @error('archive') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
    </form>

    @if ($success)
        <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ $success }}</p>
    @endif

    <label class="flex items-center gap-2 text-sm text-slate-600">
        <input type="checkbox" wire:model.live="showArchived" class="size-4 rounded border-slate-300">
        Lokasi diarsipkan
    </label>

    <div class="space-y-2">
        @forelse ($locations as $location)
            <div wire:key="location-{{ $location->id }}" class="flex items-center gap-2 rounded-xl bg-white p-3 shadow-sm">
                <button type="button" wire:click="editLocation({{ $location->id }})" @disabled($location->isArchived()) class="min-w-0 flex-1 text-left disabled:opacity-60">
                    <p class="text-sm font-semibold text-slate-900">{{ $location->code }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $location->name }} &middot; {{ $location->products_count }} produk</p>
                </button>
                @if ($location->isArchived())
                    <button type="button" wire:click="restoreLocation({{ $location->id }})" class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">Aktifkan</button>
                @else
                    <button type="button" wire:click="archiveLocation({{ $location->id }})" class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">Arsipkan</button>
                @endif
            </div>
        @empty
            <p class="rounded-xl bg-white p-5 text-center text-sm text-slate-500">Belum ada lokasi.</p>
        @endforelse
    </div>
</div>