<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seal extends Model
{
    protected $fillable = ['hero_id', 'element_slug', 'ring', 'obtained_at'];

    protected $casts = [
        'obtained_at' => 'datetime',
    ];

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Hero::class);
    }
}