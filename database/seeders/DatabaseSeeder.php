<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $elements = [
            ['name' => 'Fuego',  'slug' => 'fire',   'color' => '#ef4444'],
            ['name' => 'Agua',   'slug' => 'water',  'color' => '#3b82f6'],
            ['name' => 'Tierra', 'slug' => 'earth',  'color' => '#92400e'],
            ['name' => 'Aire',   'slug' => 'air',    'color' => '#7dd3fc'],
            ['name' => 'Luz',    'slug' => 'light',  'color' => '#fbbf24'],
            ['name' => 'Sombra', 'slug' => 'shadow', 'color' => '#7c3aed'],
            ['name' => 'Ánima',  'slug' => 'anima',  'color' => '#e5e7eb'],
        ];

        foreach ($elements as $element) {
            \App\Models\Element::create($element);
        }

        // Función helper para obtener id por slug
        $id = fn(string $slug) => \App\Models\Element::where('slug', $slug)->value('id');

        $relations = [
            'fire'   => ['strong' => ['shadow','air'], 'weak' => ['water','earth'], 'neutral' => ['light']],
            'water'  => ['strong' => ['light','fire'],  'weak' => ['shadow','air'], 'neutral' => ['earth']],
            'air'    => ['strong' => ['water','earth'], 'weak' => ['fire','light'], 'neutral' => ['shadow']],
            'earth'  => ['strong' => ['light','fire'],  'weak' => ['air','shadow'], 'neutral' => ['water']],
            'light'  => ['strong' => ['shadow','air'],  'weak' => ['water','earth'],'neutral' => ['fire']],
            'shadow' => ['strong' => ['water','earth'], 'weak' => ['light','fire'], 'neutral' => ['air']],
        ];

        foreach ($relations as $sourceSlug => $groups) {
            $sourceId = $id($sourceSlug);
            foreach ($groups['strong'] as $targetSlug) {
                \App\Models\ElementRelation::create([
                    'source_element_id' => $sourceId,
                    'target_element_id' => $id($targetSlug),
                    'multiplier'        => 1.50,
                ]);
            }
            foreach ($groups['weak'] as $targetSlug) {
                \App\Models\ElementRelation::create([
                    'source_element_id' => $sourceId,
                    'target_element_id' => $id($targetSlug),
                    'multiplier'        => 0.60,
                ]);
            }
            foreach ($groups['neutral'] as $targetSlug) {
                \App\Models\ElementRelation::create([
                    'source_element_id' => $sourceId,
                    'target_element_id' => $id($targetSlug),
                    'multiplier'        => 1.00,
                ]);
            }
        }

        // Ánima: 0.8 contra todos (incluyéndose a sí misma)
        $animaId = $id('anima');
        foreach (\App\Models\Element::all() as $target) {
            \App\Models\ElementRelation::create([
                'source_element_id' => $animaId,
                'target_element_id' => $target->id,
                'multiplier'        => 0.80,
            ]);
        }
        
        // Sembrar equipo
        $this->call(EquipmentSeeder::class);
    }
}