<?php

namespace App\Services;

use App\Models\Element;
use App\Models\Equipment;
use App\Models\Expedition;
use App\Models\Hero;
use App\Models\Inventory;

class ExpeditionService
{
    public function __construct(private CombatService $combat) {}

    public function launch(Hero $hero, int $durationSeconds, string $kingdomSlug = 'fire'): Expedition
    {
        $element   = Element::where('slug', $kingdomSlug)->firstOrFail();
        $eventType = $this->sortearEvento();

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

    public function resolve(Expedition $expedition): Expedition
    {
        return match($expedition->event_type) {
            'rest'    => $this->resolveRest($expedition),
            'chest'   => $this->resolveChest($expedition),
            'silence' => $this->resolveSilence($expedition),
            default   => $this->resolveCombat($expedition),
        };
    }

    private function sortearEvento(): string
    {
        $roll = mt_rand(1, 100);
        return match(true) {
            $roll <= 30 => 'combat',
            $roll <= 70 => 'silence',
            default     => 'chest',
        };
    }

    private function resolveRest(Expedition $expedition): Expedition
    {
        $hero = $expedition->hero;
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
        $hero    = Hero::with(['equippedItems.equipment.element', 'talisman'])->find($expedition->hero_id);
        $kingdom = $expedition->kingdom_slug;
        $ring    = 1; // por ahora siempre anillo 1

        // El poder del enemigo escala con la duración elegida por el jugador
        $enemy  = $this->combat->buildEnemy($kingdom, $ring, $expedition->duration_seconds);
        $result = $this->combat->resolve($hero, $enemy);

        $heroDied = !$result['hero_won'];
        $oro      = $heroDied ? mt_rand(2, 8) : mt_rand(10, 30);

        $talisman       = $hero->talisman;
        $esenciaGanada  = 0;
        $esenciaPerdida = 0;

        if ($heroDied) {
            $esenciaPerdida = $talisman->getEsencia($kingdom);
            $talisman->resetEsencia($kingdom);
        } else {
            $esenciaGanada = $talisman->addEsencia($kingdom, mt_rand(15, 25));
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
                'kingdom'         => $kingdom,
                'esencia_ganada'  => $esenciaGanada,
                'esencia_perdida' => $esenciaPerdida,
                'oro_ganado'      => $oro,
                'loot_item_id'    => $lootItem['id'] ?? null,
                'loot_item_name'  => $lootItem['name'] ?? null,
                'loot_item_slug'  => $lootItem['element_slug'] ?? null,
                'loot_carga_drop' => $lootItem['carga_drop'] ?? null,
                'loot_carga_antes'=> $lootItem['carga_antes'] ?? null,
                'loot_carga_despues'=> $lootItem['carga_despues'] ?? null,
                'loot_carga_maxima' => $lootItem['carga_maxima'] ?? null,
                'loot_fusion'     => $lootItem['fusion'] ?? false,
            ]),
            'carga_obtenida' => $esenciaGanada,
            'oro_obtenido'   => $oro,
            'hero_died'      => $heroDied,
            'completed_at'   => now(),
        ]);

        return $expedition->fresh();
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
            $durationSeconds >= 50 => 80,
            $durationSeconds >= 40 => 40,
            $durationSeconds >= 30 => 20,
            $durationSeconds >= 20 => 10,
            default                => 5,
        };

        $fusion  = false;
        $cargaAntes = 0;
        $cargaDespues = 0;

        // ¿Ya está equipado?
        $equipado = \App\Models\HeroEquipment::where('hero_id', $heroId)
            ->where('equipment_id', $piece->id)
            ->first();

        if ($equipado) {
            $cargaAntes   = $equipado->carga;
            $cargaDespues = min($piece->carga_maxima, $equipado->carga + $cargaDrop);
            $equipado->update(['carga' => $cargaDespues]);
            $fusion = true;
        } else {
            // ¿Está en inventario?
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
            'id'           => $piece->id,
            'name'         => $piece->name,
            'element_slug' => $piece->element->slug ?? $kingdomSlug,
            'carga_drop'   => $cargaDrop,
            'carga_antes'  => $cargaAntes,
            'carga_despues'=> $cargaDespues,
            'carga_maxima' => $piece->carga_maxima,
            'fusion'       => $fusion,
        ];
    }

    private function resolveSilence(Expedition $expedition): Expedition
    {
        $hero    = $expedition->hero;
        $kingdom = $expedition->kingdom_slug;

        $oro           = mt_rand(2, 6);
        $esenciaGanada = $hero->talisman->addEsencia($kingdom, mt_rand(3, 8));

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

        // Tres tiros independientes al 50%
        $tocaOro      = mt_rand(1, 100) <= 50;
        $tocaEsencia  = mt_rand(1, 100) <= 50;
        $tocaLoot     = mt_rand(1, 100) <= 50;

        // Si ninguno tocó, forzar uno al azar
        if (!$tocaOro && !$tocaEsencia && !$tocaLoot) {
            $forzado = mt_rand(1, 3);
            $tocaOro     = $forzado === 1;
            $tocaEsencia = $forzado === 2;
            $tocaLoot    = $forzado === 3;
        }

        if ($tocaOro) {
            $oro = mt_rand(15, 50);
            $hero->update(['oro' => $hero->oro + $oro]);
        }

        if ($tocaEsencia) {
            $esenciaGanada = $hero->talisman->addEsencia($kingdom, mt_rand(10, 30));
        }

        
        if ($tocaLoot) {
            $lootItem = $this->rollLoot($hero->id, $kingdom, $expedition->duration_seconds);
        }

        $expedition->update([
            'status'         => 'finished',
            'resultado'      => [
                'event'          => 'chest',
                'kingdom'        => $kingdom,
                'oro_ganado'     => $oro,
                'esencia_ganada' => $esenciaGanada,
                'loot_item_id'    => $lootItem['id'] ?? null,
                'loot_item_name'  => $lootItem['name'] ?? null,
                'loot_item_slug'  => $lootItem['element_slug'] ?? null,
                'loot_carga_drop' => $lootItem['carga_drop'] ?? null,
                'loot_carga_antes'=> $lootItem['carga_antes'] ?? null,
                'loot_carga_despues'=> $lootItem['carga_despues'] ?? null,
                'loot_carga_maxima' => $lootItem['carga_maxima'] ?? null,
                'loot_fusion'     => $lootItem['fusion'] ?? false,
                'toco_oro'       => $tocaOro,
                'toco_esencia'   => $tocaEsencia,
                'toco_loot'      => $tocaLoot,
            ],
            'carga_obtenida' => $esenciaGanada,
            'oro_obtenido'   => $oro,
            'completed_at'   => now(),
        ]);

        return $expedition->fresh();
    }
}