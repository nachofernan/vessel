<?php

namespace App\Livewire;

use App\Models\Equipment;
use App\Models\Hero;
use App\Models\HeroEquipment;
use App\Models\Inventory;
use App\Models\Talisman;
use App\Services\ExpeditionService;
use App\Services\MarketService;
use Livewire\Component;

class GameCore extends Component
{
    public ?Hero $hero = null;
    public string $heroName  = '';
    public ?int   $heroId    = null;

    public ?int    $expeditionId = null;
    public ?array  $resultado    = null;
    public string  $phase        = 'create';

    public int    $selectedDuration = 10;
    public string $selectedKingdom  = 'fire';
    public int    $secondsLeft      = 0;

    public array  $marketStock   = [];
    public ?string $marketMessage = null;

    public const KINGDOMS = [
        'fire'   => ['name' => 'Fuego',  'color' => '#ef4444'],
        'water'  => ['name' => 'Agua',   'color' => '#3b82f6'],
        'earth'  => ['name' => 'Tierra', 'color' => '#92400e'],
        'air'    => ['name' => 'Aire',   'color' => '#7dd3fc'],
        'light'  => ['name' => 'Luz',    'color' => '#fbbf24'],
        'shadow' => ['name' => 'Sombra', 'color' => '#7c3aed'],
        'anima'  => ['name' => 'Ánima',  'color' => '#9ca3af'],
    ];

    // ─── Mount ───────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $heroId = session('hero_id');

        if ($heroId && $hero = $this->loadHero($heroId)) {
            $this->heroId = $hero->id;
            $this->hero   = $hero;

            $active = $hero->activeExpedition;
            if ($active) {
                $this->expeditionId    = $active->id;
                $this->selectedKingdom = $active->kingdom_slug ?? 'fire';
                $this->secondsLeft     = max(0, now()->diffInSeconds($active->completes_at, false));
                $this->phase           = 'waiting';
            } else {
                $this->phase = 'hub';
            }
        }
    }

    // ─── Crear héroe ──────────────────────────────────────────────────────────

    public function createHero(): void
    {
        $hero = Hero::create([
            'name'         => $this->heroName ?: 'El Buscador',
            'fuerza'       => 5, 'resistencia' => 5, 'destreza'    => 5,
            'inteligencia' => 5, 'suerte'       => 5, 'oro'         => 50,
        ]);

        $hp = Hero::calcularHP($hero->resistencia);
        $hero->update(['hp_actual' => $hp, 'hp_maximo' => $hp]);

        Talisman::create(['hero_id' => $hero->id]);

        $this->giveStarterEquipment($hero);

        session(['hero_id' => $hero->id]);
        $this->heroId = $hero->id;
        $this->hero   = $this->loadHero($hero->id);
        $this->phase  = 'hub';
    }

    private function giveStarterEquipment(Hero $hero): void
    {
        foreach (['casco','pecho','brazos','piernas','escudo','arma','amuleto'] as $slot) {
            $piece = Equipment::where('piece_type', $slot)->where('level', 1)->inRandomOrder()->first();
            if ($piece) {
                HeroEquipment::create([
                    'hero_id'      => $hero->id,
                    'piece_type'   => $slot,
                    'equipment_id' => $piece->id,
                    'carga'        => rand(5, 15),
                ]);
            }
            if ($slot === 'pecho') {
                $hero->recalcularHP();
            }
        }
    }

    // ─── Reiniciar ────────────────────────────────────────────────────────────

    public function resetGame(): void
    {
        Hero::find($this->heroId)?->delete();
        session()->forget('hero_id');

        $this->heroId = null; $this->hero = null; $this->heroName = '';
        $this->expeditionId = null; $this->resultado = null;
        $this->secondsLeft = 0; $this->phase = 'create';
    }

    // ─── Expedición ──────────────────────────────────────────────────────────

    public function launchExpedition(): void
    {
        $this->hero = $this->loadHero($this->heroId);
        $service    = app(ExpeditionService::class);
        $expedition = $service->launch($this->hero, $this->selectedDuration, $this->selectedKingdom);

        $this->expeditionId = $expedition->id;
        $this->secondsLeft  = $this->selectedDuration;
        $this->phase        = 'waiting';
        $this->resultado    = null;
    }

    public function launchRest(): void
    {
        $this->hero = $this->loadHero($this->heroId);
        $service    = app(ExpeditionService::class);
        $expedition = $service->launchRest($this->hero);

        $this->expeditionId = $expedition->id;
        $this->secondsLeft  = 10;
        $this->phase        = 'waiting';
        $this->resultado    = null;
    }

    // ─── Inventario ──────────────────────────────────────────────────────────

    public function goToInventory(): void
    {
        $this->hero  = $this->loadHero($this->heroId);
        $this->phase = 'inventory';
    }

    public function backToHub(): void
    {
        $this->hero      = $this->loadHero($this->heroId);
        $this->phase     = 'hub';
        $this->resultado = null;
    }

    public function equipItem(int $inventoryId): void
    {
        $invRow = Inventory::with('equipment')->find($inventoryId);
        if (!$invRow || $invRow->hero_id !== $this->heroId) return;

        $newPiece  = $invRow->equipment;
        $pieceType = $newPiece->piece_type;

        $currentSlot = HeroEquipment::where('hero_id', $this->heroId)
                                    ->where('piece_type', $pieceType)
                                    ->first();

        if ($currentSlot) {
            // Si es el mismo item → fusión: sumar carga al equipado
            if ($currentSlot->equipment_id === $newPiece->id) {
                $maxCarga   = $newPiece->carga_maxima;
                $nuevaCarga = min($maxCarga, $currentSlot->carga + $invRow->carga);
                $currentSlot->update(['carga' => $nuevaCarga]);
                $invRow->delete();
                $this->hero = $this->loadHero($this->heroId);
                return;
            }
            // Item distinto → desquipar el actual al inventario
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
        $this->hero = $this->loadHero($this->heroId);
        $this->hero->recalcularHP();
        $this->hero = $this->loadHero($this->heroId);
    }

    public function unequipItem(string $pieceType): void
    {
        $slot = HeroEquipment::where('hero_id', $this->heroId)->where('piece_type', $pieceType)->first();
        if (!$slot) return;

        $this->addToInventory($this->heroId, $slot->equipment_id, $slot->carga);
        $slot->delete();
        $this->hero = $this->loadHero($this->heroId);
        $this->hero->recalcularHP();
        $this->hero = $this->loadHero($this->heroId);
    }

    // ─── Mercado ─────────────────────────────────────────────────────────────

    public function goToMarket(): void
    {
        $this->hero        = $this->loadHero($this->heroId);
        $purchased = session('market_purchased', []);
        $this->marketStock = collect(app(MarketService::class)->getStock())
            ->reject(fn($item) => in_array($item['equipment_id'], $purchased))
            ->values()
            ->all();
        $this->marketMessage = null;
        $this->phase       = 'market';
    }

    public function buyItem(int $equipmentId, int $carga): void
    {
        $this->hero   = $this->loadHero($this->heroId);
        $result       = app(MarketService::class)->buy($this->hero, $equipmentId, $carga);
        $this->marketMessage = $result['message'];

        if ($result['ok']) {
            $this->hero = $this->loadHero($this->heroId);
        }
    }

    public function refreshMarket(): void
    {
        $purchased = session('market_purchased', []);
        $this->marketStock = collect(app(MarketService::class)->getStock())
            ->reject(fn($item) => in_array($item['equipment_id'], $purchased))
            ->values()
            ->all();
        $this->marketMessage = null;
    }

    public function buyMerchantItem(int $equipmentId, int $carga): void
    {
        $this->hero   = $this->loadHero($this->heroId);
        $result       = app(\App\Services\MarketService::class)->buy($this->hero, $equipmentId, $carga);
        $this->marketMessage = $result['message'];

        if ($result['ok']) {
            // Elimina el ítem comprado del resultado para que no se pueda comprar dos veces
            $items = collect($this->resultado['items'] ?? [])
                ->reject(fn($item) => $item['equipment_id'] === $equipmentId)
                ->values()
                ->all();
            $this->resultado = array_merge($this->resultado, ['items' => $items]);
            $this->hero = $this->loadHero($this->heroId);
        }
    }

    // ─── Tick ────────────────────────────────────────────────────────────────

    public function tick(): void
    {
        if ($this->phase !== 'waiting') return;

        $expedition = \App\Models\Expedition::find($this->expeditionId);
        if (!$expedition) return;

        $this->secondsLeft = max(0, now()->diffInSeconds($expedition->completes_at, false));

        if ($expedition->isExpired() && $expedition->status === 'running') {
            $resolved        = app(ExpeditionService::class)->resolve($expedition);
            $this->hero      = $this->loadHero($this->heroId);
            $this->resultado = $resolved->resultado;
            $this->phase     = 'result';
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function addToInventory(int $heroId, int $equipmentId, int $carga): void
    {
        $eq       = Equipment::find($equipmentId);
        $existing = Inventory::where('hero_id', $heroId)->where('equipment_id', $equipmentId)->first();

        if ($existing) {
            $nuevaCarga = min($eq->carga_maxima, $existing->carga + $carga);
            $existing->update(['carga' => $nuevaCarga]);
        } else {
            Inventory::create(['hero_id' => $heroId, 'equipment_id' => $equipmentId, 'carga' => $carga]);
        }
    }

    private function loadHero(int $heroId): Hero
    {
        return Hero::with(['talisman', 'equippedItems.equipment.element', 'inventory.equipment.element'])->find($heroId);
    }

    public function render()
    {
        return view('livewire.game-core');
    }
}