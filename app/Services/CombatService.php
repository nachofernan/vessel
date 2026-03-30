<?php

namespace App\Services;

use App\Models\ElementRelation;
use App\Models\Hero;

class CombatService
{
    // ─── Construcción del enemigo ─────────────────────────────────────────────
 
    /**
     * Construye el array del enemigo con stats simétricos al héroe.
     * El poder escala por anillo y duración. Los stats se derivan del poder
     * con distribuciones fijas pero con varianza moderada por combate.
     *
     * El enemigo tiene: fuerza, resistencia, destreza, ataque, defensa, hp, poder.
     * La destreza del enemigo habilita esquive, doble golpe y evento especial,
     * igual que en el héroe.
     */
    public function buildEnemy(string $elementSlug, int $ring = 1, int $durationSeconds = 10): array
    {
        $poderBase = match($ring) {
            2       => 1000,
            3       => 10000,
            default => 100,
        };
 
        $calculo = (int)($poderBase * ($durationSeconds / 10));
        $poder = rand($calculo-50, $calculo+25);
 
        // Stats derivados del poder con distribución controlada.
        // El divisor central es fijo por stat; la varianza es ±10% por combate.
        // Esto da enemigos predecibles en escala pero distintos en cada encuentro.
        $varianzaStat = fn(float $base) => (int)round($base * (0.90 + mt_rand(0, 20) / 100));
 
        // Referencia: héroe con todos stats en 5 y equipo nivel 1 carga media
        // tiene ataque ~12, defensa ~12, HP ~50 al inicio.
        // Enemigo de 10s anillo 1 (poder 100) debe ser comparable.
        $fuerza      = $varianzaStat($poder / 20.0);
        $resistencia = $varianzaStat($poder / 20.0);
        $destreza    = $varianzaStat($poder / 30.0); // destreza más baja que el héroe en promedio
        $suerte      = $varianzaStat($poder / 35.0);
        $inteligencia = $varianzaStat($poder / 35.0);
 
        // Ataque y defensa incluyen un "bonus de equipo" implícito proporcional al poder.
        // Equivale a que el enemigo tiene su propio equipo del elemento.
        $equipBonus = (int)($poder / 15.0);
        $ataque  = $fuerza + $equipBonus;
        $defensa = $resistencia + $equipBonus;
 
        // HP: resistencia × 10 (misma fórmula que el héroe sin sellos)
        $hp = $resistencia * 10;
 
        return [
            'name'         => $this->enemyName($elementSlug),
            'element'      => $elementSlug,
            'poder'        => $poder,
            'fuerza'       => $fuerza,
            'resistencia'  => $resistencia,
            'destreza'     => $destreza,
            'suerte'       => $suerte,
            'inteligencia' => $inteligencia,
            'ataque'       => $ataque,
            'defensa'      => $defensa,
            'hp'           => $hp,
        ];
    }
 
    /**
     * Construye el guardián de anillo 1. Stats fijos y más altos que cualquier
     * enemigo común del anillo, pero con la misma estructura simétrica.
     * El guardián no tiene tope de rondas.
     */
    public function buildGuardian(string $elementSlug): array
    {
        // Poder equivalente a un enemigo de 60s+ del anillo 1
        $poder = 700;
 
        $fuerza      = (int)($poder / 9.0);
        $resistencia = (int)($poder / 8.0);  // más resistente que el héroe promedio
        $destreza    = (int)($poder / 18.0);
        $suerte      = (int)($poder / 20.0);
        $inteligencia = (int)($poder / 20.0);
 
        $equipBonus = (int)($poder / 12.0);
        $ataque  = $fuerza + $equipBonus;
        $defensa = $resistencia + $equipBonus;
        $hp      = $resistencia * 10;
 
        return [
            'name'         => $this->guardianName($elementSlug),
            'element'      => $elementSlug,
            'poder'        => $poder,
            'fuerza'       => $fuerza,
            'resistencia'  => $resistencia,
            'destreza'     => $destreza,
            'suerte'       => $suerte,
            'inteligencia' => $inteligencia,
            'ataque'       => $ataque,
            'defensa'      => $defensa,
            'hp'           => $hp,
            'is_guardian'  => true,
        ];
    }
 
    // ─── Resolución del combate ───────────────────────────────────────────────
 
    /**
     * Resuelve el combate ronda a ronda.
     *
     * Resultado posible:
     *   'victory' → héroe ganó
     *   'defeat'  → héroe perdió
     *   'draw'    → empate (ambos a 0 en la misma ronda, o límite de rondas)
     *
     * El guardián no tiene límite de rondas: pelea hasta el final.
     */
    public function resolve(Hero $hero, array $enemy): array
    {
        $hero->loadMissing('equippedItems.equipment.element');
 
        $talisman = $hero->talisman;
        $talisman->setRelation('hero', $hero);
 
        $isGuardian = $enemy['is_guardian'] ?? false;
        $maxRounds  = $isGuardian ? PHP_INT_MAX : 20;
 
        $heroHp  = $hero->hp_actual;
        $enemyHp = $enemy['hp'];
        $logs    = [];
        $round   = 0;
 
        $elementoArma    = $hero->elementoArma();
        $elementoEscudo  = $hero->elementoEscudo();
        $elementoEnemigo = $enemy['element'];
 
        // ── Chances de golpe (Fase 1 — Talismán) ─────────────────────────────
        $miPoderVsEnemigo    = $talisman->poderContra($elementoEnemigo, $hero);
        $poderEnemigoVsHeroe = $enemy['poder'];
 
        $chanceHeroeGolpea   = $miPoderVsEnemigo / ($miPoderVsEnemigo + $poderEnemigoVsHeroe);
        $chanceEnemigoGolpea = $poderEnemigoVsHeroe / ($poderEnemigoVsHeroe + $miPoderVsEnemigo);
 
        // ── Stats de combate fijos por duración ───────────────────────────────
        $heroAtaque  = $hero->ataque;
        $heroDefensa = $hero->defensa;
 
        while ($heroHp > 0 && $enemyHp > 0 && $round < $maxRounds) {
            $round++;
 
            // ── Fase 1: chequeo de golpe ──────────────────────────────────────
            $heroeGolpea   = (mt_rand(0, 1000) / 1000) < $chanceHeroeGolpea;
            $enemigoGolpea = (mt_rand(0, 1000) / 1000) < $chanceEnemigoGolpea;
 
            // ── Modificadores del héroe ───────────────────────────────────────
            $heroEsquiva      = $enemigoGolpea && $this->tiraDado($this->dodgeChance($hero->destreza));
            $heroEspecial     = $heroeGolpea   && $this->tiraDado($this->specialChance($hero->suerte));
            $heroMultEspecial = $heroEspecial
                ? $this->specialMultiplier($hero->suerte, $hero->inteligencia)
                : 1.0;
 
            // ── Modificadores del enemigo ─────────────────────────────────────
            $enemyEsquiva      = $heroeGolpea   && $this->tiraDado($this->dodgeChance($enemy['destreza']));
            $enemyEspecial     = $enemigoGolpea && $this->tiraDado($this->specialChance($enemy['suerte']));
            $enemyMultEspecial = $enemyEspecial
                ? $this->specialMultiplier($enemy['suerte'], $enemy['inteligencia'])
                : 1.0;
 
            // ── Varianza ±15% ─────────────────────────────────────────────────
            $varianzaH = 0.85 + (mt_rand(0, 30) / 100);
            $varianzaE = 0.85 + (mt_rand(0, 30) / 100);
 
            // ── Fase 2: daño del héroe ────────────────────────────────────────
            $dmgHero = 0;
            if ($heroeGolpea && !$enemyEsquiva) {
                $multOfensivo = $this->getMultiplier($elementoArma, $elementoEnemigo);
                $dmgHero = (int)($heroAtaque * $multOfensivo * $varianzaH * $heroMultEspecial);
                $dmgHero = max(1, $dmgHero);
            }
 
            // ── Fase 2: daño recibido ─────────────────────────────────────────
            $dmgReceived = 0;
            if ($enemigoGolpea && !$heroEsquiva) {
                $multOfensivoEnemigo = $this->getMultiplier($elementoEnemigo, $elementoArma);
                $multDefensivo       = $this->getMultiplier($elementoEnemigo, $elementoEscudo);
 
                $golpeBruto  = (int)($enemy['ataque'] * $multOfensivoEnemigo * $varianzaE * $enemyMultEspecial);
                $absorcion   = (int)($heroDefensa * $multDefensivo);
                $dmgReceived = max(1, $golpeBruto - $absorcion);
            }
 
            $enemyHp -= $dmgHero;
            $heroHp  -= $dmgReceived;
 
            $logs[] = [
                'round_number'          => $round,
                'hero_damage_dealt'     => $dmgHero,
                'hero_damage_received'  => $dmgReceived,
                'hero_dodged'           => $heroEsquiva,
                'hero_double_hit'       => false, // reemplazado por evento especial
                'hero_critical'         => $heroEspecial,
                'hero_special_mult'     => $heroMultEspecial,
                'enemy_dodged'          => $enemyEsquiva,
                'enemy_special'         => $enemyEspecial,
                'enemy_special_mult'    => $enemyMultEspecial,
                'enemy_fled'            => false,
                'narrative_line'        => $this->narrativeLine(
                    $round, $dmgHero, $dmgReceived,
                    $heroeGolpea, $enemigoGolpea,
                    $heroEsquiva, $enemyEsquiva,
                    $heroEspecial, $enemyEspecial,
                    $heroMultEspecial, $enemyMultEspecial,
                    $enemy['name']
                ),
            ];
        }
 
        // ── Resultado ─────────────────────────────────────────────────────────
        $outcome = $this->determineOutcome($heroHp, $enemyHp, $round, $maxRounds);
 
        return [
            'outcome'               => $outcome,           // 'victory' | 'defeat' | 'draw'
            'hero_won'              => $outcome === 'victory', // compatibilidad con código existente
            'hero_hp_left'          => max(0, $heroHp),
            'rounds'                => $round,
            'logs'                  => $logs,
            'enemy'                 => $enemy,
            'chance_heroe_golpea'   => round($chanceHeroeGolpea * 100, 1),
            'chance_enemigo_golpea' => round($chanceEnemigoGolpea * 100, 1),
        ];
    }
 
    // ─── Helpers de resultado ─────────────────────────────────────────────────
 
    private function determineOutcome(int $heroHp, int $enemyHp, int $round, int $maxRounds): string
    {
        $heroVivo  = $heroHp > 0;
        $enemyVivo = $enemyHp > 0;
 
        if ($heroVivo && !$enemyVivo)  return 'victory';
        if (!$heroVivo && $enemyVivo)  return 'defeat';
        if (!$heroVivo && !$enemyVivo) return 'draw';   // ambos a 0 en misma ronda
 
        // Límite de rondas alcanzado con ambos vivos (solo enemigos comunes)
        if ($round >= $maxRounds) return 'draw';
 
        // No debería llegar acá, pero por seguridad
        return 'draw';
    }
 
    // ─── Helpers de probabilidad ──────────────────────────────────────────────
 
    private function tiraDado(float $chance): bool
    {
        return (mt_rand(0, 1000) / 1000) < $chance;
    }
 
    /**
     * Chance de esquivar. Curva suavizada, techo 40%.
     * Con destreza 10: ~33%. Con destreza 5: ~20%.
     */
    private function dodgeChance(float $destreza): float
    {
        return min(0.40, $destreza / ($destreza + 20));
    }
 
    /**
     * Chance de evento especial (crítico/golpe potente).
     * Controlada por Suerte. Curva suavizada, techo 30%.
     * Con suerte 10: ~14%. Con suerte 5: ~8%.
     */
    private function specialChance(float $suerte): float
    {
        return min(0.30, $suerte / ($suerte + 60));
    }
 
    /**
     * Multiplicador del evento especial.
     * Determinado por Suerte + Inteligencia juntas.
     * Rango: ×1.5 (stats mínimos) hasta ×3.0 (stats muy altos).
     *
     * Fórmula: 1.5 + (suerte + inteligencia) / 40
     * Con suerte 5 + int 5 (=10): ×1.75
     * Con suerte 10 + int 10 (=20): ×2.0
     * Con suerte 20 + int 20 (=40): ×2.5
     * Con suerte 30 + int 30 (=60): ×3.0 (techo)
     */
    private function specialMultiplier(float $suerte, float $inteligencia): float
    {
        return min(3.0, 1.5 + ($suerte + $inteligencia) / 40.0);
    }
 
    // ─── Multiplicadores elementales ─────────────────────────────────────────
 
    /**
     * Tabla unificada con la del Talismán.
     * Ánima atacando: ×1.0 contra todos.
     * No-Ánima atacando a Ánima: ×0.7.
     */
    private const MULT = [
        'fire'   => ['fire'=>1.0, 'water'=>0.6, 'earth'=>0.6, 'air'=>1.5, 'light'=>1.0, 'shadow'=>1.5, 'anima'=>0.7],
        'water'  => ['fire'=>1.5, 'water'=>1.0, 'earth'=>1.0, 'air'=>0.6, 'light'=>1.5, 'shadow'=>0.6, 'anima'=>0.7],
        'earth'  => ['fire'=>1.5, 'water'=>1.0, 'earth'=>1.0, 'air'=>0.6, 'light'=>1.5, 'shadow'=>0.6, 'anima'=>0.7],
        'air'    => ['fire'=>0.6, 'water'=>1.5, 'earth'=>1.5, 'air'=>1.0, 'light'=>0.6, 'shadow'=>1.0, 'anima'=>0.7],
        'light'  => ['fire'=>1.0, 'water'=>0.6, 'earth'=>0.6, 'air'=>1.5, 'light'=>1.0, 'shadow'=>1.5, 'anima'=>0.7],
        'shadow' => ['fire'=>0.6, 'water'=>1.5, 'earth'=>1.5, 'air'=>1.0, 'light'=>0.6, 'shadow'=>1.0, 'anima'=>0.7],
        'anima'  => ['fire'=>1.0, 'water'=>1.0, 'earth'=>1.0, 'air'=>1.0, 'light'=>1.0, 'shadow'=>1.0, 'anima'=>1.0],
    ];
 
    public function getMultiplier(string $sourceSlug, string $targetSlug): float
    {
        return self::MULT[$sourceSlug][$targetSlug] ?? 1.0;
    }
 
    // ─── Nombres ──────────────────────────────────────────────────────────────
 
    private function enemyName(string $elementSlug): string
    {
        return match($elementSlug) {
            'fire'   => 'Bestia de Magma',
            'water'  => 'Espectro Fluido',
            'earth'  => 'Coloso de Piedra',
            'air'    => 'Elemental de Viento',
            'light'  => 'Centinela de Luz',
            'shadow' => 'Sombra Sin Forma',
            'anima'  => 'Eco del Buscador',
            default  => 'Criatura Desconocida',
        };
    }
 
    private function guardianName(string $elementSlug): string
    {
        return match($elementSlug) {
            'fire'   => 'Guardián de Magma',
            'water'  => 'Leviatán Menor',
            'earth'  => 'Centinela de Piedra',
            'air'    => 'Vórtice con Voluntad',
            'light'  => 'Heraldo de Luz',
            'shadow' => 'Sombra Antigua',
            'anima'  => 'Eco Primordial',
            default  => 'Guardián Desconocido'
        };
    }
 
    // ─── Narrativa ────────────────────────────────────────────────────────────
 
    private function narrativeLine(
        int    $round,
        int    $dealt,
        int    $received,
        bool   $heroeGolpea,
        bool   $enemigoGolpea,
        bool   $heroEsquiva,
        bool   $enemyEsquiva,
        bool   $heroEspecial,
        bool   $enemyEspecial,
        float  $heroMult,
        float  $enemyMult,
        string $enemyName
    ): string {
        $line = "Ronda {$round} — ";
 
        // Ataque del héroe
        if (!$heroeGolpea) {
            $line .= "El golpe del Buscador no penetra. ";
        } elseif ($enemyEsquiva) {
            $line .= "{$enemyName} esquiva el ataque. ";
        } elseif ($heroEspecial) {
            $multStr = number_format($heroMult, 1);
            $line .= "¡Golpe especial ×{$multStr}! Daño: {$dealt}. ";
        } else {
            $line .= "El Buscador golpea a {$enemyName}. Daño: {$dealt}. ";
        }
 
        // Ataque del enemigo
        if (!$enemigoGolpea) {
            $line .= "{$enemyName} no logra penetrar las defensas.";
        } elseif ($heroEsquiva) {
            $line .= "El Buscador esquiva el contraataque.";
        } elseif ($enemyEspecial) {
            $multStr = number_format($enemyMult, 1);
            $line .= "{$enemyName} golpea con furia ×{$multStr}. Daño recibido: {$received}.";
        } elseif ($received === 0) {
            $line .= "El escudo absorbe todo el golpe.";
        } else {
            $line .= "{$enemyName} responde. Daño recibido: {$received}.";
        }
 
        return $line;
    }
}