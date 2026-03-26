<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expedition extends Model
{
    protected $fillable = [
        'hero_id','zone_slug', 'kingdom_slug', 'element_id','duration_seconds',
        'status','event_type','resultado','carga_obtenida',
        'oro_obtenido','hero_died','started_at','completes_at','completed_at'
    ];

    protected $casts = [
        'resultado' => 'array',
        'started_at' => 'datetime',
        'completes_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Hero::class);
    }

    public function combatLogs(): HasMany
    {
        return $this->hasMany(CombatLog::class);
    }

    public function isExpired(): bool
    {
        return $this->completes_at && now()->gte($this->completes_at);
    }
}