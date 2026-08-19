<?php

namespace Tests\Feature;

use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_creates_checksum_and_restore_rejects_corrupt_files(): void
    {
        $service = app(BackupService::class);
        $backup = $service->backup();

        $this->assertFileExists($backup);
        $this->assertFileExists($backup.'.json');

        File::put($backup, 'corrupt');

        $this->expectException(\RuntimeException::class);
        $service->restore($backup);
    }
}
