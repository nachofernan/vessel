<?php

namespace Database\Seeders;

use App\Models\Element;
use App\Models\Equipment;
use Illuminate\Database\Seeder;

class EquipmentSeeder extends Seeder
{
    /**
     * Genera el catálogo completo de nivel 1:
     * 7 tipos de pieza × 7 elementos = 49 items.
     *
     * Nivel 1: stat_bonus = 2, alignment_bonus = 10, valor_base = 100
     */
    public function run(): void
    {
        $pieces = [
            'casco'   => ['stat' => 'INT', 'prefix' => ['Capucha', 'Yelmo', 'Casco']],
            'pecho'   => ['stat' => 'RES', 'prefix' => ['Túnica', 'Peto', 'Coraza']],
            'brazos'  => ['stat' => 'FUE', 'prefix' => ['Guanteletes', 'Brazales', 'Muñequeras']],
            'piernas' => ['stat' => 'DES', 'prefix' => ['Grebas', 'Polainas', 'Calzas']],
            'escudo'  => ['stat' => 'DEF', 'prefix' => ['Rodela', 'Escudo', 'Égida']],
            'arma'    => ['stat' => 'ATQ', 'prefix' => ['Espada', 'Bastón', 'Daga']],
            'amuleto' => ['stat' => 'SUE', 'prefix' => ['Colgante', 'Amuleto', 'Talismán menor']],
        ];

        $elements = Element::all()->keyBy('slug');

        $elementAdjective = [
            'fire'   => 'Ígnea',
            'water'  => 'Fluida',
            'earth'  => 'Terrenal',
            'air'    => 'Etérea',
            'light'  => 'Radiante',
            'shadow' => 'Oscura',
            'anima'  => 'Vital',
        ];

        foreach ($pieces as $pieceType => $meta) {
            $prefixes = $meta['prefix'];

            foreach ($elements as $slug => $element) {
                $prefix = $prefixes[array_rand($prefixes)];
                $adj    = $elementAdjective[$slug];

                Equipment::create([
                    'piece_type'      => $pieceType,
                    'element_id'      => $element->id,
                    'level'           => 1,
                    'name'            => "{$prefix} {$adj}",
                    'stat_bonus'      => 0,
                    'alignment_bonus' => 10,
                    'carga_maxima'    => 100,
                    'valor_base'      => 100,
                ]);
            }
        }
    }
}
