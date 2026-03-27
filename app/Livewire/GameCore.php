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

    public array  $marketStock    = [];
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

    /**
     * Esencia farmeada mínima requerida para cada duración (anillo 1).
     * Fórmula: (duracion - 10) * 2
     * 10s → 0  ·  20s → 20  ·  30s → 40  ·  40s → 60  ·  50s → 80
     * Guardián (60s) requiere 100 — se implementa por separado.
     */
    public const DURACION_ESENCIA_MINIMA = [
        10 => 0,
        20 => 20,
        30 => 40,
        40 => 60,
        50 => 80,
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

        // Validación en servidor: defensa en profundidad por si el blade es bypasseado
        if (!$this->duracionDesbloqueada($this->selectedDuration, $this->selectedKingdom)) {
            return;
        }

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

    /**
     * Seleccionar duración desde el blade. Solo acepta si está desbloqueada.
     */
    public function selectDuration(int $duration): void
    {
        if ($this->duracionDesbloqueada($duration, $this->selectedKingdom)) {
            $this->selectedDuration = $duration;
        }
    }

    /**
     * Al cambiar de reino, ajustar la duración seleccionada si ya no está disponible.
     */
    public function updatedSelectedKingdom(): void
    {
        $this->hero = $this->loadHero($this->heroId);
        if (!$this->duracionDesbloqueada($this->selectedDuration, $this->selectedKingdom)) {
            $esencia = $this->hero->talisman->getEsencia($this->selectedKingdom);
            $this->selectedDuration = $this->maxDuracionPermitida($esencia);
        }
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
            if ($currentSlot->equipment_id === $newPiece->id) {
                $maxCarga   = $newPiece->carga_maxima;
                $nuevaCarga = min($maxCarga, $currentSlot->carga + $invRow->carga);
                $currentSlot->update(['carga' => $nuevaCarga]);
                $invRow->delete();
                if ($pieceType === 'pecho') {
                    $this->hero = $this->loadHero($this->heroId);
                    $this->hero->recalcularHP();
                }
                $this->hero = $this->loadHero($this->heroId);
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
        $this->hero          = $this->loadHero($this->heroId);
        $this->marketMessage = null;
        $this->marketStock   = $this->buildMarketStock();
        $this->phase         = 'market';
    }

    public function buyItem(int $equipmentId, int $carga): void
    {
        $this->hero   = $this->loadHero($this->heroId);
        $result       = app(MarketService::class)->buy($this->hero, $equipmentId, $carga);
        $this->marketMessage = $result['message'];

        if ($result['ok']) {
            $this->hero        = $this->loadHero($this->heroId);
            $this->marketStock = $this->buildMarketStock();
        }
    }

    public function refreshMarket(): void
    {
        $this->marketMessage = null;
        $this->marketStock   = $this->buildMarketStock();
    }

    public function buyMerchantItem(int $equipmentId, int $carga): void
    {
        $this->hero   = $this->loadHero($this->heroId);
        $result       = app(MarketService::class)->buy($this->hero, $equipmentId, $carga);
        $this->marketMessage = $result['message'];

        if ($result['ok']) {
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

    // ─── Helpers de acceso por esencia ───────────────────────────────────────

    /**
     * True si la esencia farmeada del reino supera el mínimo requerido para esa duración.
     */
    public function duracionDesbloqueada(int $duracion, string $kingdom): bool
    {
        $minima = self::DURACION_ESENCIA_MINIMA[$duracion] ?? 999;
        $actual = $this->hero?->talisman->getEsencia($kingdom) ?? 0;
        return $actual >= $minima;
    }

    /**
     * Duración máxima disponible para una cantidad de esencia dada.
     */
    public function maxDuracionPermitida(int $esencia): int
    {
        $max = 10;
        foreach (self::DURACION_ESENCIA_MINIMA as $dur => $min) {
            if ($esencia >= $min) {
                $max = $dur;
            }
        }
        return $max;
    }

    /**
     * Array de todas las duraciones con estado de acceso para el reino seleccionado.
     * Usado en el blade para renderizar los botones con bloqueo visual.
     */
    public function duracionesParaKingdom(string $kingdom): array
    {
        $esencia = $this->hero?->talisman->getEsencia($kingdom) ?? 0;
        $result  = [];
        foreach (self::DURACION_ESENCIA_MINIMA as $dur => $min) {
            $result[] = [
                'duracion'          => $dur,
                'desbloqueada'      => $esencia >= $min,
                'esencia_requerida' => $min,
            ];
        }
        return $result;
    }

    // ─── Helpers internos ────────────────────────────────────────────────────

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

    private function buildMarketStock(): array
    {
        $purchased = session('market_purchased', []);
        return collect(app(MarketService::class)->getStock())
            ->reject(fn($item) => in_array($item['equipment_id'], $purchased))
            ->values()
            ->all();
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