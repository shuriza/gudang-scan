<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('unit')->constrained()->nullOnDelete();
        });

        DB::table('products')
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->select(['id', 'location'])
            ->orderBy('id')
            ->each(function (object $product): void {
                $locationCode = mb_strtoupper(trim($product->location));

                $locationId = DB::table('locations')->where('code', $locationCode)->value('id');

                if ($locationId === null) {
                    $locationId = DB::table('locations')->insertGetId([
                        'code' => $locationCode,
                        'name' => $locationCode,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['location_id' => $locationId]);
            });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('location', 32)->nullable()->after('unit');
        });

        DB::table('products')
            ->whereNotNull('location_id')
            ->orderBy('id')
            ->each(function (object $product): void {
                DB::table('products')->where('id', $product->id)->update([
                    'location' => DB::table('locations')->where('id', $product->location_id)->value('code'),
                ]);
            });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
