<?php

use App\Models\AppSetting;
use App\Services\BackupService;
use Livewire\Component;

new class extends Component
{
    public string $warehouseName = '';
    public string $managerName = '';
    public ?string $message = null;

    public function mount(): void
    {
        $this->warehouseName = AppSetting::query()->find('warehouse_name')?->value ?? '';
        $this->managerName = AppSetting::query()->find('manager_name')?->value ?? '';
    }

    public function save(): void
    {
        $validated = $this->validate([
            'warehouseName' => ['required', 'string', 'max:255'],
            'managerName' => ['nullable', 'string', 'max:255'],
        ]);

        AppSetting::query()->updateOrCreate(['key' => 'warehouse_name'], ['value' => trim($validated['warehouseName'])]);
        AppSetting::query()->updateOrCreate(['key' => 'manager_name'], ['value' => trim((string) $validated['managerName'])]);
        $this->message = 'Pengaturan disimpan.';
    }

    public function backup(BackupService $backups): void
    {
        $path = $backups->backup();
        $this->message = 'Backup dibuat: '.basename($path);
    }

    public function restoreLatest(BackupService $backups): void
    {
        $files = collect(glob(storage_path('app/backups/*.sqlite')) ?: [])->sort()->values();

        if ($files->isEmpty()) {
            $this->addError('backup', 'Belum ada file backup.');

            return;
        }

        try {
            $backups->restore($files->last());
        } catch (\RuntimeException $exception) {
            $this->addError('backup', $exception->getMessage());

            return;
        }

        $this->message = 'Restore berhasil.';
    }
};
?>

<div class="space-y-4">
    <form wire:submit="save" class="space-y-3 rounded-xl bg-white p-4 shadow-sm">
        <label class="block">
            <span class="text-xs font-medium text-slate-600">Nama gudang</span>
            <input wire:model="warehouseName" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            @error('warehouseName') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="block">
            <span class="text-xs font-medium text-slate-600">Penanggung jawab</span>
            <input wire:model="managerName" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </label>
        <button type="submit" class="w-full rounded-lg bg-slate-900 py-3 text-sm font-semibold text-white">Simpan Pengaturan</button>
    </form>

    <div class="grid grid-cols-2 gap-2">
        <button type="button" wire:click="backup" class="rounded-lg bg-emerald-600 py-3 text-sm font-semibold text-white">Backup</button>
        <button type="button" wire:click="restoreLatest" class="rounded-lg bg-slate-100 py-3 text-sm font-semibold text-slate-700">Restore Terakhir</button>
    </div>
    @error('backup') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
    @if ($message)
        <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ $message }}</p>
    @endif
    <p class="text-xs text-slate-500">Versi aplikasi: {{ config('app.version', config('nativephp.version', 'DEBUG')) }}</p>
</div>
