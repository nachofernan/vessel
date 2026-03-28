<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Talisman extends Model
{
    protected $table = 'talismans';

    protected $fillable = [
        'hero_id',
        'esencia_fire', 'esencia_water', 'esencia_earth', 'esencia_air',
        'esencia_light', 'esencia_shadow', 'esencia_anima',
    ];

    public const MAX_ESENCIA = 100;

    public const ELEMENTOS = ['fire', 'water', 'earth', 'air', 'light', 'shadow', 'anima'];

    public const NOMBRES = [
        'fire'   => 'Fuego',  'water'  => 'Agua',   'earth'  => 'Tierra',
        'air'    => 'Aire',   'light'  => 'Luz',    'shadow' => 'Sombra', 'anima'  => 'Ánima',
    ];

    public const COLORES = [
        'fire'   => '#ef4444', 'water'  => '#3b82f6', 'earth'  => '#92400e',
        'air'    => '#7dd3fc', 'light'  => '#fbbf24', 'shadow' => '#7c3aed', 'anima'  => '#9ca3af',
    ];

    /**
     * Multiplicadores para el cálculo de poder del Talismán (Fase 1).
     *
     * Ánima atacando a cualquiera (incluyendo Ánima): ×1.0
     * Cualquier elemento no-Ánima atacando a Ánima: ×0.70
     * Ánima vs Ánima: ×1.0 (cubierto por la fila 'anima')
     *
     * Nota: el ×0.70 para no-Ánima vs Ánima se aplica en la columna 'anima'
     * de cada fila de elemento clásico.
     */
    private const MULT = [
        'fire'   => ['fire'=>1.0, 'water'=>0.6, 'earth'=>0.6, 'air'=>1.5, 'light'=>1.0, 'shadow'=>1.5, 'anima'=>0.70],
        'water'  => ['fire'=>1.5, 'water'=>1.0, 'earth'=>1.0, 'air'=>0.6, 'light'=>1.5, 'shadow'=>0.6, 'anima'=>0.70],
        'earth'  => ['fire'=>1.5, 'water'=>1.0, 'earth'=>1.0, 'air'=>0.6, 'light'=>1.5, 'shadow'=>0.6, 'anima'=>0.70],
        'air'    => ['fire'=>0.6, 'water'=>1.5, 'earth'=>1.5, 'air'=>1.0, 'light'=>0.6, 'shadow'=>1.0, 'anima'=>0.70],
        'light'  => ['fire'=>1.0, 'water'=>0.6, 'earth'=>0.6, 'air'=>1.5, 'light'=>1.0, 'shadow'=>1.5, 'anima'=>0.70],
        'shadow' => ['fire'=>0.6, 'water'=>1.5, 'earth'=>1.5, 'air'=>1.0, 'light'=>0.6, 'shadow'=>1.0, 'anima'=>0.70],
        'anima'  => ['fire'=>1.0, 'water'=>1.0, 'earth'=>1.0, 'air'=>1.0, 'light'=>1.0, 'shadow'=>1.0, 'anima'=>1.0],
    ];

    public function hero()
    {
        return $this->belongsTo(Hero::class);
    }

    // ─── Esencia farmeada (DB) ────────────────────────────────────────────────

    public function getEsencia(string $slug): int
    {
        return $this->{"esencia_{$slug}"} ?? 0;
    }

    /**
     * Agrega esencia respetando el tope MAX_ESENCIA.
     * Devuelve la cantidad efectivamente agregada.
     */
    public function addEsencia(string $slug, int $amount): int
    {
        $col     = "esencia_{$slug}";
        $current = $this->{$col} ?? 0;
        $added   = min($amount, self::MAX_ESENCIA - $current);

        if ($added > 0) {
            $this->increment($col, $added);
            $this->refresh();
        }

        return $added;
    }

    /**
     * Resetea la esencia de un reino, respetando el piso si el anillo 1
     * ya fue conquistado (sello obtenido).
     *
     * Si el héroe tiene el sello del anillo 1 de ese reino, la esencia
     * no puede bajar de MAX_ESENCIA (100). Si no tiene sello, va a 0.
     */
    public function resetEsencia(string $slug, bool $tieneSellosAnillo1 = false): void
    {
        $piso = $tieneSellosAnillo1 ? self::MAX_ESENCIA : 0;
        $this->update(["esencia_{$slug}" => $piso]);
    }

    // ─── Esencia efectiva (DB + bonus de equipo, no persiste) ─────────────────

    /**
     * Devuelve la esencia efectiva de cada elemento: farmeada + alignment del equipo.
     * El Hero debe tener 'equippedItems.equipment.element' cargado.
     *
     * @return array<string, int>
     */
    public function esenciasEfectivas(?Hero $hero = null): array
    {
        $hero = $hero ?? $this->hero;
        $result = [];

        $equipBonus = [];
        if ($hero && $hero->relationLoaded('equippedItems')) {
            $setPieces = ['casco', 'pecho', 'brazos', 'piernas', 'amuleto', 'arma', 'escudo'];
            $countPorElemento = [];

            foreach ($hero->equippedItems as $slot) {
                $eq   = $slot->equipment;
                $slug = $eq->element->slug;

                if (in_array($slot->piece_type, $setPieces)) {
                    $equipBonus[$slug]       = ($equipBonus[$slug] ?? 0) + $slot->alignmentEfectivo();
                    $countPorElemento[$slug] = ($countPorElemento[$slug] ?? 0) + 1;
                }
            }

            foreach ($equipBonus as $slug => $base) {
                $count    = $countPorElemento[$slug] ?? 1;
                $setBonus = max(0, $count - 1) * 0.20;
                $equipBonus[$slug] = (int)($base * (1 + $setBonus));
            }
        }

        foreach (self::ELEMENTOS as $slug) {
            $result[$slug] = $this->getEsencia($slug) + ($equipBonus[$slug] ?? 0);
        }

        return $result;
    }

    /**
     * Poder del Talismán contra un elemento enemigo específico.
     */
    public function poderContra(string $enemyElementSlug, ?Hero $hero = null): float
    {
        $esencias = $this->esenciasEfectivas($hero);
        $poder    = 0.0;

        foreach ($esencias as $slug => $valor) {
            $mult   = self::MULT[$slug][$enemyElementSlug] ?? 1.0;
            $poder += $valor * $mult;
        }

        return $poder;
    }

    /**
     * Probabilidad de que el héroe golpee al enemigo en Fase 1.
     */
    public function chanceDeGolpe(float $poderEnemigo, string $enemyElementSlug): float
    {
        $miPoder = $this->poderContra($enemyElementSlug);
        $total   = $miPoder + $poderEnemigo;

        if ($total <= 0) return 0.5;

        return $miPoder / $total;
    }

    // ─── Display ─────────────────────────────────────────────────────────────

    public function todasLasEsencias(): array
    {
        $result = [];
        foreach (self::ELEMENTOS as $slug) {
            $result[$slug] = $this->getEsencia($slug);
        }
        return $result;
    }

    public function esenciaTotal(): int
    {
        return array_sum($this->todasLasEsencias());
    }

    public function poderTotal(): int
    {
        return array_sum($this->esenciasEfectivas());
    }
}