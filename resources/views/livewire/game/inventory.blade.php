@php
    $sheet    = $hero->statSheet();
    $slots    = ['casco','pecho','brazos','piernas','escudo','arma','amuleto'];
    $equipped = $hero->equippedItems->keyBy('piece_type');
    $esencias = $hero->talisman->todasLasEsencias();
    $colores  = \App\Models\Talisman::COLORES;
    $nombres  = \App\Models\Talisman::NOMBRES;

    $statLabel = [
        'casco'   => 'INT', 'pecho'   => 'RES', 'brazos'  => 'FUE',
        'piernas' => 'DES', 'escudo'  => 'DEF', 'arma'    => 'ATQ', 'amuleto' => 'SUE',
    ];
    $elementColor = $colores;

    $inventoryBySlot = collect($slots)->mapWithKeys(fn($s) => [$s => collect()])->toArray();
    foreach ($hero->inventory as $invRow) {
        $t = $invRow->equipment->piece_type;
        if (isset($inventoryBySlot[$t])) {
            $inventoryBySlot[$t][] = $invRow;
        }
    }
@endphp

<div>
    <div class="flex justify-between items-center mb-4">
        <h2 class="font-bold text-lg">— Equipamiento —</h2>
        <button wire:click="backToHub" class="text-xs border border-gray-400 px-2 py-1 hover:bg-gray-100">
            ← Refugio
        </button>
    </div>

    <div class="mb-4 p-3 bg-gray-50 border border-gray-200">
        <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Stats · {{ $hero->name }}</p>
        <div class="grid grid-cols-7 gap-1 text-xs text-center">
            @foreach(['fuerza'=>'FUE','resistencia'=>'RES','destreza'=>'DES','inteligencia'=>'INT','suerte'=>'SUE','ataque'=>'ATQ','defensa'=>'DEF'] as $key => $lbl)
                <div>
                    <div class="text-gray-400">{{ $lbl }}</div>
                    <div class="font-bold text-base">{{ $sheet[$key]['base'] + $sheet[$key]['bonus'] }}</div>
                    @if($sheet[$key]['bonus'] > 0)
                        <div class="text-green-600">+{{ $sheet[$key]['bonus'] }}</div>
                    @else
                        <div class="text-gray-200">—</div>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-2 pt-2 border-t border-gray-200 text-xs text-gray-400 flex gap-4">
            <span>HP {{ $hero->hp_actual }}/{{ $hero->hp_maximo }}</span>
            <span>Oro {{ $hero->oro }}</span>
        </div>
    </div>

    <div class="mb-4 p-3 bg-gray-50 border border-gray-200">
        <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Talismán</p>
        <div class="grid grid-cols-7 gap-1 text-xs text-center">
            @foreach($esencias as $slug => $valor)
                <div>
                    <div style="color:{{ $colores[$slug] }}" class="text-xs">{{ $nombres[$slug] }}</div>
                    <div class="font-bold">{{ $valor }}</div>
                    @if($hero->hasSeal($slug, 1))
                        <div style="color:{{ $colores[$slug] }}" class="text-xs">✦</div>
                    @else
                        <div class="w-full bg-gray-200 h-1 mt-1 rounded">
                            <div class="h-1 rounded" style="width:{{ $valor }}%;background:{{ $colores[$slug] }}"></div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    @php
        $elementosEnInventario = $hero->inventory
            ->map(fn($i) => $i->equipment->element->slug)
            ->unique()
            ->values();
    @endphp

    @if($elementosEnInventario->isNotEmpty())
        <div class="mb-4 p-3 bg-gray-50 border border-gray-200">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Equipar setup completo</p>
            <div class="flex flex-wrap gap-2">
                @foreach($elementosEnInventario as $slug)
                    <button wire:click="equiparSetup('{{ $slug }}')"
                            class="text-xs px-3 py-1 border rounded hover:opacity-80"
                            style="border-color:{{ $colores[$slug] }};
                                color:{{ $colores[$slug] }};
                                background:{{ $colores[$slug] }}11">
                        Setup {{ $nombres[$slug] }}
                    </button>
                @endforeach
            </div>
            <p class="text-xs text-gray-300 mt-2">
                Equipa todas las piezas disponibles de ese elemento. Lo que tengas puesto pasa al inventario.
            </p>
        </div>
    @endif

    @foreach($slots as $slot)
        @php
            $slotData    = $equipped[$slot] ?? null;
            $itemsEnSlot = $inventoryBySlot[$slot] ?? collect();
            $tieneItems  = count($itemsEnSlot) > 0;
        @endphp

        <div class="flex items-center gap-2 mt-4 mb-1">
            <span class="text-xs font-bold text-gray-500 uppercase tracking-wide w-14 shrink-0">{{ $slot }}</span>
            <div class="flex-1 border-t border-gray-100"></div>
        </div>

        <div class="flex items-center border border-gray-300 bg-gray-50 px-3 py-2 mb-1">
            @if($slotData)
                @php $eq = $slotData->equipment; $c = $elementColor[$eq->element->slug] ?? '#9ca3af'; @endphp
                <div class="flex-1 leading-tight">
                    <span class="font-semibold">{{ $eq->name }}</span>
                    <span class="ml-1 text-xs px-1 rounded"
                          style="background:{{ $c }}22;color:{{ $c }};border:1px solid {{ $c }}55">
                        {{ $eq->element->name }}
                    </span>
                    <span class="ml-1 text-gray-400 text-xs">
                        Nv{{ $eq->level }}
                        &nbsp;·&nbsp; {{ $statLabel[$slot] }}+{{ $slotData->statEfectivo() }}
                        &nbsp;·&nbsp; Alin+{{ $slotData->alignmentEfectivo() }}
                        &nbsp;·&nbsp; carga {{ $slotData->carga }}/{{ $eq->carga_maxima }}
                    </span>
                </div>
                <button wire:click="unequipItem('{{ $slot }}')"
                        class="text-xs text-gray-400 hover:text-red-500 shrink-0 ml-2">Quitar</button>
            @else
                <span class="text-gray-300 italic text-xs">— vacío —</span>
            @endif
        </div>

        @if($tieneItems)
            @foreach($itemsEnSlot as $invRow)
                @php
                    $eq  = $invRow->equipment;
                    $c   = $elementColor[$eq->element->slug] ?? '#9ca3af';

                    $esFusion    = $slotData && $slotData->equipment_id === $eq->id;
                    $statNuevo   = $invRow->statEfectivo();
                    $statActual  = $slotData ? $slotData->statEfectivo() : null;
                    $diff        = ($statActual !== null && !$esFusion) ? $statNuevo - $statActual : null;
                    $cargaFusion = $esFusion ? min($eq->carga_maxima, $slotData->carga + $invRow->carga) : null;
                @endphp
                <div class="flex items-center border border-gray-100 border-l-2 ml-2 px-3 py-2 hover:bg-gray-50"
                     style="border-left-color:{{ $c }}">
                    <div class="flex-1 leading-tight">
                        <span class="font-semibold">{{ $eq->name }}</span>
                        <span class="ml-1 text-xs px-1 rounded"
                              style="background:{{ $c }}22;color:{{ $c }};border:1px solid {{ $c }}55">
                            {{ $eq->element->name }}
                        </span>
                        <span class="ml-1 text-gray-400 text-xs">
                            Nv{{ $eq->level }}
                            &nbsp;·&nbsp; {{ $statLabel[$slot] }}+{{ $statNuevo }}
                            &nbsp;·&nbsp; Alin+{{ $invRow->equipment->alignmentEfectivo($invRow->carga) }}
                            &nbsp;·&nbsp; carga {{ $invRow->carga }}/{{ $eq->carga_maxima }}
                        </span>
                        @if($esFusion)
                            <span class="ml-1 text-xs text-blue-500">
                                ⟳ fusión → {{ $cargaFusion }}/{{ $eq->carga_maxima }}
                            </span>
                        @elseif($diff !== null)
                            <span class="ml-1 text-xs font-bold
                                {{ $diff > 0 ? 'text-green-600' : ($diff < 0 ? 'text-red-500' : 'text-gray-400') }}">
                                ({{ $diff > 0 ? '+' : '' }}{{ $diff }})
                            </span>
                        @else
                            <span class="ml-1 text-xs text-gray-300">(slot vacío)</span>
                        @endif
                    </div>
                    <button wire:click="equipItem({{ $invRow->id }})"
                            class="text-xs bg-black text-white px-2 py-1 hover:bg-gray-700 shrink-0 ml-2">
                        {{ $esFusion ? 'Fusionar' : 'Equipar' }}
                    </button>
                </div>
            @endforeach
        @endif
    @endforeach

    @if($hero->inventory->isEmpty())
        <p class="text-gray-300 italic text-xs mt-4">Mochila vacía. Los items se obtienen en expediciones.</p>
    @endif
</div>
