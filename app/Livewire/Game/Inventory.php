<?php

namespace App\Livewire\Game;

use App\Models\Equipment;
use App\Models\Hero;
use App\Models\HeroEquipment;
use App\Models\Inventory as InventoryModel;
use Livewire\Component;

class Inventory extends Component
{
    public int $heroId;
    public ?Hero $hero = null;

    public function mount(int $heroId): void
    {
        $this->heroId = $heroId;
        $this->loadHero();
    }

    public function loadHero(): void
    {
        $this->hero = Hero::with([
            'talisman',
            'equippedItems.equipment.element',
            'inventory.equipment.element',
            'seals',
        ])->find($this->heroId);
    }

    public function backToHub(): void
    {
        $this->dispatch('phase-changed', phase: 'hub');
    }

    public function equipItem(int $inventoryId): void
    {
        $invRow = InventoryModel::with('equipment')->find($inventoryId);
        if (!$invRow || $invRow->hero_id !== $this->heroId) return;

        $newPiece  = $invRow->equipment;
        $pieceType = $newPiece->piece_type;

        $currentSlot = HeroEquipment::where('hero_id', $this->heroId)
                                    ->where('piece_type', $pieceType)
                                    ->first();

        if ($currentSlot) {
            if ($currentSlot->equipment_id === $newPiece->id) {
                $maxCarga   = $newPiece->carga_maxima;
                $nuevaCarga = min($maxCarga, $currentSlot->carga + $invRow->carga);
                $currentSlot->update(['carga' => $nuevaCarga]);
                $invRow->delete();
                if ($pieceType === 'pecho') {
                    $this->loadHero();
                    $this->hero->recalcularHP();
                }
                $this->loadHero();
                return;
            }
            $this->addToInventory($this->heroId, $currentSlot->equipment_id, $currentSlot->carga);
            $currentSlot->delete();
        }

        HeroEquipment::create([
            'hero_id'      => $this->heroId,
            'piece_type'   => $pieceType,
            'equipment_id' => $newPiece->id,
            'carga'        => $invRow->carga,
        ]);

        $invRow->delete();
        $this->loadHero();
        $this->hero->recalcularHP();
        $this->loadHero();
    }

    public function unequipItem(string $pieceType): void
    {
        $slot = HeroEquipment::where('hero_id', $this->heroId)->where('piece_type', $pieceType)->first();
        if (!$slot) return;

        $this->addToInventory($this->heroId, $slot->equipment_id, $slot->carga);
        $slot->delete();
        $this->loadHero();
        $this->hero->recalcularHP();
        $this->loadHero();
    }

    public function equiparSetup(string $elementSlug): void
    {
        $this->loadHero();
        $slots = ['casco','pecho','brazos','piernas','escudo','arma','amuleto'];

        foreach ($slots as $slot) {
            $candidata = $this->hero->inventory
                ->first(fn($i) =>
                    $i->equipment->piece_type === $slot &&
                    $i->equipment->element->slug === $elementSlug
                );

            if (!$candidata) continue;

            $actual = HeroEquipment::where('hero_id', $this->heroId)
                ->where('piece_type', $slot)
                ->first();

            if ($actual) {
                if ($actual->equipment_id === $candidata->equipment_id) {
                    $candidata->delete();
                    continue;
                }
                $this->addToInventory($this->heroId, $actual->equipment_id, $actual->carga);
                $actual->delete();
            }

            HeroEquipment::create([
                'hero_id'      => $this->heroId,
                'piece_type'   => $slot,
                'equipment_id' => $candidata->equipment_id,
                'carga'        => $candidata->carga,
            ]);

            $candidata->delete();
        }

        $this->loadHero();
        $this->hero->recalcularHP();
        $this->loadHero();
    }

    private function addToInventory(int $heroId, int $equipmentId, int $carga): void
    {
        $eq       = Equipment::find($equipmentId);
        $existing = InventoryModel::where('hero_id', $heroId)->where('equipment_id', $equipmentId)->first();

        if ($existing) {
            $nuevaCarga = min($eq->carga_maxima, $existing->carga + $carga);
            $existing->update(['carga' => $nuevaCarga]);
        } else {
            InventoryModel::create(['hero_id' => $heroId, 'equipment_id' => $equipmentId, 'carga' => $carga]);
        }
    }

    public function render()
    {
        return view('livewire.game.inventory');
    }
}
