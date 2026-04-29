<div>
    @if($phase === 'waiting')
        <div wire:poll.1000ms="tick">
            <h2 class="font-bold text-lg mb-2">— En curso —</h2>
            @php
                $exp           = \App\Models\Expedition::find($expeditionId);
                $totalDuration = $exp?->duration_seconds ?? 10;
                $isRest        = $exp?->event_type === 'rest';
                $isGuardian    = $exp?->event_type === 'guardian';
                $kName         = \App\Models\Talisman::NOMBRES[$selectedKingdom] ?? '';
                $kColor        = \App\Models\Talisman::COLORES[$selectedKingdom] ?? '#111';
            @endphp
            <p class="mb-4">
                @if($isRest)
                    El Buscador descansa en el Refugio...
                @elseif($isGuardian)
                    <span class="font-bold" style="color:{{ $kColor }}">
                        El Buscador se acerca al corazón del reino de {{ $kName }}. El guardián espera.
                    </span>
                @else
                    El Buscador avanza hacia el reino de {{ $kName }}...
                @endif
            </p>
            <p class="text-2xl font-bold">{{ $secondsLeft }}s</p>
            <div class="w-full bg-gray-200 h-2 mt-2 rounded">
                <div class="h-2 rounded transition-all"
                     style="width:{{ $totalDuration > 0 ? (($totalDuration-$secondsLeft)/$totalDuration)*100 : 100 }}%;
                            background:{{ $isRest ? '#60a5fa' : $kColor }}">
                </div>
            </div>
        </div>
    @elseif($phase === 'result' && $resultado)
        @php
            $colores = \App\Models\Talisman::COLORES;
            $nombres = \App\Models\Talisman::NOMBRES;
            $esencias = $hero->talisman->todasLasEsencias();
        @endphp

        <h2 class="font-bold text-lg mb-2">— Resultado —</h2>

        @if(($resultado['event'] ?? null) === 'rest')
            <p class="text-blue-500 font-bold mb-3">{{ $resultado['message'] }}</p>
            <p class="mb-4">HP: {{ $hero->hp_actual }} / {{ $hero->hp_maximo }}</p>

        @elseif(($resultado['event'] ?? null) === 'merchant')
            @php
                $statLabel = [
                    'casco'=>'INT','pecho'=>'RES','brazos'=>'FUE',
                    'piernas'=>'DES','escudo'=>'DEF','arma'=>'ATQ','amuleto'=>'SUE',
                ];
            @endphp

            <p class="text-yellow-600 font-bold mb-2">{{ $resultado['message'] }}</p>

            @if($marketMessage)
                <div class="mb-3 px-3 py-2 text-xs border
                    {{ str_contains($marketMessage, 'insuficiente') || str_contains($marketMessage, 'no encontrado')
                    ? 'border-red-300 bg-red-50 text-red-600'
                    : 'border-green-300 bg-green-50 text-green-700' }}">
                    {{ $marketMessage }}
                </div>
            @endif

            <p class="text-xs text-gray-400 mb-3">Oro disponible: <strong>{{ $hero->oro }}</strong></p>

            @if(empty($resultado['items']))
                <p class="text-xs text-gray-400 italic mb-4">El mercader ya no tiene nada para ofrecer.</p>
            @else
                <div class="space-y-2 mb-4">
                    @foreach($resultado['items'] as $item)
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
                                    <button wire:click="buyMerchantItem({{ $item['equipment_id'] }}, {{ $item['carga'] }})"
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

        @elseif(($resultado['event'] ?? null) === 'silence')
            @php $kingdom = $resultado['kingdom'] ?? $selectedKingdom; @endphp
            <p class="font-bold mb-3" style="color:{{ $colores[$kingdom] ?? '#9ca3af' }}">
                Silencio — {{ $nombres[$kingdom] ?? $kingdom }}
            </p>
            <p class="text-xs text-gray-500 mb-4">
                El Buscador atraviesa el reino sin encontrar nada. El viaje no fue en vano.
            </p>
            <div class="border border-gray-200 p-3 text-xs space-y-1 mb-4">
                <p>+ Oro: <strong>{{ $resultado['oro_ganado'] }}</strong></p>
                <p>+ Esencia <span style="color:{{ $colores[$kingdom] ?? '#000' }}">{{ $nombres[$kingdom] ?? $kingdom }}</span>:
                <strong>+{{ $resultado['esencia_ganada'] }}</strong>
                → {{ $hero->talisman->getEsencia($kingdom) }}/{{ \App\Models\Talisman::MAX_ESENCIA }}
                </p>
            </div>

        @elseif(($resultado['event'] ?? null) === 'chest')
            @php $kingdom = $resultado['kingdom'] ?? $selectedKingdom; @endphp
            <p class="font-bold mb-3" style="color:{{ $colores[$kingdom] ?? '#9ca3af' }}">
                Cofre — {{ $nombres[$kingdom] ?? $kingdom }}
            </p>
            <p class="text-xs text-gray-500 mb-4">Entre las sombras del camino, un cofre olvidado.</p>
            <div class="border border-gray-200 p-3 text-xs space-y-1 mb-4">
                @if($resultado['toco_oro'])
                    <p>+ Oro: <strong>{{ $resultado['oro_ganado'] }}</strong></p>
                @endif
                @if($resultado['toco_esencia'])
                    <p>+ Esencia <span style="color:{{ $colores[$kingdom] ?? '#000' }}">{{ $nombres[$kingdom] ?? $kingdom }}</span>:
                    <strong>+{{ $resultado['esencia_ganada'] }}</strong>
                    → {{ $hero->talisman->getEsencia($kingdom) }}/{{ \App\Models\Talisman::MAX_ESENCIA }}
                    </p>
                @endif
                @if($resultado['toco_loot'] && $resultado['loot_item_name'])
                    @php
                        $lootSlug     = $resultado['loot_item_slug'];
                        $lootFusion   = $resultado['loot_fusion'] ?? false;
                        $cargaAntes   = $resultado['loot_carga_antes'] ?? 0;
                        $cargaDrop    = $resultado['loot_carga_drop'] ?? 0;
                        $cargaDespues = $resultado['loot_carga_despues'] ?? 0;
                        $cargaMax     = $resultado['loot_carga_maxima'] ?? 100;
                    @endphp
                    <p>+ {{ $lootFusion ? 'Fusión' : 'Loot' }}:
                        <strong>{{ $resultado['loot_item_name'] }}</strong>
                        @if($lootFusion)
                            <span class="text-blue-500">carga: {{ $cargaDespues }}/{{ $cargaMax }} ({{ $cargaAntes }}+{{ $cargaDrop }})</span>
                        @else
                            <span class="text-gray-400">carga: {{ $cargaDrop }}/{{ $cargaMax }}</span>
                        @endif
                    </p>
                @endif
            </div>

        @else
            {{-- Combate normal o Guardián --}}
            @php
                $kingdom    = $resultado['kingdom'] ?? $selectedKingdom;
                $kColor     = $colores[$kingdom] ?? '#9ca3af';
                $outcome    = $resultado['outcome'] ?? ($resultado['hero_won'] ? 'victory' : 'defeat');
                $isGuardian = $resultado['is_guardian'] ?? false;
                $selloObt   = $resultado['sello_obtenido'] ?? false;
                $enemy      = $resultado['enemy'];
                $hpLeft     = $resultado['hero_hp_left'];
                $hpMax      = $hero->hp_maximo;
                $hpPct      = $hpMax > 0 ? round(($hpLeft / $hpMax) * 100) : 0;
                $hpColor    = $hpPct > 55 ? 'text-green-600' : ($hpPct > 25 ? 'text-yellow-600' : 'text-red-500');
                $chanceH    = $resultado['chance_heroe_golpea'] ?? '—';
                $chanceE    = $resultado['chance_enemigo_golpea'] ?? '—';
            @endphp

            {{-- ── Encabezado según outcome ── --}}
            @if($isGuardian && $outcome === 'victory')
                <div class="mb-3 p-3 border-2 rounded text-center"
                    style="border-color:{{ $kColor }}; background:{{ $kColor }}11">
                    <p class="font-bold text-base" style="color:{{ $kColor }}">
                        ✦ Guardián derrotado — {{ $nombres[$kingdom] ?? $kingdom }}
                    </p>
                    <p class="text-xs mt-1" style="color:{{ $kColor }}">
                        El primer sello de la Gema queda grabado en el Talismán.
                        La esencia del reino no puede caer de 100.
                    </p>
                </div>
            @elseif($isGuardian && $outcome === 'defeat')
                <p class="font-bold mb-3 text-base text-red-600">
                    Derrota — el Guardián de {{ $nombres[$kingdom] ?? $kingdom }} resiste.
                </p>
            @elseif($isGuardian && $outcome === 'draw')
                <div class="mb-3 p-3 border border-gray-400 rounded bg-gray-50 text-center">
                    <p class="font-bold text-base text-gray-600">
                        Empate — el Guardián de {{ $nombres[$kingdom] ?? $kingdom }} no cae.
                    </p>
                    <p class="text-xs text-gray-400 mt-1">
                        Ninguno ganó. Volvés sin sello, pero conservás tu esencia.
                    </p>
                </div>
            @elseif($outcome === 'victory')
                <p class="font-bold mb-3 text-base" style="color:{{ $kColor }}">
                    Victoria — {{ $nombres[$kingdom] ?? $kingdom }}
                </p>
            @elseif($outcome === 'defeat')
                <p class="font-bold mb-3 text-base text-red-600">
                    Derrota — {{ $nombres[$kingdom] ?? $kingdom }}
                </p>
            @else {{-- draw --}}
                <div class="mb-3 p-3 border border-gray-300 rounded bg-gray-50">
                    <p class="font-bold text-base text-gray-600">
                        Empate — {{ $nombres[$kingdom] ?? $kingdom }}
                    </p>
                    <p class="text-xs text-gray-400 mt-1">
                        El combate terminó sin ganador. No hay recompensas ni penalización.
                    </p>
                </div>
            @endif

            {{-- ── Combatientes ── --}}
            <div class="grid grid-cols-2 gap-3 mb-4 text-xs">
                <div class="border border-gray-200 p-2 bg-gray-50">
                    <p class="font-bold mb-1">{{ $hero->name }}</p>
                    <p>HP: <span class="font-bold {{ $hpColor }}">{{ $hpLeft }}/{{ $hpMax }}</span>
                    <span class="text-gray-400">({{ $hpPct }}%)</span></p>
                    <p>Ataque: {{ $hero->ataque }} · Defensa: {{ $hero->defensa }}</p>
                    <p>Poder vs {{ $nombres[$kingdom] ?? $kingdom }}:
                    <strong>{{ round($hero->talisman->poderContra($kingdom, $hero)) }}</strong></p>
                    <p>Chance de golpear: <strong>{{ $chanceH }}%</strong></p>
                </div>
                <div class="border p-2 bg-gray-50" style="border-color:{{ $kColor }}44">
                    <p class="font-bold mb-1" style="color:{{ $kColor }}">
                        {{ $enemy['name'] }}
                        @if($isGuardian) <span class="text-xs">(Guardián)</span> @endif
                    </p>
                    <p>HP:
                        <span class="font-bold
                            {{ $outcome === 'victory' ? 'text-gray-400' : ($outcome === 'draw' ? 'text-yellow-600' : 'text-green-600') }}">
                            {{ $outcome === 'victory' ? '0' : '?' }}/{{ $enemy['hp'] }}
                        </span>
                    </p>
                    <p>Ataque: {{ $enemy['ataque'] }} · Defensa: {{ $enemy['defensa'] }}</p>
                    <p>Poder: <strong>{{ round($enemy['poder']) }}</strong></p>
                    <p>Chance de golpear: <strong>{{ $chanceE }}%</strong></p>
                </div>
            </div>

            {{-- ── Métricas ── --}}
            <div class="grid grid-cols-4 gap-2 mb-4 text-xs text-center">
                <div class="border border-gray-200 p-2">
                    <div class="text-gray-400">Rondas</div>
                    <div class="font-bold text-base">{{ $resultado['rounds'] }}</div>
                </div>
                <div class="border border-gray-200 p-2">
                    <div class="text-gray-400">Oro</div>
                    <div class="font-bold text-base">
                        {{ $outcome === 'victory' ? '+' . $resultado['oro_ganado'] : '—' }}
                    </div>
                </div>
                <div class="border border-gray-200 p-2">
                    @if($outcome === 'victory')
                        <div class="text-gray-400">Esencia +</div>
                        <div class="font-bold text-base text-green-600">
                            +{{ $resultado['esencia_ganada'] }}
                        </div>
                    @elseif($outcome === 'defeat')
                        <div class="text-gray-400">Esencia −</div>
                        <div class="font-bold text-base text-red-500">
                            −{{ $resultado['esencia_perdida'] }}
                        </div>
                    @else
                        <div class="text-gray-400">Esencia</div>
                        <div class="font-bold text-base text-gray-400">sin cambio</div>
                    @endif
                    <div style="color:{{ $kColor }}">{{ $nombres[$kingdom] ?? $kingdom }}</div>
                </div>
                <div class="border border-gray-200 p-2">
                    <div class="text-gray-400">HP restante</div>
                    <div class="font-bold text-base {{ $hpColor }}">{{ $hpLeft }}</div>
                </div>
            </div>

            {{-- ── Loot (solo victorias en combate normal) ── --}}
            @if(!$isGuardian && $outcome === 'victory' && ($resultado['loot_item_name'] ?? null))
                @php
                    $lootFusion   = $resultado['loot_fusion'] ?? false;
                    $cargaAntes   = $resultado['loot_carga_antes'] ?? 0;
                    $cargaDrop    = $resultado['loot_carga_drop'] ?? 0;
                    $cargaDespues = $resultado['loot_carga_despues'] ?? 0;
                    $cargaMax     = $resultado['loot_carga_maxima'] ?? 100;
                @endphp
                <div class="border border-gray-200 p-3 text-xs space-y-1 mb-4">
                    <p>+ {{ $lootFusion ? 'Fusión' : 'Loot' }}:
                        <strong>{{ $resultado['loot_item_name'] }}</strong>
                        @if($lootFusion)
                            <span class="text-blue-500">
                                carga: {{ $cargaDespues }}/{{ $cargaMax }} ({{ $cargaAntes }}+{{ $cargaDrop }})
                            </span>
                        @else
                            <span class="text-gray-400">carga: {{ $cargaDrop }}/{{ $cargaMax }}</span>
                        @endif
                    </p>
                </div>
            @endif

            {{-- ── Log de rondas ── --}}
            <div class="bg-gray-100 p-3 mb-4 space-y-1 max-h-48 overflow-y-auto text-xs">
                @foreach($resultado['logs'] as $log)
                    <p>{{ $log['narrative_line'] }}</p>
                @endforeach
            </div>

            {{-- ── Talismán post-combate ── --}}
            <div class="mb-4 space-y-1">
                @foreach($esencias as $slug => $valor)
                    <div class="flex items-center gap-2">
                        <span class="w-12 text-xs" style="color:{{ $colores[$slug] }}">{{ $nombres[$slug] }}</span>
                        @if($hero->hasSeal($slug, 1))
                            <span class="text-xs" style="color:{{ $colores[$slug] }}">✦ sellado</span>
                            <div class="flex-1 bg-gray-200 h-1.5 rounded">
                                <div class="h-1.5 rounded" style="width:100%; background:{{ $colores[$slug] }}"></div>
                            </div>
                        @else
                            <div class="flex-1 bg-gray-200 h-1.5 rounded">
                                <div class="h-1.5 rounded" style="width:{{ $valor }}%; background:{{ $colores[$slug] }}"></div>
                            </div>
                        @endif
                        <span class="text-xs text-gray-400 w-10 text-right">{{ $valor }}/100</span>
                    </div>
                @endforeach
            </div>

            {{-- ── Botones post-combate ── --}}
            @if($outcome === 'defeat')
                <div class="flex gap-2 mb-3">
                    <button wire:click="backToHub"
                            class="bg-black text-white px-4 py-2 text-sm">
                        Volver al Refugio
                    </button>
                </div>
            @endif
        @endif

        <p class="text-xs text-gray-500 mb-3">Oro total: {{ $hero->oro }}</p>

        <button wire:click="backToHub" class="bg-black text-white px-4 py-2 text-sm">
            Volver al Refugio
        </button>
    @endif
</div>
