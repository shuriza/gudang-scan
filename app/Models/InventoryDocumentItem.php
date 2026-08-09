<?php

namespace App\Models;

use Database\Factories\InventoryDocumentItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryDocumentItem extends Model
{
    /** @use HasFactory<InventoryDocumentItemFactory> */
    use HasFactory;

    protected $fillable = ['product_id', 'quantity'];

    protected $casts = ['quantity' => 'integer'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class, 'inventory_document_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
