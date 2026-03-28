<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

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

        $id = fn(string $slug) => \App\Models\Element::where('slug', $slug)->value('id');

        $relations = [
            'fire'   => ['strong' => ['shadow','air'], 'weak' => ['water','earth'], 'neutral' => ['light']],
            'water'  => ['strong' => ['light','fire'],  'weak' => ['shadow','air'], 'neutral' => ['earth']],
            'air'    => ['strong' => ['water','earth'], 'weak' => ['fire','light'], 'neutral' => ['shadow']],
            'earth'  => ['strong' => ['light','fire'],  'weak' => ['air','shadow'], 'neutral' => ['water']],
            'light'  => ['strong' => ['shadow','air'],  'weak' => ['water','earth'],'neutral' => ['fire']],
            'shadow' => ['strong' => ['water','earth'], 'weak' => ['light','fire'], 'neutral' => ['air']],
        ];

        // Elementos clásicos entre sí
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

        // Elementos clásicos atacando a Ánima: ×0.70 (Ánima es dura de penetrar)
        $animaId = $id('anima');
        foreach (['fire','water','earth','air','light','shadow'] as $slug) {
            \App\Models\ElementRelation::create([
                'source_element_id' => $id($slug),
                'target_element_id' => $animaId,
                'multiplier'        => 0.70,
            ]);
            // Elemento clásico atacado por Ánima: ×1.0 (Ánima es neutral ofensivamente)
            \App\Models\ElementRelation::create([
                'source_element_id' => $animaId,
                'target_element_id' => $id($slug),
                'multiplier'        => 1.00,
            ]);
        }

        // Ánima vs Ánima: ×1.0 en ambas direcciones
        \App\Models\ElementRelation::create([
            'source_element_id' => $animaId,
            'target_element_id' => $animaId,
            'multiplier'        => 1.00,
        ]);

        // Elementos clásicos entre sí vs Ánima como escudo: ya cubierto arriba.
        // Falta: elementos clásicos atacando a otros clásicos, Ánima ya no tiene
        // relación "clásico atacando a clásico" — eso está en el loop de arriba.

        $this->call(EquipmentSeeder::class);
    }
}