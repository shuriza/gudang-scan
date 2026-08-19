<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class BackupService
{
    public function backup(): string
    {
        $source = database_path('database.sqlite');

        if (! File::exists($source)) {
            throw new RuntimeException('Database SQLite tidak ditemukan.');
        }

        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);

        $destination = $directory.DIRECTORY_SEPARATOR.'gudang-scan-'.now()->format('YmdHis').'.sqlite';
        File::copy($source, $destination);

        $payload = [
            'version' => config('app.version', '1'),
            'created_at' => now()->toIso8601String(),
            'checksum' => hash_file('sha256', $destination),
        ];
        File::put($destination.'.json', json_encode($payload, JSON_PRETTY_PRINT));

        return $destination;
    }

    public function restore(string $backupPath): void
    {
        $metadataPath = $backupPath.'.json';

        if (! File::exists($backupPath) || ! File::exists($metadataPath)) {
            throw new RuntimeException('File backup tidak lengkap.');
        }

        $metadata = json_decode(File::get($metadataPath), true);
        $checksum = $metadata['checksum'] ?? null;

        if (! is_string($checksum) || hash_file('sha256', $backupPath) !== $checksum) {
            throw new RuntimeException('Checksum backup tidak valid.');
        }

        $this->backup();
        File::copy($backupPath, database_path('database.sqlite'));
    }
}
