<?php

namespace App\Livewire\Game;

use App\Models\Hero;
use App\Models\Talisman;
use App\Services\ExpeditionService;
use Livewire\Component;

class Hub extends Component
{
    public int $heroId;
    public ?Hero $hero = null;

    public int    $selectedDuration = 10;
    public string $selectedKingdom  = 'fire';

    public const DURACION_ESENCIA_MINIMA = [
        10 => 0,
        20 => 20,
        30 => 40,
        40 => 60,
        50 => 80,
    ];

    public function mount(int $heroId): void
    {
        $this->heroId = $heroId;
        $this->loadHeroData();
    }

    public function loadHeroData(): void
    {
        $this->hero = Hero::with([
            'talisman',
            'equippedItems.equipment.element',
            'seals',
        ])->find($this->heroId);
    }

    public function launchExpedition(): void
    {
        $this->loadHeroData();

        if (!$this->duracionDesbloqueada($this->selectedDuration, $this->selectedKingdom)) {
            return;
        }

        $service    = app(ExpeditionService::class);
        $expedition = $service->launch($this->hero, $this->selectedDuration, $this->selectedKingdom);

        $this->dispatch('expedition-launched', expeditionId: $expedition->id, kingdom: $this->selectedKingdom);
    }

    public function launchRest(): void
    {
        $this->loadHeroData();
        $service    = app(ExpeditionService::class);
        $expedition = $service->launchRest($this->hero);

        $this->dispatch('expedition-launched', expeditionId: $expedition->id, kingdom: 'fire');
    }

    public function launchGuardian(string $kingdom): void
    {
        $this->loadHeroData();

        $esencia = $this->hero->talisman->getEsencia($kingdom);
        if ($esencia < Talisman::MAX_ESENCIA) return;
        if ($this->hero->hasSeal($kingdom, 1)) return;

        $service    = app(ExpeditionService::class);
        $expedition = $service->launchGuardian($this->hero, $kingdom);

        $this->dispatch('expedition-launched', expeditionId: $expedition->id, kingdom: $kingdom);
    }

    public function selectDuration(int $duration): void
    {
        if ($this->duracionDesbloqueada($duration, $this->selectedKingdom)) {
            $this->selectedDuration = $duration;
        }
    }

    public function updatedSelectedKingdom(): void
    {
        $this->loadHeroData();
        if (!$this->duracionDesbloqueada($this->selectedDuration, $this->selectedKingdom)) {
            $esencia = $this->hero->talisman->getEsencia($this->selectedKingdom);
            $this->selectedDuration = $this->maxDuracionPermitida($esencia);
        }
    }

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

    public function guardianDisponible(string $kingdom): bool
    {
        if (!$this->hero) return false;
        $esencia = $this->hero->talisman->getEsencia($kingdom);
        return $esencia >= Talisman::MAX_ESENCIA && !$this->hero->hasSeal($kingdom, 1);
    }

    public function goTo(string $phase): void
    {
        $this->dispatch('phase-changed', phase: $phase);
    }

    public function logout(): void
    {
        $this->dispatch('logout');
    }

    public function render()
    {
        return view('livewire.game.hub');
    }
}
