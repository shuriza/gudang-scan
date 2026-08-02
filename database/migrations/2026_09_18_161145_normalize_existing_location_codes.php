<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('locations')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (object $location): string => mb_strtoupper(trim($location->code)))
            ->each(function ($locations, string $canonicalCode): void {
                $keeper = $locations->first();

                $locations->skip(1)->each(function (object $duplicate) use ($keeper): void {
                    DB::table('products')->where('location_id', $duplicate->id)->update(['location_id' => $keeper->id]);
                    DB::table('locations')->where('id', $duplicate->id)->delete();
                });

                DB::table('locations')->where('id', $keeper->id)->update(['code' => $canonicalCode]);
            });
    }

    public function down(): void
    {
        // Canonicalization cannot reconstruct previous spelling variants.
    }
};
