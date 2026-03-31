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

    public array $statsCreacion = [
        'fuerza' => 5, 'resistencia' => 5, 'destreza' => 5,
        'inteligencia' => 5, 'suerte' => 5,
    ];

    private const STAT_PUNTOS_TOTAL = 25;
    private const STAT_MIN = 1;
    private const STAT_MAX = 10;

    public ?int    $expeditionId = null;
    public ?array  $resultado    = null;
    public string  $phase        = 'create';

    public int    $selectedDuration = 10;
    public string $selectedKingdom  = 'fire';
    public int    $secondsLeft      = 0;

    public array  $marketStock    = [];
    public ?string $marketMessage = null;

    public string $simKingdom    = 'fire';
    public int    $simDuration   = 50;
    public int    $simCount      = 20;
    public array  $simResults    = [];
    public bool   $simRunning    = false;

    public const KINGDOMS = [
        'fire'   => ['name' => 'Fuego',  'color' => '#ef4444'],
        'water'  => ['name' => 'Agua',   'color' => '#3b82f6'],
        'earth'  => ['name' => 'Tierra', 'color' => '#92400e'],
        'air'    => ['name' => 'Aire',   'color' => '#7dd3fc'],
        'light'  => ['name' => 'Luz',    'color' => '#fbbf24'],
        'shadow' => ['name' => 'Sombra', 'color' => '#7c3aed'],
        'anima'  => ['name' => 'Ánima',  'color' => '#9ca3af'],
    ];

    public const DURACION_ESENCIA_MINIMA = [
        10 => 0,
        20 => 20,
        30 => 40,
        40 => 60,
        50 => 80,
    ];

    public string  $cheatMessage      = '';
    public array   $cheatStats        = [];
    public array   $cheatEsencias     = [];
    public int     $cheatOro          = 0;
    public string  $cheatEquipElement = 'fire';
    public string  $cheatEquipSlot    = 'arma';
    public int     $cheatEquipCarga   = 50;

    // ─── Mount ───────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $heroId = session('hero_id');

        // Si no hay sesión, buscar héroes de esta IP
        if (!$heroId) {
            $this->phase = 'select';
            return;
        }

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

    public function heroesDeEstaIp(): \Illuminate\Support\Collection
    {
        //return Hero::all();
        return Hero::where('ip_address', request()->ip())
            ->orderByDesc('updated_at')
            ->get();
    }

    public function selectHero(int $heroId): void
    {
        $hero = Hero::where('id', $heroId)
            ->where('ip_address', request()->ip())
            ->first();

        if (!$hero) return;

        session(['hero_id' => $hero->id]);
        $this->heroId = $hero->id;
        $this->hero   = $this->loadHero($hero->id);
        $this->phase  = 'hub';
    }

    public function logout(): void
    {
        session()->forget('hero_id');
        $this->heroId       = null;
        $this->hero         = null;
        $this->expeditionId = null;
        $this->resultado    = null;
        $this->secondsLeft  = 0;
        $this->phase        = 'select';
    }

    // ─── Crear héroe ──────────────────────────────────────────────────────────

    public function subirStat(string $stat): void
    {
        if (!array_key_exists($stat, $this->statsCreacion)) return;
        if ($this->statsCreacion[$stat] >= self::STAT_MAX) return;
        if (array_sum($this->statsCreacion) >= self::STAT_PUNTOS_TOTAL) return;
        $this->statsCreacion[$stat]++;
    }

    public function bajarStat(string $stat): void
    {
        if (!array_key_exists($stat, $this->statsCreacion)) return;
        if ($this->statsCreacion[$stat] <= self::STAT_MIN) return;
        $this->statsCreacion[$stat]--;
    }

    public function createHero(): void
    {
        $hero = Hero::create([
            'name'         => $this->heroName ?: 'El Buscador',
            'ip_address'   => request()->ip(),
            'fuerza'       => $this->statsCreacion['fuerza'],
            'resistencia'  => $this->statsCreacion['resistencia'],
            'destreza'     => $this->statsCreacion['destreza'],
            'inteligencia' => $this->statsCreacion['inteligencia'],
            'suerte'       => $this->statsCreacion['suerte'],
            'oro'          => 50,
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

    /**
     * El Buscador parte solo con arma y escudo de Ánima.
     * Narrativamente: un estudioso que no conoce aún el sistema elemental.
     */
    private function giveStarterEquipment(Hero $hero): void
    {
        foreach (['arma', 'escudo'] as $slot) {
            $piece = Equipment::where('piece_type', $slot)
                ->where('level', 1)
                ->whereHas('element', fn($q) => $q->where('slug', 'anima'))
                ->first();

            if ($piece) {
                HeroEquipment::create([
                    'hero_id'      => $hero->id,
                    'piece_type'   => $slot,
                    'equipment_id' => $piece->id,
                    'carga'        => 10,
                ]);
            }
        }

        // Recalcular HP con el pecho vacío (solo resistencia base por ahora)
        $hero->load('equippedItems.equipment.element');
        $hero->recalcularHP();
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
     * Lanza la misión del guardián de anillo 1.
     * Solo disponible si la esencia farmeada del reino es exactamente MAX_ESENCIA
     * y el héroe no tiene ya el sello de ese reino.
     */
    public function launchGuardian(string $kingdom): void
    {
        $this->hero = $this->loadHero($this->heroId);

        $esencia = $this->hero->talisman->getEsencia($kingdom);
        if ($esencia < Talisman::MAX_ESENCIA) return;
        if ($this->hero->hasSeal($kingdom, 1)) return;

        $service    = app(ExpeditionService::class);
        $expedition = $service->launchGuardian($this->hero, $kingdom);

        $this->selectedKingdom = $kingdom;
        $this->expeditionId    = $expedition->id;
        $this->secondsLeft     = 60;
        $this->phase           = 'waiting';
        $this->resultado       = null;
    }

    public function selectDuration(int $duration): void
    {
        if ($this->duracionDesbloqueada($duration, $this->selectedKingdom)) {
            $this->selectedDuration = $duration;
        }
    }

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

    public function equiparSetup(string $elementSlug): void
    {
        $this->hero = $this->loadHero($this->heroId);
        $slots      = ['casco','pecho','brazos','piernas','escudo','arma','amuleto'];

        foreach ($slots as $slot) {
            // Buscar en inventario una pieza de ese elemento para este slot
            $candidata = $this->hero->inventory
                ->first(fn($i) =>
                    $i->equipment->piece_type === $slot &&
                    $i->equipment->element->slug === $elementSlug
                );

            if (!$candidata) continue;

            // Desequipar lo que hay en el slot (va al inventario)
            $actual = \App\Models\HeroEquipment::where('hero_id', $this->heroId)
                ->where('piece_type', $slot)
                ->first();

            if ($actual) {
                // Si ya es del mismo elemento y mismo item, ignorar
                if ($actual->equipment_id === $candidata->equipment_id) {
                    $candidata->delete();
                    continue;
                }
                $this->addToInventory($this->heroId, $actual->equipment_id, $actual->carga);
                $actual->delete();
            }

            // Equipar la candidata
            \App\Models\HeroEquipment::create([
                'hero_id'      => $this->heroId,
                'piece_type'   => $slot,
                'equipment_id' => $candidata->equipment_id,
                'carga'        => $candidata->carga,
            ]);

            $candidata->delete();
        }

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

    // ─── Cheats ──────────────────────────────────────────────────────────────────
 
    public function goToCheats(): void
    {
        $this->hero = $this->loadHero($this->heroId);
    
        // Inicializar formularios con valores actuales
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
        $this->phase         = 'cheats';
    }
    
    public function cheatSaveStats(): void
    {
        $this->hero = $this->loadHero($this->heroId);
    
        $allowed = ['fuerza', 'resistencia', 'destreza', 'inteligencia', 'suerte'];
        $update  = [];
    
        foreach ($allowed as $stat) {
            $val = (int)($this->cheatStats[$stat] ?? 1);
            $update[$stat] = max(1, min(99, $val));
        }
    
        $this->hero->update($update);
        $this->hero = $this->loadHero($this->heroId);
        $this->hero->recalcularHP();
        $this->hero = $this->loadHero($this->heroId);
    
        $this->cheatMessage = '✓ Stats actualizados.';
    }
    
    public function cheatSaveEsencias(): void
    {
        $this->hero = $this->loadHero($this->heroId);
        $talisman   = $this->hero->talisman;
    
        $update = [];
        foreach (Talisman::ELEMENTOS as $slug) {
            $val = (int)($this->cheatEsencias[$slug] ?? 0);
            $update["esencia_{$slug}"] = max(0, min(9999, $val));
        }
    
        $talisman->update($update);
        $this->hero        = $this->loadHero($this->heroId);
        $this->cheatMessage = '✓ Esencias actualizadas.';
    }
    
    public function cheatSaveOro(): void
    {
        $this->hero = $this->loadHero($this->heroId);
        $this->hero->update(['oro' => max(0, (int)$this->cheatOro)]);
        $this->hero        = $this->loadHero($this->heroId);
        $this->cheatMessage = '✓ Oro actualizado.';
    }
    
    public function cheatToggleSeal(string $elementSlug): void
    {
        $this->hero = $this->loadHero($this->heroId);
    
        $existing = \App\Models\Seal::where('hero_id', $this->heroId)
            ->where('element_slug', $elementSlug)
            ->where('ring', 1)
            ->first();
    
        if ($existing) {
            $existing->delete();
            $this->cheatMessage = "✓ Sello {$elementSlug} eliminado.";
        } else {
            \App\Models\Seal::create([
                'hero_id'      => $this->heroId,
                'element_slug' => $elementSlug,
                'ring'         => 1,
                'obtained_at'  => now(),
            ]);
            $this->cheatMessage = "✓ Sello {$elementSlug} otorgado.";
        }
    
        $this->hero = $this->loadHero($this->heroId);
        $this->hero->recalcularHP();
        $this->hero = $this->loadHero($this->heroId);
    }
    
    public function cheatAddEquip(): void
    {
        $this->hero = $this->loadHero($this->heroId);
    
        $element = \App\Models\Element::where('slug', $this->cheatEquipElement)->first();
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
    
        // Fusión si ya está en inventario
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
    
        $this->hero = $this->loadHero($this->heroId);
    }
    
    public function cheatRemoveEquip(int $inventoryId): void
    {
        Inventory::where('id', $inventoryId)
            ->where('hero_id', $this->heroId)
            ->delete();
    
        $this->hero        = $this->loadHero($this->heroId);
        $this->cheatMessage = '✓ Item eliminado del inventario.';
    }
    
    public function cheatUnequipSlot(string $slot): void
    {
        $slotRow = HeroEquipment::where('hero_id', $this->heroId)
            ->where('piece_type', $slot)
            ->first();
    
        if ($slotRow) {
            $slotRow->delete();
            $this->hero = $this->loadHero($this->heroId);
            $this->hero->recalcularHP();
            $this->hero        = $this->loadHero($this->heroId);
            $this->cheatMessage = "✓ Slot {$slot} vaciado.";
        }
    }
    
    public function cheatRestoreHP(): void
    {
        $this->hero = $this->loadHero($this->heroId);
        $this->hero->update(['hp_actual' => $this->hero->hp_maximo]);
        $this->hero        = $this->loadHero($this->heroId);
        $this->cheatMessage = '✓ HP restaurado al máximo.';
    }

    public function runSimulation(): void
    {
        $this->hero = $this->loadHero($this->heroId);
    
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
            \App\Models\Talisman::NOMBRES[$this->simKingdom] . " {$this->simDuration}s.";
    }

    // ─── Helpers de acceso por esencia ───────────────────────────────────────

    public function duracionDesbloqueada(int $duracion, string $kingdom): bool
    {
        $minima = self::DURACION_ESENCIA_MINIMA[$duracion] ?? 999;
        $actual = $this->hero?->talisman->getEsencia($kingdom) ?? 0;
        return $actual >= $minima;
    }

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

    /**
     * True si el guardián está disponible para un reino:
     * esencia farmeada == MAX y no tiene el sello todavía.
     */
    public function guardianDisponible(string $kingdom): bool
    {
        if (!$this->hero) return false;
        $esencia = $this->hero->talisman->getEsencia($kingdom);
        return $esencia >= Talisman::MAX_ESENCIA && !$this->hero->hasSeal($kingdom, 1);
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
        $service   = app(MarketService::class);

        return collect($service->getStock())
            ->reject(fn($item) => in_array($item['equipment_id'], $purchased))
            ->map(fn($item) => array_merge($item, [
                'precio' => $service->precioParaHeroe($this->hero, $item['carga'])
            ]))
            ->values()
            ->all();
    }

    private function loadHero(int $heroId): Hero
    {
        return Hero::with([
            'talisman',
            'equippedItems.equipment.element',
            'inventory.equipment.element',
            'seals',
        ])->find($heroId);
    }

    public function render()
    {
        return view('livewire.game-core');
    }
}