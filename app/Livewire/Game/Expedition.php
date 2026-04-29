<?php

namespace App\Livewire\Game;

use App\Models\Expedition as ExpeditionModel;
use App\Models\Hero;
use App\Services\ExpeditionService;
use App\Services\MarketService;
use Livewire\Component;

class Expedition extends Component
{
    public int $heroId;
    public int $expeditionId;
    public string $selectedKingdom;

    public ?Hero $hero = null;
    public ?array $resultado = null;
    public int $secondsLeft = 0;
    public string $phase = 'waiting'; // 'waiting' or 'result'
    public ?string $marketMessage = null;

    public function mount(int $heroId, int $expeditionId, string $kingdom): void
    {
        $this->heroId = $heroId;
        $this->expeditionId = $expeditionId;
        $this->selectedKingdom = $kingdom;

        $this->loadHero();
        $this->tick(); // Initial check
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

    public function tick(): void
    {
        $expedition = ExpeditionModel::find($this->expeditionId);
        if (!$expedition) return;

        $this->secondsLeft = max(0, now()->diffInSeconds($expedition->completes_at, false));

        if ($expedition->isExpired() && $expedition->status === 'running') {
            $resolved        = app(ExpeditionService::class)->resolve($expedition);
            $this->loadHero();
            $this->resultado = $resolved->resultado;
            $this->phase     = 'result';
        } elseif ($expedition->status === 'finished') {
            $this->loadHero();
            $this->resultado = $expedition->resultado;
            $this->phase     = 'result';
        }
    }

    public function buyMerchantItem(int $equipmentId, int $carga): void
    {
        $this->loadHero();
        $result = app(MarketService::class)->buy($this->hero, $equipmentId, $carga);
        $this->marketMessage = $result['message'];

        if ($result['ok']) {
            $items = collect($this->resultado['items'] ?? [])
                ->reject(fn($item) => $item['equipment_id'] === $equipmentId)
                ->values()
                ->all();
            $this->resultado = array_merge($this->resultado, ['items' => $items]);
            $this->loadHero();
        }
    }

    public function backToHub(): void
    {
        $this->dispatch('phase-changed', phase: 'hub');
    }

    public function render()
    {
        return view('livewire.game.expedition');
    }
}
