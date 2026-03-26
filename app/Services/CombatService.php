<?php

namespace App\Services;

use App\Models\ElementRelation;
use App\Models\Hero;

class CombatService
{
    /**
     * Construye el array del enemigo.
     * El poder escala linealmente dentro del anillo según la duración de la expedición.
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

        $chanceHeroeGolpea   = $talisman->chanceDeGolpe($enemy['poder'], $elementoEnemigo);
        $poderEnemigoVsHeroe = $enemy['poder'];
        $miPoderVsEnemigo    = $talisman->poderContra($elementoEnemigo);
        $chanceEnemigoGolpea = $poderEnemigoVsHeroe / ($poderEnemigoVsHeroe + $miPoderVsEnemigo);

        $heroAtaque  = $hero->ataque;
        $heroDefensa = $hero->defensa;

        while ($heroHp > 0 && $enemyHp > 0 && $round < $maxRounds) {
            $round++;

            // ── Fase 1: chequeo de golpe (Talismán) ──────────────────────────
            $heroeGolpea   = (mt_rand(0, 1000) / 1000) < $chanceHeroeGolpea;
            $enemigoGolpea = (mt_rand(0, 1000) / 1000) < $chanceEnemigoGolpea;

            // ── Modificadores de Destreza y Suerte ───────────────────────────
            $dodged    = $enemigoGolpea && ((mt_rand(0, 100) / 100) < $this->dodgeChance($hero->destreza));
            $doubleHit = $heroeGolpea   && ((mt_rand(0, 100) / 100) < $this->dodgeChance($hero->destreza * 0.5));
            $critical  = $heroeGolpea   && ((mt_rand(0, 100) / 100) < ($hero->suerte / 200));

            // ── Varianza ±15% ────────────────────────────────────────────────
            $variance = 0.85 + (mt_rand(0, 30) / 100);

            // ── Fase 2: daño (equipo elemental) ──────────────────────────────
            $dmgHero = 0;
            if ($heroeGolpea) {
                $multOfensivo = $this->getMultiplier($elementoArma, $elementoEnemigo);
                $dmgHero      = (int)($heroAtaque * $multOfensivo * $variance);
                if ($critical)  $dmgHero *= 2;
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

        // Empate (ambos a 0 en la misma ronda, o límite de rondas con ambos en pie):
        // se considera victoria del héroe. Si llegó al límite de rondas con el enemigo
        // aún vivo pero el héroe también vivo, también es victoria por supervivencia.
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
        if ($sourceSlug === 'anima') return 1.25;

        $relation = ElementRelation::whereHas('sourceElement', fn($q) => $q->where('slug', $sourceSlug))
            ->whereHas('targetElement', fn($q) => $q->where('slug', $targetSlug))
            ->first();

        return $relation ? (float)$relation->multiplier : 1.0;
    }

    private function dodgeChance(float $destreza): float
    {
        return min(0.45, $destreza / ($destreza + 20));
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