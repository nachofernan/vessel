<?php

namespace App\Services;

use App\Models\Element;
use App\Models\Equipment;
use App\Models\Expedition;
use App\Models\Hero;
use App\Models\Inventory;
use App\Models\Seal;

class ExpeditionService
{
    public function __construct(private CombatService $combat) {}

    public function launch(Hero $hero, int $durationSeconds, string $kingdomSlug = 'fire'): Expedition
    {
        $element   = Element::where('slug', $kingdomSlug)->firstOrFail();
        $eventType = $this->sortearEvento($durationSeconds, $hero);

        return Expedition::create([
            'hero_id'          => $hero->id,
            'zone_slug'        => "{$kingdomSlug}_1",
            'kingdom_slug'     => $kingdomSlug,
            'element_id'       => $element->id,
            'duration_seconds' => $durationSeconds,
            'status'           => 'running',
            'event_type'       => $eventType,
            'started_at'       => now(),
            'completes_at'     => now()->addSeconds($durationSeconds),
        ]);
    }

    public function launchRest(Hero $hero): Expedition
    {
        $element = Element::where('slug', 'fire')->firstOrFail();

        return Expedition::create([
            'hero_id'          => $hero->id,
            'zone_slug'        => 'refuge',
            'kingdom_slug'     => 'fire',
            'element_id'       => $element->id,
            'duration_seconds' => 10,
            'status'           => 'running',
            'event_type'       => 'rest',
            'started_at'       => now(),
            'completes_at'     => now()->addSeconds(10),
        ]);
    }

    /**
     * Lanza la misión del guardián de anillo 1 para un reino.
     * Requiere que la esencia farmeada sea >= MAX_ESENCIA (100).
     * Duración fija: 60s.
     */
    public function launchGuardian(Hero $hero, string $kingdomSlug): Expedition
    {
        $element = Element::where('slug', $kingdomSlug)->firstOrFail();

        return Expedition::create([
            'hero_id'          => $hero->id,
            'zone_slug'        => "{$kingdomSlug}_guardian",
            'kingdom_slug'     => $kingdomSlug,
            'element_id'       => $element->id,
            'duration_seconds' => 60,
            'status'           => 'running',
            'event_type'       => 'guardian',
            'started_at'       => now(),
            'completes_at'     => now()->addSeconds(60),
        ]);
    }

    public function resolve(Expedition $expedition): Expedition
    {
        return match($expedition->event_type) {
            'rest'     => $this->resolveRest($expedition),
            'chest'    => $this->resolveChest($expedition),
            'silence'  => $this->resolveSilence($expedition),
            'merchant' => $this->resolveMerchant($expedition),
            'guardian' => $this->resolveGuardian($expedition),
            default    => $this->resolveCombat($expedition),
        };
    }

    // ─── Sorteo de evento ─────────────────────────────────────────────────────

    /**
     * Distribuye los tipos de evento según la duración y la Suerte del héroe.
     * Suerte efectiva inclina la distribución hacia cofre y aleja del silencio.
     * Cada punto de Suerte efectiva suma ~0.4% a la probabilidad de cofre.
     */
    private function sortearEvento(int $durationSeconds, Hero $hero): string
    {
        // Suerte efectiva = suerte base + bonus del amuleto
        $suerte = $hero->suerte;
        foreach ($hero->equippedItems as $slot) {
            if ($slot->piece_type === 'amuleto') {
                $suerte += $slot->statEfectivo();
            }
        }

        // Bonus de cofre por Suerte: hasta ~+10% con suerte 27 (maxeado nivel 1)
        $bonusCofre = (int)min(15, $suerte * 0.4);

        $roll = mt_rand(1, 100);

        return match(true) {
            $roll <= 10                              => 'merchant',
            $roll <= 15 + $bonusCofre               => 'chest',
            $roll <= 50 + $durationSeconds           => 'combat',
            default                                  => 'silence',
        };
    }

    // ─── Resolvers ───────────────────────────────────────────────────────────

    private function resolveRest(Expedition $expedition): Expedition
    {
        $hero       = $expedition->hero;
        $hpRestored = $hero->hp_maximo - $hero->hp_actual;
        $hero->update(['hp_actual' => $hero->hp_maximo]);

        $expedition->update([
            'status'       => 'finished',
            'event_type'   => 'rest',
            'resultado'    => [
                'event'       => 'rest',
                'hp_restored' => $hpRestored,
                'message'     => $hpRestored > 0
                    ? "El Buscador descansa en el Refugio. HP restaurado: {$hpRestored}."
                    : "El Buscador descansa. Ya estaba en plena forma.",
            ],
            'completed_at' => now(),
        ]);

        return $expedition->fresh();
    }

    private function resolveCombat(Expedition $expedition): Expedition
    {
        $hero    = Hero::with(['equippedItems.equipment.element', 'talisman', 'seals'])->find($expedition->hero_id);
        $kingdom = $expedition->kingdom_slug;
        $ring    = 1;

        $enemy  = $this->combat->buildEnemy($kingdom, $ring, $expedition->duration_seconds);
        $result = $this->combat->resolve($hero, $enemy);

        $heroDied = !$result['hero_won'];
        $oro      = $heroDied ? 0 : mt_rand(
            (int)(0.5 * $expedition->duration_seconds),
            (int)(3   * $expedition->duration_seconds)
        );

        $talisman      = $hero->talisman;
        $esenciaGanada = 0;
        $esenciaPerdida = 0;

        if ($heroDied) {
            $esenciaPerdida     = $talisman->getEsencia($kingdom);
            $tieneSellosAnillo1 = $hero->hasSeal($kingdom, 1);
            $talisman->resetEsencia($kingdom, $tieneSellosAnillo1);
        } else {
            $esenciaGanada = $talisman->addEsencia(
                $kingdom,
                mt_rand(
                    (int)(0.2 * $expedition->duration_seconds),
                    (int)(0.5 * $expedition->duration_seconds)
                )
            );
        }

        $lootItem = null;
        if (!$heroDied && mt_rand(1, 100) <= 40) {
            $lootItem = $this->rollLoot($hero->id, $kingdom, $expedition->duration_seconds);
        }

        if ($heroDied) {
            $hero->update(['hp_actual' => 0]);
        } else {
            $hero->update([
                'hp_actual' => $result['hero_hp_left'],
                'oro'       => $hero->oro + $oro,
            ]);
        }

        foreach ($result['logs'] as $log) {
            $expedition->combatLogs()->create($log);
        }

        $expedition->update([
            'status'         => 'finished',
            'event_type'     => 'combat',
            'resultado'      => array_merge($result, [
                'kingdom'            => $kingdom,
                'esencia_ganada'     => $esenciaGanada,
                'esencia_perdida'    => $esenciaPerdida,
                'oro_ganado'         => $oro,
                'loot_item_id'       => $lootItem['id'] ?? null,
                'loot_item_name'     => $lootItem['name'] ?? null,
                'loot_item_slug'     => $lootItem['element_slug'] ?? null,
                'loot_carga_drop'    => $lootItem['carga_drop'] ?? null,
                'loot_carga_antes'   => $lootItem['carga_antes'] ?? null,
                'loot_carga_despues' => $lootItem['carga_despues'] ?? null,
                'loot_carga_maxima'  => $lootItem['carga_maxima'] ?? null,
                'loot_fusion'        => $lootItem['fusion'] ?? false,
            ]),
            'carga_obtenida' => $esenciaGanada,
            'oro_obtenido'   => $oro,
            'hero_died'      => $heroDied,
            'completed_at'   => now(),
        ]);

        return $expedition->fresh();
    }

    /**
     * Resuelve el combate contra el guardián de anillo 1.
     * Stats del guardián son más altos que un enemigo común de 50s pero fijos.
     * Al vencer: crea el sello y fija el piso de esencia en 100.
     * Al perder: esencia a 0 (mismo que derrota normal en anillo 1).
     */
    private function resolveGuardian(Expedition $expedition): Expedition
    {
        $hero    = Hero::with(['equippedItems.equipment.element', 'talisman', 'seals'])->find($expedition->hero_id);
        $kingdom = $expedition->kingdom_slug;

        $guardian = $this->combat->buildGuardian($kingdom);
        $result   = $this->combat->resolve($hero, $guardian);

        $heroDied = !$result['hero_won'];
        $oro      = $heroDied ? 0 : mt_rand(150, 300);

        $talisman       = $hero->talisman;
        $esenciaGanada  = 0;
        $esenciaPerdida = 0;
        $selloObtenido  = false;

        if ($heroDied) {
            $esenciaPerdida = $talisman->getEsencia($kingdom);
            // Al morir intentando el guardián, esencia a 0 (no tiene sello aún)
            $talisman->resetEsencia($kingdom, false);
            $hero->update(['hp_actual' => 0]);
        } else {
            // Victoria: crear sello, fijar esencia en 100 como piso permanente
            Seal::firstOrCreate([
                'hero_id'      => $hero->id,
                'element_slug' => $kingdom,
                'ring'         => 1,
            ], [
                'obtained_at' => now(),
            ]);

            // Asegurar que la esencia queda en exactamente MAX_ESENCIA
            $talisman->update(["esencia_{$kingdom}" => \App\Models\Talisman::MAX_ESENCIA]);

            // Recalcular HP por el nuevo sello (+15 HP)
            $hero->load('seals');
            $hero->recalcularHP();
            $hero->load('equippedItems.equipment.element');
            $hero->update([
                'hp_actual' => min($hero->hp_actual, $hero->hp_maximo),
                'oro'       => $hero->oro + $oro,
            ]);

            $selloObtenido = true;
        }

        foreach ($result['logs'] as $log) {
            $expedition->combatLogs()->create($log);
        }

        $expedition->update([
            'status'         => 'finished',
            'event_type'     => 'guardian',
            'resultado'      => array_merge($result, [
                'kingdom'         => $kingdom,
                'is_guardian'     => true,
                'sello_obtenido'  => $selloObtenido,
                'esencia_ganada'  => $esenciaGanada,
                'esencia_perdida' => $esenciaPerdida,
                'oro_ganado'      => $oro,
                'loot_item_id'    => null,
                'loot_item_name'  => null,
                'loot_fusion'     => false,
            ]),
            'carga_obtenida' => 0,
            'oro_obtenido'   => $oro,
            'hero_died'      => $heroDied,
            'completed_at'   => now(),
        ]);

        return $expedition->fresh();
    }

    private function resolveSilence(Expedition $expedition): Expedition
    {
        $hero    = $expedition->hero;
        $kingdom = $expedition->kingdom_slug;

        $oro           = mt_rand(
            (int)(0.2 * $expedition->duration_seconds),
            (int)(0.5 * $expedition->duration_seconds)
        );
        $esenciaGanada = $hero->talisman->addEsencia(
            $kingdom,
            mt_rand(
                (int)(0.1 * $expedition->duration_seconds),
                (int)(0.3 * $expedition->duration_seconds)
            )
        );

        $hero->update(['oro' => $hero->oro + $oro]);

        $expedition->update([
            'status'         => 'finished',
            'resultado'      => [
                'event'          => 'silence',
                'kingdom'        => $kingdom,
                'oro_ganado'     => $oro,
                'esencia_ganada' => $esenciaGanada,
            ],
            'carga_obtenida' => $esenciaGanada,
            'oro_obtenido'   => $oro,
            'completed_at'   => now(),
        ]);

        return $expedition->fresh();
    }

    private function resolveChest(Expedition $expedition): Expedition
    {
        $hero    = $expedition->hero;
        $kingdom = $expedition->kingdom_slug;

        $oro           = 0;
        $esenciaGanada = 0;
        $lootItem      = null;

        $tocaOro     = mt_rand(1, 100) <= 50;
        $tocaEsencia = mt_rand(1, 100) <= 50;
        $tocaLoot    = mt_rand(1, 100) <= 50;

        if (!$tocaOro && !$tocaEsencia && !$tocaLoot) {
            $forzado     = mt_rand(1, 3);
            $tocaOro     = $forzado === 1;
            $tocaEsencia = $forzado === 2;
            $tocaLoot    = $forzado === 3;
        }

        if ($tocaOro) {
            $oro = mt_rand(
                (int)(1.5 * $expedition->duration_seconds),
                (int)(4   * $expedition->duration_seconds)
            );
            $hero->update(['oro' => $hero->oro + $oro]);
        }

        if ($tocaEsencia) {
            $esenciaGanada = $hero->talisman->addEsencia(
                $kingdom,
                mt_rand(
                    (int)(0.5 * $expedition->duration_seconds),
                    (int)(1.5 * $expedition->duration_seconds)
                )
            );
        }

        if ($tocaLoot) {
            $lootItem = $this->rollLoot($hero->id, $kingdom, $expedition->duration_seconds);
        }

        $expedition->update([
            'status'         => 'finished',
            'resultado'      => [
                'event'              => 'chest',
                'kingdom'            => $kingdom,
                'oro_ganado'         => $oro,
                'esencia_ganada'     => $esenciaGanada,
                'loot_item_id'       => $lootItem['id'] ?? null,
                'loot_item_name'     => $lootItem['name'] ?? null,
                'loot_item_slug'     => $lootItem['element_slug'] ?? null,
                'loot_carga_drop'    => $lootItem['carga_drop'] ?? null,
                'loot_carga_antes'   => $lootItem['carga_antes'] ?? null,
                'loot_carga_despues' => $lootItem['carga_despues'] ?? null,
                'loot_carga_maxima'  => $lootItem['carga_maxima'] ?? null,
                'loot_fusion'        => $lootItem['fusion'] ?? false,
                'toco_oro'           => $tocaOro,
                'toco_esencia'       => $tocaEsencia,
                'toco_loot'          => $tocaLoot,
            ],
            'carga_obtenida' => $esenciaGanada,
            'oro_obtenido'   => $oro,
            'completed_at'   => now(),
        ]);

        return $expedition->fresh();
    }

    private function resolveMerchant(Expedition $expedition): Expedition
    {
        $count   = rand(2, 3);
        $kingdom = $expedition->kingdom_slug;

        $element = Element::where('slug', $kingdom)->first();
        $pieces  = Equipment::with('element')
            ->where('level', 1)
            ->where('element_id', $element?->id)
            ->inRandomOrder()
            ->limit($count)
            ->get();

        // Inteligencia efectiva del héroe para la carga de los ítems
        $hero         = $expedition->hero;
        $inteligencia = $hero->inteligencia;
        foreach ($hero->equippedItems as $slot) {
            if ($slot->piece_type === 'casco') {
                $inteligencia += $slot->statEfectivo();
            }
        }

        $items = $pieces->map(function (Equipment $eq) use ($expedition, $inteligencia) {
            $carga = $this->calcularCargaMercader($expedition->duration_seconds, $inteligencia);
            return [
                'equipment_id'             => $eq->id,
                'name'                     => $eq->name,
                'piece_type'               => $eq->piece_type,
                'element_slug'             => $eq->element->slug,
                'element_name'             => $eq->element->name,
                'element_color'            => $eq->element->color,
                'carga'                    => $carga,
                'carga_maxima'             => $eq->carga_maxima,
                'precio'                   => app(MarketService::class)->precioParaHeroe($expedition->hero, $carga),
                'stat_bonus_efectivo'      => $eq->statEfectivo($carga),
                'alignment_bonus_efectivo' => $eq->alignmentEfectivo($carga),
            ];
        })->values()->all();

        $expedition->update([
            'status'       => 'finished',
            'event_type'   => 'merchant',
            'resultado'    => [
                'event'      => 'merchant',
                'items'      => $items,
                'oro_ganado' => 0,
                'message'    => 'Un mercader ambulante detiene al Buscador. Extiende su mercancía en silencio.',
            ],
            'oro_obtenido' => 0,
            'completed_at' => now(),
        ]);

        return $expedition->fresh();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Carga del drop según duración, con bonus de Inteligencia.
     * Inteligencia sube el piso y el techo del rango.
     * Con INT 5 (base): rango estándar.
     * Con INT 27 (maxeado nivel 1): +7 al piso, +11 al techo.
     */
    private function calcularCargaMercader(int $durationSeconds, int $inteligencia): int
    {
        $bonusPiso  = (int)floor($inteligencia / 4);
        $bonusTecho = (int)floor($inteligencia / 2.5);

        [$piso, $techo] = match(true) {
            $durationSeconds >= 50 => [50, 80],
            $durationSeconds >= 40 => [35, 50],
            $durationSeconds >= 30 => [20, 35],
            $durationSeconds >= 20 => [10, 20],
            default                => [5,  10],
        };

        return rand(
            min($piso  + $bonusPiso,  100),
            min($techo + $bonusTecho, 100)
        );
    }

    private function rollLoot(int $heroId, string $kingdomSlug, int $durationSeconds): ?array
    {
        $element = Element::where('slug', $kingdomSlug)->first();
        if (!$element) return null;

        $piece = Equipment::where('element_id', $element->id)
            ->where('level', 1)
            ->inRandomOrder()
            ->first();

        if (!$piece) return null;

        $cargaDrop = match(true) {
            $durationSeconds >= 50 => rand(50, 80),
            $durationSeconds >= 40 => rand(35, 50),
            $durationSeconds >= 30 => rand(20, 35),
            $durationSeconds >= 20 => rand(10, 20),
            default                => rand(5,  10),
        };

        $fusion       = false;
        $cargaAntes   = 0;
        $cargaDespues = 0;

        $equipado = \App\Models\HeroEquipment::where('hero_id', $heroId)
            ->where('equipment_id', $piece->id)
            ->first();

        if ($equipado) {
            $cargaAntes   = $equipado->carga;
            $cargaDespues = min($piece->carga_maxima, $equipado->carga + $cargaDrop);
            $equipado->update(['carga' => $cargaDespues]);
            $fusion = true;
            if ($piece->piece_type === 'pecho') {
                $hero = Hero::with('equippedItems.equipment.element', 'seals')->find($heroId);
                $hero->recalcularHP();
            }
        } else {
            $existing = Inventory::where('hero_id', $heroId)->where('equipment_id', $piece->id)->first();
            if ($existing) {
                $cargaAntes   = $existing->carga;
                $cargaDespues = min($piece->carga_maxima, $existing->carga + $cargaDrop);
                $existing->update(['carga' => $cargaDespues]);
                $fusion = true;
            } else {
                $cargaDespues = $cargaDrop;
                Inventory::create(['hero_id' => $heroId, 'equipment_id' => $piece->id, 'carga' => $cargaDrop]);
            }
        }

        return [
            'id'            => $piece->id,
            'name'          => $piece->name,
            'element_slug'  => $piece->element->slug ?? $kingdomSlug,
            'carga_drop'    => $cargaDrop,
            'carga_antes'   => $cargaAntes,
            'carga_despues' => $cargaDespues,
            'carga_maxima'  => $piece->carga_maxima,
            'fusion'        => $fusion,
        ];
    }
}