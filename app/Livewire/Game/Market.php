<?php

namespace App\Livewire\Game;

use App\Models\Hero;
use App\Services\MarketService;
use Livewire\Component;

class Market extends Component
{
    public int $heroId;
    public ?Hero $hero = null;

    public array  $marketStock    = [];
    public ?string $marketMessage = null;

    public function mount(int $heroId): void
    {
        $this->heroId = $heroId;
        $this->loadHero();
        $this->marketStock = $this->buildMarketStock();
    }

    public function loadHero(): void
    {
        $this->hero = Hero::with([
            'equippedItems.equipment.element',
        ])->find($this->heroId);
    }

    public function backToHub(): void
    {
        $this->dispatch('phase-changed', phase: 'hub');
    }

    public function buyItem(int $equipmentId, int $carga): void
    {
        $this->loadHero();
        $result = app(MarketService::class)->buy($this->hero, $equipmentId, $carga);
        $this->marketMessage = $result['message'];

        if ($result['ok']) {
            $this->loadHero();
            $this->marketStock = $this->buildMarketStock();
        }
    }

    public function refreshMarket(): void
    {
        $this->marketMessage = null;
        $this->marketStock   = $this->buildMarketStock();
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

    public function render()
    {
        return view('livewire.game.market');
    }
}
