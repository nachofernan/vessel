<?php

namespace App\Livewire\Game;

use App\Models\Element;
use App\Models\Equipment;
use App\Models\Hero;
use App\Models\HeroEquipment;
use App\Models\Inventory;
use App\Models\Seal;
use App\Models\Talisman;
use Livewire\Component;

class Cheats extends Component
{
    public int $heroId;
    public ?Hero $hero = null;

    public string $cheatMessage      = '';
    public array   $cheatStats        = [];
    public array   $cheatEsencias     = [];
    public int     $cheatOro          = 0;
    public string  $cheatEquipElement = 'fire';
    public string  $cheatEquipSlot    = 'arma';
    public int     $cheatEquipCarga   = 50;

    public string $simKingdom    = 'fire';
    public int    $simDuration   = 50;
    public int    $simCount      = 20;
    public array  $simResults    = [];
    public bool   $simRunning    = false;

    public function mount(int $heroId): void
    {
        $this->heroId = $heroId;
        $this->initCheats();
    }

    public function initCheats(): void
    {
        $this->hero = Hero::with(['talisman', 'equippedItems.equipment.element', 'inventory.equipment.element', 'seals'])->find($this->heroId);

        $this->cheatStats = [
            'fuerza'       => $this->hero->fuerza,
            'resistencia'  => $this->hero->resistencia,
            'destreza'     => $this->hero->destreza,
            'inteligencia' => $this->hero->inteligencia,
            'suerte'       => $this->hero->suerte,
        ];

        $this->cheatEsencias = $this->hero->talisman->todasLasEsencias();
        $this->cheatOro      = $this->hero->oro;
        $this->cheatMessage  = '';
    }

    public function backToHub(): void
    {
        $this->dispatch('phase-changed', phase: 'hub');
    }

    public function cheatSaveStats(): void
    {
        $allowed = ['fuerza', 'resistencia', 'destreza', 'inteligencia', 'suerte'];
        $update  = [];

        foreach ($allowed as $stat) {
            $val = (int)($this->cheatStats[$stat] ?? 1);
            $update[$stat] = max(1, min(99, $val));
        }

        $this->hero->update($update);
        $this->hero->recalcularHP();
        $this->initCheats();
        $this->cheatMessage = '✓ Stats actualizados.';
    }

    public function cheatSaveEsencias(): void
    {
        $talisman = $this->hero->talisman;
        $update   = [];
        foreach (Talisman::ELEMENTOS as $slug) {
            $val = (int)($this->cheatEsencias[$slug] ?? 0);
            $update["esencia_{$slug}"] = max(0, min(9999, $val));
        }

        $talisman->update($update);
        $this->initCheats();
        $this->cheatMessage = '✓ Esencias actualizadas.';
    }

    public function cheatSaveOro(): void
    {
        $this->hero->update(['oro' => max(0, (int)$this->cheatOro)]);
        $this->initCheats();
        $this->cheatMessage = '✓ Oro actualizado.';
    }

    public function cheatToggleSeal(string $elementSlug): void
    {
        $existing = Seal::where('hero_id', $this->heroId)
            ->where('element_slug', $elementSlug)
            ->where('ring', 1)
            ->first();

        if ($existing) {
            $existing->delete();
            $this->cheatMessage = "✓ Sello {$elementSlug} eliminado.";
        } else {
            Seal::create([
                'hero_id'      => $this->heroId,
                'element_slug' => $elementSlug,
                'ring'         => 1,
                'obtained_at'  => now(),
            ]);
            $this->cheatMessage = "✓ Sello {$elementSlug} otorgado.";
        }

        $this->hero->recalcularHP();
        $this->initCheats();
    }

    public function cheatAddEquip(): void
    {
        $element = Element::where('slug', $this->cheatEquipElement)->first();
        if (!$element) {
            $this->cheatMessage = '✗ Elemento no encontrado.';
            return;
        }

        $piece = Equipment::where('piece_type', $this->cheatEquipSlot)
            ->where('element_id', $element->id)
            ->where('level', 1)
            ->first();

        if (!$piece) {
            $this->cheatMessage = '✗ Pieza no encontrada en el catálogo.';
            return;
        }

        $carga = max(1, min((int)$this->cheatEquipCarga, $piece->carga_maxima));

        $existing = Inventory::where('hero_id', $this->heroId)
            ->where('equipment_id', $piece->id)
            ->first();

        if ($existing) {
            $nueva = min($piece->carga_maxima, $existing->carga + $carga);
            $existing->update(['carga' => $nueva]);
            $this->cheatMessage = "✓ Fusión en inventario. Carga: {$nueva}/{$piece->carga_maxima}.";
        } else {
            Inventory::create([
                'hero_id'      => $this->heroId,
                'equipment_id' => $piece->id,
                'carga'        => $carga,
            ]);
            $this->cheatMessage = "✓ {$piece->name} agregado al inventario (carga {$carga}).";
        }

        $this->initCheats();
    }

    public function cheatRemoveEquip(int $inventoryId): void
    {
        Inventory::where('id', $inventoryId)
            ->where('hero_id', $this->heroId)
            ->delete();

        $this->initCheats();
        $this->cheatMessage = '✓ Item eliminado del inventario.';
    }

    public function cheatUnequipSlot(string $slot): void
    {
        $slotRow = HeroEquipment::where('hero_id', $this->heroId)
            ->where('piece_type', $slot)
            ->first();

        if ($slotRow) {
            $slotRow->delete();
            $this->hero->recalcularHP();
            $this->initCheats();
            $this->cheatMessage = "✓ Slot {$slot} vaciado.";
        }
    }

    public function cheatRestoreHP(): void
    {
        $this->hero->update(['hp_actual' => $this->hero->hp_maximo]);
        $this->initCheats();
        $this->cheatMessage = '✓ HP restaurado al máximo.';
    }

    public function runSimulation(): void
    {
        $combat  = app(\App\Services\CombatService::class);
        $count   = max(1, min(200, (int)$this->simCount));
        $results = [];

        for ($i = 0; $i < $count; $i++) {
            $enemy  = $combat->buildEnemy($this->simKingdom, 1, $this->simDuration);
            $result = $combat->resolve($this->hero, $enemy);

            $results[] = [
                'n'              => $i + 1,
                'outcome'        => $result['outcome'],
                'rounds'         => $result['rounds'],
                'hero_hp_left'   => $result['hero_hp_left'],
                'enemy_hp_ini'   => $enemy['hp'],
                'enemy_poder'    => $enemy['poder'],
                'enemy_ataque'   => $enemy['ataque'],
                'enemy_defensa'  => $enemy['defensa'],
                'chance_h'       => $result['chance_heroe_golpea'],
                'chance_e'       => $result['chance_enemigo_golpea'],
            ];
        }

        $this->simResults  = $results;
        $this->simRunning  = false;
        $this->cheatMessage = "✓ Simulación completada: {$count} batallas vs " .
            Talisman::NOMBRES[$this->simKingdom] . " {$this->simDuration}s.";
    }

    public function render()
    {
        return view('livewire.game.cheats');
    }
}
