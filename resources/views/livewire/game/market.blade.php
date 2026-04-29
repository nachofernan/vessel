@php
    $colores = \App\Models\Talisman::COLORES;
    $nombres = \App\Models\Talisman::NOMBRES;
    $statLabel = [
        'casco'=>'INT','pecho'=>'RES','brazos'=>'FUE',
        'piernas'=>'DES','escudo'=>'DEF','arma'=>'ATQ','amuleto'=>'SUE',
    ];
@endphp

<div>
    <div class="flex justify-between items-center mb-4">
        <h2 class="font-bold text-lg">— Mercado —</h2>
        <div class="flex gap-2 items-center">
            <span class="text-xs text-gray-400">Oro: <strong class="text-black">{{ $hero->oro }}</strong></span>
            <button wire:click="backToHub"
                    class="text-xs border border-gray-400 px-2 py-1 hover:bg-gray-100">
                ← Refugio
            </button>
        </div>
    </div>

    @if($marketMessage)
        <div class="mb-3 px-3 py-2 text-xs border
            {{ str_contains($marketMessage, 'insuficiente') || str_contains($marketMessage, 'no encontrado')
            ? 'border-red-300 bg-red-50 text-red-600'
            : 'border-green-300 bg-green-50 text-green-700' }}">
            {{ $marketMessage }}
        </div>
    @endif

    <p class="text-xs text-gray-400 mb-1">
        El stock se renueva cada minuto. Precio fijo: <strong>100 oro</strong> por ítem.
    </p>

    <div wire:poll.10000ms="refreshMarket" class="mb-4">
        <p class="text-xs text-gray-300 italic">El mercader ordena su mesa...</p>
    </div>

    @if(empty($marketStock))
        <p class="text-gray-400 italic text-xs">El mercado está vacío.</p>
    @else
        <div class="space-y-2">
            @foreach($marketStock as $item)
                @php
                    $c      = $item['element_color'];
                    $canBuy = $hero->oro >= $item['precio'];
                @endphp
                <div class="flex items-center border border-gray-200 px-3 py-2
                            {{ $canBuy ? 'hover:bg-gray-50' : 'opacity-50' }}">
                    <div class="w-14 text-gray-400 text-xs uppercase shrink-0">
                        {{ $item['piece_type'] }}
                    </div>
                    <div class="flex-1 mx-2 leading-tight">
                        <span class="font-semibold text-sm">{{ $item['name'] }}</span>
                        <span class="ml-1 text-xs px-1 rounded"
                            style="background:{{ $c }}22;color:{{ $c }};border:1px solid {{ $c }}55">
                            {{ $item['element_name'] }}
                        </span>
                        <br>
                        <span class="text-xs text-gray-400">
                            {{ $statLabel[$item['piece_type']] }}+{{ $item['stat_bonus_efectivo'] }}
                            &nbsp;·&nbsp; Alin+{{ $item['alignment_bonus_efectivo'] }}
                            &nbsp;·&nbsp; Carga: {{ $item['carga'] }}/{{ $item['carga_maxima'] }}
                        </span>
                        @php
                            $yaEquipado  = $hero->equippedItems->first(fn($e) => $e->equipment_id === $item['equipment_id']);
                            $yaEnMochila = $hero->inventory->first(fn($i) => $i->equipment_id === $item['equipment_id']);
                        @endphp
                        @if($yaEquipado)
                            <br><span class="text-xs text-blue-500">
                                ⟳ Equipado · carga {{ $yaEquipado->carga }}/{{ $item['carga_maxima'] }}
                                → fusión a {{ min($item['carga_maxima'], $yaEquipado->carga + $item['carga']) }}
                            </span>
                        @elseif($yaEnMochila)
                            <br><span class="text-xs text-indigo-400">
                                ↓ En mochila · carga {{ $yaEnMochila->carga }}/{{ $item['carga_maxima'] }}
                                → fusión a {{ min($item['carga_maxima'], $yaEnMochila->carga + $item['carga']) }}
                            </span>
                        @endif
                    </div>
                    <div class="shrink-0 text-right">
                        <div class="text-xs text-gray-500 mb-1">{{ $item['precio'] }} oro</div>
                        @if($canBuy)
                            <button wire:click="buyItem({{ $item['equipment_id'] }}, {{ $item['carga'] }})"
                                    class="text-xs bg-black text-white px-2 py-1 hover:bg-gray-700">
                                Comprar
                            </button>
                        @else
                            <span class="text-xs text-gray-300">Sin oro</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
