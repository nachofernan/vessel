<?php

namespace App\Services;

use App\Models\ElementRelation;
use App\Models\Hero;

class CombatService
{
    /**
     * Construye el enemigo común.
     * Stats aleatorios basados en poder escalado por duración.
     */
    public function buildEnemy(string $elementSlug, int $ring = 1, int $durationSeconds = 10): array
    {
        $poderBase = match($ring) {
            2       => 1000,
            3       => 10000,
            default => 100,
        };

        $poder = $poderBase * ($durationSeconds / 10);

        $ataque  = (int)($poder / rand(10, 12));
        $hp      = rand(25, (int)($poder / 2));
        $defensa = (int)($poder / rand(10, 12));

        return [
            'name'    => $this->enemyName($elementSlug),
            'element' => $elementSlug,
            'poder'   => $poder,
            'hp'      => $hp,
            'ataque'  => $ataque,
            'defensa' => $defensa,
        ];
    }

    /**
     * Construye el guardián de anillo 1 para un reino.
     * Stats fijos por diseño para que el encuentro sea determinista.
     *
     * Calibración: diseñado para ser desafiante con héroe que tiene
     * arma/escudo de carga ~50-60 y esencias del reino en torno a 80-100.
     * Poder ~650 (por encima del enemigo de 50s pero alcanzable con buen equipo).
     *
     * TODO: afinar estos valores con testeo.
     */
    public function buildGuardian(string $elementSlug): array
    {
        $guardians = [
            'fire'   => ['name' => 'Dragón de Magma',          'poder' => 650, 'hp' => 280, 'ataque' => 58, 'defensa' => 52],
            'water'  => ['name' => 'Leviatán de Aguas Profundas','poder'=> 650, 'hp' => 310, 'ataque' => 54, 'defensa' => 55],
            'earth'  => ['name' => 'Coloso de Piedra',          'poder' => 650, 'hp' => 350, 'ataque' => 50, 'defensa' => 60],
            'air'    => ['name' => 'Tormenta con Voluntad',     'poder' => 650, 'hp' => 240, 'ataque' => 62, 'defensa' => 48],
            'light'  => ['name' => 'Ángel Ciego de Luz Pura',   'poder' => 650, 'hp' => 270, 'ataque' => 60, 'defensa' => 54],
            'shadow' => ['name' => 'Sombra Sin Forma',          'poder' => 650, 'hp' => 260, 'ataque' => 56, 'defensa' => 50],
            'anima'  => ['name' => 'Eco del Ánima',             'poder' => 700, 'hp' => 300, 'ataque' => 60, 'defensa' => 58],
        ];

        $g = $guardians[$elementSlug] ?? $guardians['fire'];

        return [
            'name'        => $g['name'],
            'element'     => $elementSlug,
            'poder'       => $g['poder'],
            'hp'          => $g['hp'],
            'ataque'      => $g['ataque'],
            'defensa'     => $g['defensa'],
            'is_guardian' => true,
        ];
    }

    public function resolve(Hero $hero, array $enemy): array
    {
        $hero->loadMissing('equippedItems.equipment.element');

        $talisman = $hero->talisman;
        $talisman->setRelation('hero', $hero);

        $heroHp    = $hero->hp_actual;
        $enemyHp   = $enemy['hp'];
        $logs      = [];
        $round     = 0;
        $maxRounds = 20;

        $elementoArma    = $hero->elementoArma();
        $elementoEscudo  = $hero->elementoEscudo();
        $elementoEnemigo = $enemy['element'];

        $miPoderVsEnemigo    = $talisman->poderContra($elementoEnemigo);
        $poderEnemigoVsHeroe = $enemy['poder'];
        $chanceHeroeGolpea   = $miPoderVsEnemigo / ($miPoderVsEnemigo + $poderEnemigoVsHeroe);
        $chanceEnemigoGolpea = $poderEnemigoVsHeroe / ($poderEnemigoVsHeroe + $miPoderVsEnemigo);

        $heroAtaque  = $hero->ataque;
        $heroDefensa = $hero->defensa;

        while ($heroHp > 0 && $enemyHp > 0 && $round < $maxRounds) {
            $round++;

            // ── Fase 1: chequeo de golpe (Talismán) ──────────────────────────
            $heroeGolpea   = (mt_rand(0, 1000) / 1000) < $chanceHeroeGolpea;
            $enemigoGolpea = (mt_rand(0, 1000) / 1000) < $chanceEnemigoGolpea;

            // ── Modificadores de Destreza ─────────────────────────────────────
            $dodged    = $enemigoGolpea && ((mt_rand(0, 100) / 100) < $this->dodgeChance($hero->destreza));
            $doubleHit = $heroeGolpea   && ((mt_rand(0, 100) / 100) < $this->doubleHitChance($hero->destreza));
            $critical  = $heroeGolpea   && ((mt_rand(0, 100) / 100) < $this->criticalChance($hero->destreza));

            // ── Varianza ±15% ─────────────────────────────────────────────────
            $variance = 0.85 + (mt_rand(0, 30) / 100);

            // ── Fase 2: daño (equipo elemental) ──────────────────────────────
            $dmgHero = 0;
            if ($heroeGolpea) {
                $multOfensivo = $this->getMultiplier($elementoArma, $elementoEnemigo);
                $dmgHero      = (int)($heroAtaque * $multOfensivo * $variance);
                if ($critical)  $dmgHero  = (int)($dmgHero * 2);
                if ($doubleHit) $dmgHero  = (int)($dmgHero * 1.8);
                $dmgHero = max(1, $dmgHero);
            }

            $dmgReceived = 0;
            if ($enemigoGolpea && !$dodged) {
                $multDefensivo = $this->getMultiplier($elementoEnemigo, $elementoEscudo);
                $dmgReceived   = max(1, (int)($enemy['ataque'] - $heroDefensa * $multDefensivo * $variance));
            }

            $enemyHp -= $dmgHero;
            $heroHp  -= $dmgReceived;

            $logs[] = [
                'round_number'         => $round,
                'hero_damage_dealt'    => $dmgHero,
                'hero_damage_received' => $dmgReceived,
                'hero_dodged'          => $dodged,
                'hero_double_hit'      => $doubleHit,
                'hero_critical'        => $critical,
                'enemy_fled'           => false,
                'narrative_line'       => $this->narrativeLine(
                    $round, $dmgHero, $dmgReceived,
                    $heroeGolpea, $enemigoGolpea,
                    $dodged, $critical, $doubleHit,
                    $enemy['name']
                ),
            ];
        }

        $heroWon = $enemyHp <= 0 || ($heroHp > 0 && $round >= $maxRounds);

        return [
            'hero_won'              => $heroWon,
            'hero_hp_left'          => max(0, $heroHp),
            'rounds'                => $round,
            'logs'                  => $logs,
            'enemy'                 => $enemy,
            'chance_heroe_golpea'   => round($chanceHeroeGolpea * 100, 1),
            'chance_enemigo_golpea' => round($chanceEnemigoGolpea * 100, 1),
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function getMultiplier(string $sourceSlug, string $targetSlug): float
    {
        // Ánima atacando: siempre ×1.0
        if ($sourceSlug === 'anima') return 1.0;

        // No-Ánima atacando a Ánima: ×0.70
        if ($targetSlug === 'anima') return 0.70;

        $relation = ElementRelation::whereHas('sourceElement', fn($q) => $q->where('slug', $sourceSlug))
            ->whereHas('targetElement', fn($q) => $q->where('slug', $targetSlug))
            ->first();

        return $relation ? (float)$relation->multiplier : 1.0;
    }

    /**
     * Chance de esquivar basada en Destreza total (base + equipo piernas).
     * Curva suavizada, tope 45%.
     */
    private function dodgeChance(float $destreza): float
    {
        return min(0.45, $destreza / ($destreza + 20));
    }

    /**
     * Chance de doble golpe: mitad de la curva de esquive.
     */
    private function doubleHitChance(float $destreza): float
    {
        return min(0.30, ($destreza * 0.5) / ($destreza * 0.5 + 20));
    }

    /**
     * Chance de crítico basada en Destreza.
     * Más contenida que el esquive: tope 20%.
     */
    private function criticalChance(float $destreza): float
    {
        return min(0.20, $destreza / ($destreza + 50));
    }

    private function enemyName(string $elementSlug): string
    {
        return match($elementSlug) {
            'fire'   => 'Bestia de Magma',    'water'  => 'Espectro Fluido',
            'earth'  => 'Coloso de Piedra',   'air'    => 'Elemental de Viento',
            'light'  => 'Centinela de Luz',   'shadow' => 'Sombra Sin Forma',
            'anima'  => 'Eco del Buscador',   default  => 'Criatura Desconocida',
        };
    }

    private function narrativeLine(
        int $round, int $dealt, int $received,
        bool $heroeGolpea, bool $enemigoGolpea,
        bool $dodged, bool $critical, bool $double,
        string $enemyName
    ): string {
        $line = "Ronda {$round} — ";

        if (!$heroeGolpea) {
            $line .= "El golpe del Buscador no penetra. ";
        } elseif ($critical) {
            $line .= "¡Golpe crítico! Daño: {$dealt}. ";
        } elseif ($double) {
            $line .= "Doble golpe. Daño total: {$dealt}. ";
        } else {
            $line .= "El Buscador golpea a {$enemyName}. Daño: {$dealt}. ";
        }

        if (!$enemigoGolpea) {
            $line .= "{$enemyName} no logra penetrar las defensas.";
        } elseif ($dodged) {
            $line .= "{$enemyName} ataca pero el Buscador esquiva.";
        } elseif ($received === 0) {
            $line .= "Sin daño recibido.";
        } else {
            $line .= "{$enemyName} responde. Daño recibido: {$received}.";
        }

        return $line;
    }
}