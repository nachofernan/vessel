<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CombatLog extends Model
{
    //
    protected $fillable = [
        'expedition_id',
        'round_number',
        'hero_damage_dealt',
        'hero_damage_received',
        'hero_dodged',
        'hero_double_hit',
        'hero_critical',
        'enemy_fled',
        'narrative_line',
    ];
    
    public function expedition()
    {
        return $this->belongsTo(Expedition::class);
    }
}