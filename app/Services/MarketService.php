<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\Hero;
use App\Models\HeroEquipment;
use App\Models\Inventory;
use App\Models\MarketStock;

class MarketService
{
    public const PRECIO_NIVEL_1 = 100;

    /**
     * Devuelve el stock vigente. Si caducó (cambió el minuto), regenera.
     * Cada ítem: { equipment_id, name, piece_type, element_slug, element_name,
     *              element_color, carga, carga_maxima, precio, stat_bonus_efectivo,
     *              alignment_bonus_efectivo }
     */
    public function getStock(): array
    {
        $stock = MarketStock::latest()->first();

        if (!$stock || !$stock->isFresh()) {
            session()->forget('market_purchased'); 
            $stock = $this->generate();
        }

        return $stock->items;
    }

    /**
     * Intenta comprar un ítem del stock actual.
     * Devuelve ['ok' => bool, 'message' => string]
     */
    public function buy(Hero $hero, int $equipmentId, int $carga): array
    {
        if ($hero->oro < self::PRECIO_NIVEL_1) {
            return ['ok' => false, 'message' => 'Oro insuficiente.'];
        }

        $equipment = Equipment::with('element')->find($equipmentId);
        if (!$equipment) {
            return ['ok' => false, 'message' => 'Ítem no encontrado.'];
        }

        // Descontar oro y marcar como comprado
        $hero->decrement('oro', self::PRECIO_NIVEL_1);
        $purchased = session('market_purchased', []);
        $purchased[] = $equipmentId;
        session(['market_purchased' => $purchased]);

        // Fusión: equipado > inventario > nuevo
        $equipped = HeroEquipment::where('hero_id', $hero->id)
            ->where('piece_type', $equipment->piece_type)
            ->where('equipment_id', $equipmentId)
            ->first();

        if ($equipped) {
            $nueva = min($equipment->carga_maxima, $equipped->carga + $carga);
            $equipped->update(['carga' => $nueva]);
            return ['ok' => true, 'message' => "Carga fusionada al slot equipado. Carga: {$nueva}/{$equipment->carga_maxima}."];
        }

        $inInventory = Inventory::where('hero_id', $hero->id)
            ->where('equipment_id', $equipmentId)
            ->first();

        if ($inInventory) {
            $nueva = min($equipment->carga_maxima, $inInventory->carga + $carga);
            $inInventory->update(['carga' => $nueva]);
            return ['ok' => true, 'message' => "Carga fusionada en inventario. Carga: {$nueva}/{$equipment->carga_maxima}."];
        }

        Inventory::create(['hero_id' => $hero->id, 'equipment_id' => $equipmentId, 'carga' => $carga]);
        return ['ok' => true, 'message' => "Ítem agregado al inventario."];
    }

    // ─── Privados ────────────────────────────────────────────────────────────

    private function generate(): MarketStock
    {
        $count  = rand(8, 10);
        $pieces = Equipment::with('element')->where('level', 1)->inRandomOrder()->limit($count)->get();

        $items = $pieces->map(function (Equipment $eq) {
            $carga = rand(5, 15);
            return [
                'equipment_id'              => $eq->id,
                'name'                      => $eq->name,
                'piece_type'                => $eq->piece_type,
                'element_slug'              => $eq->element->slug,
                'element_name'              => $eq->element->name,
                'element_color'             => $eq->element->color,
                'carga'                     => $carga,
                'carga_maxima'              => $eq->carga_maxima,
                'precio'                    => self::PRECIO_NIVEL_1,
                'stat_bonus_efectivo'       => $eq->stat_bonus + (int)floor($carga / 5),
                'alignment_bonus_efectivo'  => $eq->alignment_bonus + (int)floor($carga / 5),
            ];
        })->values()->all();

        // Borra el anterior y crea el nuevo
        MarketStock::truncate();

        return MarketStock::create([
            'items'        => $items,
            'generated_at' => now(),
        ]);
    }
}