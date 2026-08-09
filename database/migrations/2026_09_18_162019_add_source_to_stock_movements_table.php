<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('inventory_document_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->foreignId('inventory_document_item_id')->nullable()->after('inventory_document_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_document_item_id');
            $table->dropConstrainedForeignId('inventory_document_id');
        });
    }
};
