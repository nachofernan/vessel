<?php

namespace App\Livewire;

use App\Models\Hero;
use Livewire\Component;
use Livewire\Attributes\On;

class GameCore extends Component
{
    public ?int   $heroId    = null;
    public string $phase     = 'select';

    public ?int    $expeditionId = null;
    public string  $selectedKingdom  = 'fire';

    public function mount(): void
    {
        $heroId = session('hero_id');

        if (!$heroId) {
            $this->phase = 'select';
            return;
        }

        if ($heroId && $hero = Hero::find($heroId)) {
            $this->heroId = $hero->id;

            $active = $hero->activeExpedition;
            if ($active) {
                $this->expeditionId    = $active->id;
                $this->selectedKingdom = $active->kingdom_slug ?? 'fire';
                $this->phase           = 'waiting';
            } else {
                $this->phase = 'hub';
            }
        } else {
            $this->phase = 'select';
        }
    }

    #[On('hero-selected')]
    public function onHeroSelected(int $heroId): void
    {
        $this->heroId = $heroId;
        $this->phase  = 'hub';
    }

    #[On('logout')]
    public function logout(): void
    {
        session()->forget('hero_id');
        $this->heroId       = null;
        $this->expeditionId = null;
        $this->phase        = 'select';
    }

    #[On('phase-changed')]
    public function setPhase(string $phase): void
    {
        $this->phase = $phase;
    }

    #[On('expedition-launched')]
    public function onExpeditionLaunched(int $expeditionId, string $kingdom): void
    {
        $this->expeditionId    = $expeditionId;
        $this->selectedKingdom = $kingdom;
        $this->phase           = 'waiting';
    }

    public function render()
    {
        return view('livewire.game-core');
    }
}
