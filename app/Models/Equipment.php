<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Equipment extends Model
{
    protected $table = 'equipments';
    
    protected $fillable = [
        'piece_type', 'element_id', 'level',
        'name', 'stat_bonus', 'alignment_bonus', 'carga_maxima', 'valor_base',
    ];

    public function element(): BelongsTo
    {
        return $this->belongsTo(Element::class);
    }

    public static function statForPiece(string $pieceType): string
    {
        return match($pieceType) {
            'casco'    => 'inteligencia',
            'pecho'    => 'resistencia',
            'brazos'   => 'fuerza',
            'piernas'  => 'destreza',
            'escudo'   => 'defensa',
            'arma'     => 'ataque',
            'amuleto'  => 'suerte',
            default    => 'fuerza',
        };
    }

    public static function labelForPiece(string $pieceType): string
    {
        return match($pieceType) {
            'casco'   => 'Casco',
            'pecho'   => 'Pecho',
            'brazos'  => 'Brazos',
            'piernas' => 'Piernas',
            'escudo'  => 'Escudo',
            'arma'    => 'Arma',
            'amuleto' => 'Amuleto',
            default   => ucfirst($pieceType),
        };
    }

    /**
     * Stat efectivo con progresión triangular.
     * k puntos de bonus requieren k*(k+1)/2 de carga acumulada.
     * Fórmula inversa: k = floor((-1 + sqrt(1 + 8*carga)) / 2)
     *
     * Ejemplos:
     *   carga  10 → k=3  → stat_bonus + 3
     *   carga  50 → k=9  → stat_bonus + 9
     *   carga 100 → k=13 → stat_bonus + 13
     *   carga 1000 → k=44
     *   carga 10000 → k=140
     */
    public function statEfectivo(int $carga): int
    {
        $k = (int)((-1 + sqrt(1 + 8 * $carga)) / 2);
        return $this->stat_bonus + $k;
    }

    /**
     * Alignment efectivo — misma progresión triangular que stat.
     */
    public function alignmentEfectivo(int $carga): int
    {
        $k = (int)((-1 + sqrt(1 + 8 * $carga)) / 2);
        return $this->alignment_bonus + $k;
    }
}