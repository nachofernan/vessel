<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Hero extends Model
{
    protected $fillable = [
        'name','fuerza','resistencia','destreza','inteligencia','suerte',
        'hp_actual','hp_maximo','oro'
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function talisman(): HasOne
    {
        return $this->hasOne(Talisman::class);
    }

    public function expeditions(): HasMany
    {
        return $this->hasMany(Expedition::class);
    }

    public function activeExpedition(): HasOne
    {
        return $this->hasOne(Expedition::class)->where('status', 'running');
    }

    public function equippedItems(): HasMany
    {
        return $this->hasMany(HeroEquipment::class)->with('equipment.element');
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class)->with('equipment.element');
    }

    // ─── HP ───────────────────────────────────────────────────────────────────

    public static function calcularHP(int $resistencia, int $sellos = 0): int
    {
        $factor_base     = 10;
        $bonus_por_sello = 15;
        return ($resistencia * $factor_base) + ($sellos * $bonus_por_sello);
    }

    // ─── Stats de combate (stat base + bonus de equipo) ───────────────────────

    /**
     * Ataque de combate = Fuerza + bonus del arma equipada
     */
    protected function ataque(): Attribute
    {
        return Attribute::make(
            get: function () {
                $arma = $this->equippedItems->first(fn($e) => $e->piece_type === 'arma');
                return $this->fuerza + ($arma ? $arma->statEfectivo() : 0);
            }
        );
    }

    /**
     * Defensa de combate = Resistencia + bonus del escudo equipado
     */
    protected function defensa(): Attribute
    {
        return Attribute::make(
            get: function () {
                $escudo = $this->equippedItems->first(fn($e) => $e->piece_type === 'escudo');
                return $this->resistencia + ($escudo ? $escudo->statEfectivo() : 0);
            }
        );
    }

    /**
     * Elemento del arma equipada (para multiplicador ofensivo en combate).
     * Devuelve slug o 'anima' si no hay arma.
     */
    public function elementoArma(): string
    {
        $arma = $this->equippedItems->first(fn($e) => $e->piece_type === 'arma');
        return $arma?->equipment->element->slug ?? 'anima';
    }

    /**
     * Elemento del escudo equipado (para multiplicador defensivo en combate).
     * Devuelve slug o 'anima' si no hay escudo.
     */
    public function elementoEscudo(): string
    {
        $escudo = $this->equippedItems->first(fn($e) => $e->piece_type === 'escudo');
        return $escudo?->equipment->element->slug ?? 'anima';
    }

    // ─── Alineación elemental del set ─────────────────────────────────────────

    /**
     * Calcula la alineación total del set (sin arma ni escudo, que operan distinto).
     * Set pieces: casco, pecho, brazos, piernas, amuleto → 5 piezas.
     * Bonus acumulativo del 20% por cada pieza adicional del mismo elemento.
     *
     * Devuelve [ 'slug' => alineación_efectiva, ... ]
     */
    public function alineacionSet(): array
    {
        $setPieces = ['casco', 'pecho', 'brazos', 'piernas', 'amuleto', 'arma', 'escudo'];

        $byElement = [];
        foreach ($this->equippedItems as $slot) {
            if (!in_array($slot->piece_type, $setPieces)) continue;
            $slug = $slot->equipment->element->slug;
            $byElement[$slug] = ($byElement[$slug] ?? 0) + $slot->equipment->alignment_bonus;
        }

        $result = [];
        foreach ($byElement as $slug => $baseAlign) {
            $count   = collect($this->equippedItems)
                ->filter(fn($e) => in_array($e->piece_type, $setPieces)
                    && $e->equipment->element->slug === $slug)
                ->count();
            $setBonus   = max(0, ($count - 1)) * 0.20;
            $result[$slug] = (int)($baseAlign * (1 + $setBonus));
        }

        return $result;
    }

    // ─── Stats totales legibles para la UI ───────────────────────────────────

    /**
     * Devuelve un array con stats base + bonus de equipo para mostrar en pantalla.
     */
    public function statSheet(): array
    {
        $bonuses = [
            'fuerza' => 0, 'resistencia' => 0,
            'destreza' => 0, 'inteligencia' => 0, 'suerte' => 0,
        ];

        $statMap = [
            'brazos' => 'fuerza', 'pecho' => 'resistencia',
            'piernas' => 'destreza', 'casco' => 'inteligencia', 'amuleto' => 'suerte',
        ];

        foreach ($this->equippedItems as $slot) {
            $stat = $statMap[$slot->piece_type] ?? null;
            if ($stat) {
                $bonuses[$stat] += $slot->statEfectivo();
            }
        }

        return [
            'fuerza'      => ['base' => $this->fuerza,      'bonus' => $bonuses['fuerza']],
            'resistencia' => ['base' => $this->resistencia, 'bonus' => $bonuses['resistencia']],
            'destreza'    => ['base' => $this->destreza,    'bonus' => $bonuses['destreza']],
            'inteligencia'=> ['base' => $this->inteligencia,'bonus' => $bonuses['inteligencia']],
            'suerte'      => ['base' => $this->suerte,      'bonus' => $bonuses['suerte']],
            'ataque'      => ['base' => $this->fuerza,      'bonus' => $this->ataque - $this->fuerza],
            'defensa'     => ['base' => $this->resistencia, 'bonus' => $this->defensa - $this->resistencia],
        ];
    }
}