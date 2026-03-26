<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeroEquipment extends Model
{
    protected $table = 'hero_equipment';

    protected $fillable = ['hero_id', 'piece_type', 'equipment_id', 'carga'];

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Hero::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function statEfectivo(): int
    {
        return $this->equipment->statEfectivo($this->carga);
    }

    public function alignmentEfectivo(): int
    {
        return $this->equipment->alignmentEfectivo($this->carga);
    }

    public function cargaPct(): int
    {
        $max = $this->equipment->carga_maxima;
        return $max > 0 ? (int)floor(($this->carga / $max) * 100) : 0;
    }
}