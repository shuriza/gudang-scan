<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name'];

    protected $casts = ['archived_at' => 'datetime'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
