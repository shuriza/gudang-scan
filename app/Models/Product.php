<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'barcode', 'name', 'unit', 'location', 'stock', 'min_stock',
    ];

    protected $casts = [
        'stock' => 'integer',
        'min_stock' => 'integer',
        'archived_at' => 'datetime',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLowStock(): bool
    {
        return $this->min_stock > 0 && $this->stock <= $this->min_stock;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
