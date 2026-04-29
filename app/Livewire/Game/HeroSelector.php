<?php

namespace App\Livewire\Game;

use App\Models\Equipment;
use App\Models\Hero;
use App\Models\HeroEquipment;
use App\Models\Talisman;
use Livewire\Component;

class HeroSelector extends Component
{
    public string $phase = 'select'; // 'select' o 'create'
    public string $heroName  = '';

    public array $statsCreacion = [
        'fuerza' => 5, 'resistencia' => 5, 'destreza' => 5,
        'inteligencia' => 5, 'suerte' => 5,
    ];

    private const STAT_PUNTOS_TOTAL = 25;
    private const STAT_MIN = 1;
    private const STAT_MAX = 10;

    public function heroesDeEstaIp(): \Illuminate\Support\Collection
    {
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
        $this->dispatch('hero-selected', heroId: $hero->id);
    }

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
        $this->dispatch('hero-selected', heroId: $hero->id);
    }

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

        $hero->load('equippedItems.equipment.element');
        $hero->recalcularHP();
    }

    public function render()
    {
        return view('livewire.game.hero-selector');
    }
}
