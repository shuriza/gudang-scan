<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['type', 'created_at']);
            $table->index(['inventory_document_id', 'created_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['archived_at', 'min_stock', 'stock']);
            $table->index(['location_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['type', 'created_at']);
            $table->dropIndex(['inventory_document_id', 'created_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['archived_at', 'min_stock', 'stock']);
            $table->dropIndex(['location_id', 'archived_at']);
        });
    }
};
