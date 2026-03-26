<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketStock extends Model
{
    protected $fillable = ['items', 'generated_at'];

    protected $casts = [
        'items'        => 'array',
        'generated_at' => 'datetime',
    ];

    public function isFresh(): bool
    {
        // Válido mientras sea el mismo minuto calendario
        return $this->generated_at->format('Y-m-d H:i') === now()->format('Y-m-d H:i');
    }
}