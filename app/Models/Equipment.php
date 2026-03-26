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

    // ── Qué stat modifica cada pieza ─────────────────────────────────────────
    // Casco       → Inteligencia
    // Pecho       → Resistencia
    // Brazos      → Fuerza
    // Piernas     → Destreza
    // Escudo      → Defensa de combate (no stat base)
    // Arma        → Ataque de combate (no stat base)
    // Amuleto     → Suerte

    public static function statForPiece(string $pieceType): string
    {
        return match($pieceType) {
            'casco'    => 'inteligencia',
            'pecho'    => 'resistencia',
            'brazos'   => 'fuerza',
            'piernas'  => 'destreza',
            'escudo'   => 'defensa',   // combate
            'arma'     => 'ataque',    // combate
            'amuleto'  => 'suerte',
            default    => 'fuerza',
        };
    }

    // Etiqueta legible para la UI
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
     * Stat efectivo según carga de la instancia.
     * +1 por cada 5 puntos de carga.
     */
    public function statEfectivo(int $carga): int
    {
        return $this->stat_bonus + (int)floor($carga / 5);
    }

    public function alignmentEfectivo(int $carga): int
    {
        return $this->alignment_bonus + (int)floor($carga / 5);
    }
}