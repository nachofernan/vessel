<div class="p-6 max-w-2xl mx-auto font-mono text-sm">

    {{-- ═══════════════════════════════════════════════════════ CREAR HÉROE ══ --}}
    @if($phase === 'create')
        <h1 class="text-xl font-bold mb-4">The Vessel — Farlock's Codex</h1>
        <p class="mb-2 text-gray-500">Un estudioso encontró el Códice. El Talismán lo eligió.</p>
        <input wire:model="heroName" placeholder="Nombre del Buscador (opcional)"
               class="border px-3 py-2 w-full mb-3" />
        <button wire:click="createHero" class="bg-black text-white px-4 py-2">Comenzar</button>

    {{-- ═══════════════════════════════════════════════════════════════ HUB ══ --}}
    @elseif($phase === 'hub')

        @php
            $sheet    = $hero->statSheet();
            $esencias = $hero->talisman->todasLasEsencias();
            $colores  = \App\Models\Talisman::COLORES;
            $nombres  = \App\Models\Talisman::NOMBRES;
        @endphp

        <div class="flex justify-between items-start mb-3">
            <h2 class="font-bold text-lg">— Refugio —</h2>
            <div class="flex gap-2">
                <button wire:click="goToMarket"
                        class="text-xs border border-gray-400 px-2 py-1 hover:bg-gray-100">
                    Mercado
                </button>
                <button wire:click="goToInventory"
                        class="text-xs border border-gray-400 px-2 py-1 hover:bg-gray-100">
                    Equipamiento
                </button>
                <button wire:click="resetGame"
                        wire:confirm="¿Reiniciar partida? El héroe y todos sus datos se eliminarán."
                        class="text-xs text-red-500 border border-red-300 px-2 py-1 hover:bg-red-50">
                    Reiniciar
                </button>
            </div>
        </div>

        <p>Buscador: <strong>{{ $hero->name }}</strong>
           &nbsp;·&nbsp; HP: {{ $hero->hp_actual }}/{{ $hero->hp_maximo }}
           &nbsp;·&nbsp; Oro: {{ $hero->oro }}
        </p>

        {{-- Stats rápidos --}}
        <div class="grid grid-cols-7 gap-1 mt-3 mb-4 text-xs text-center">
            @foreach(['fuerza'=>'FUE','resistencia'=>'RES','destreza'=>'DES','inteligencia'=>'INT','suerte'=>'SUE','ataque'=>'ATQ','defensa'=>'DEF'] as $key => $lbl)
                <div class="border border-gray-200 p-1">
                    <div class="text-gray-400">{{ $lbl }}</div>
                    <div class="font-bold">{{ $sheet[$key]['base'] + $sheet[$key]['bonus'] }}
                        @if($sheet[$key]['bonus'] > 0)
                            <span class="text-green-600 text-xs">(+{{ $sheet[$key]['bonus'] }})</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Talismán: esencias + poder vs reinos --}}
        @php
            $esenciasEfectivas = $hero->talisman->esenciasEfectivas($hero);
            $esenciasFarmeadas = $hero->talisman->todasLasEsencias();
            $max = \App\Models\Talisman::MAX_ESENCIA;
        @endphp
        <div class="mb-5 p-3 border border-gray-200 bg-gray-50">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Talismán — Esencias</p>
            <div class="space-y-1 mb-3">
                @foreach($esenciasFarmeadas as $slug => $valorFarmeado)
                    @php
                        $color    = $colores[$slug];
                        $efectivo = $esenciasEfectivas[$slug];
                        $bonus    = $efectivo - $valorFarmeado;
                        $barBase  = $max > 0 ? ($valorFarmeado / $max) * 100 : 0;
                        $barBonus = $max > 0 ? ($bonus / $max) * 100 : 0;
                    @endphp
                    <div class="flex items-center gap-2">
                        <span class="w-12 text-xs" style="color:{{ $color }}">{{ $nombres[$slug] }}</span>
                        <div class="flex-1 bg-gray-200 h-2 rounded overflow-hidden flex">
                            <div class="h-2" style="width:{{ $barBase }}%; background:{{ $color }}"></div>
                            <div class="h-2" style="width:{{ $barBonus }}%; background:{{ $color }}; opacity:0.35"></div>
                        </div>
                        <span class="text-xs text-gray-500 w-20 text-right">
                            {{ $valorFarmeado }}/{{ $max }}
                            @if($bonus > 0)
                                <span style="color:{{ $color }}">(+{{ $bonus }})</span>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>

            {{-- Poder vs cada reino --}}
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Poder vs. reinos</p>
            @php
                $poderes = [];
                foreach (\App\Models\Talisman::ELEMENTOS as $slug) {
                    $poderes[$slug] = $hero->talisman->poderContra($slug, $hero);
                }
                $maxPoder = max($poderes) ?: 1;
            @endphp
            <div class="space-y-1">
                @foreach($poderes as $slug => $poder)
                    @php
                        $color  = $colores[$slug];
                        $barW   = round(($poder / $maxPoder) * 100);
                        $pEnem  = 100 * ($selectedDuration / 10); // anillo 1 siempre por ahora
                        $chance = round(($poder / ($poder + $pEnem)) * 100);
                    @endphp
                    <div class="flex items-center gap-2">
                        <span class="w-12 text-xs" style="color:{{ $color }}">{{ $nombres[$slug] }}</span>
                        <div class="flex-1 bg-gray-200 h-1.5 rounded overflow-hidden">
                            <div class="h-1.5 rounded" style="width:{{ $barW }}%; background:{{ $color }}"></div>
                        </div>
                        <span class="text-xs text-gray-500 w-8 text-right">{{ round($poder) }}</span>
                        <span class="text-xs w-10 text-right font-bold
                            {{ $chance >= 55 ? 'text-green-600' : ($chance >= 45 ? 'text-yellow-600' : 'text-red-500') }}">
                            {{ $chance }}%
                        </span>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-gray-300 mt-2">% = chance de golpear vs reino en {{ $selectedDuration }}s · anillo 1</p>
        </div>

        <hr class="my-4">

        {{-- Descansar --}}
        @if($hero->hp_actual < $hero->hp_maximo)
            <div class="mb-4 p-3 bg-gray-50 border border-gray-200">
                <p class="text-gray-600 mb-2 text-xs">HP bajo. Descansar restaura toda la vida (10s).</p>
                <button wire:click="launchRest" class="bg-gray-700 text-white px-4 py-2 text-xs">
                    Descansar (10s)
                </button>
            </div>
        @endif

        {{-- Selector de reino --}}
        <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Reino destino</p>
        <div class="grid grid-cols-7 gap-1 mb-4">
            @foreach(\App\Livewire\GameCore::KINGDOMS as $slug => $info)
                <button wire:click="$set('selectedKingdom', '{{ $slug }}')"
                        class="text-xs py-2 px-1 border text-center transition-all"
                        style="
                            border-color: {{ $selectedKingdom === $slug ? $info['color'] : '#e5e7eb' }};
                            background: {{ $selectedKingdom === $slug ? $info['color'].'22' : 'white' }};
                            color: {{ $selectedKingdom === $slug ? $info['color'] : '#6b7280' }};
                        ">
                    {{ $info['name'] }}
                </button>
            @endforeach
        </div>

        {{-- Selector de duración --}}
        <label class="block text-xs text-gray-400 uppercase tracking-wide mb-1">
            Duración: {{ $selectedDuration }}s
        </label>
        <input type="range" wire:model="selectedDuration" min="10" max="50" step="10"
               class="w-full mb-4" />

        <button wire:click="launchExpedition"
                class="text-white px-4 py-2 text-sm"
                style="background: {{ \App\Livewire\GameCore::KINGDOMS[$selectedKingdom]['color'] ?? '#111' }}">
            Partir — Reino de {{ \App\Livewire\GameCore::KINGDOMS[$selectedKingdom]['name'] }}
        </button>

    {{-- ═══════════════════════════════════════════════════════ INVENTARIO ══ --}}
    @elseif($phase === 'inventory')
        @php
            $sheet    = $hero->statSheet();
            $slots    = ['casco','pecho','brazos','piernas','escudo','arma','amuleto'];
            $equipped = $hero->equippedItems->keyBy('piece_type');
            $esencias = $hero->talisman->todasLasEsencias();
            $colores  = \App\Models\Talisman::COLORES;
            $nombres  = \App\Models\Talisman::NOMBRES;

            $statLabel = [
                'casco'=>'INT','pecho'=>'RES','brazos'=>'FUE',
                'piernas'=>'DES','escudo'=>'DEF','arma'=>'ATQ','amuleto'=>'SUE',
            ];
            $elementColor = $colores;
        @endphp

        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold text-lg">— Equipamiento —</h2>
            <button wire:click="backToHub" class="text-xs border border-gray-400 px-2 py-1 hover:bg-gray-100">
                ← Refugio
            </button>
        </div>

        {{-- Stats --}}
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

        {{-- Esencias (compactas) --}}
        <div class="mb-4 p-3 bg-gray-50 border border-gray-200">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Talismán</p>
            <div class="grid grid-cols-7 gap-1 text-xs text-center">
                @foreach($esencias as $slug => $valor)
                    <div>
                        <div style="color:{{ $colores[$slug] }}" class="text-xs">{{ $nombres[$slug] }}</div>
                        <div class="font-bold">{{ $valor }}</div>
                        <div class="w-full bg-gray-200 h-1 mt-1 rounded">
                            <div class="h-1 rounded" style="width:{{ $valor }}%;background:{{ $colores[$slug] }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Slots equipados --}}
        <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Equipo equipado</p>
        <div class="space-y-1 mb-5">
            @foreach($slots as $slot)
                @php $slotData = $equipped[$slot] ?? null; @endphp
                <div class="flex items-center border border-gray-200 px-3 py-2">
                    <div class="w-14 text-gray-400 text-xs uppercase shrink-0">{{ $slot }}</div>
                    @if($slotData)
                        @php $eq = $slotData->equipment; $c = $elementColor[$eq->element->slug] ?? '#9ca3af'; @endphp
                        <div class="flex-1 mx-2 leading-tight">
                            <span class="font-semibold">{{ $eq->name }}</span>
                            <span class="ml-1 text-xs px-1 rounded"
                                  style="background:{{ $c }}22;color:{{ $c }};border:1px solid {{ $c }}55">
                                {{ $eq->element->name }}
                            </span>
                            <span class="ml-1 text-gray-400 text-xs">
                                Nv{{ $eq->level }} · {{ $statLabel[$slot] }}+{{ $slotData->statEfectivo() }}
                                · carga {{ $slotData->carga }}/{{ $eq->carga_maxima }}
                            </span>
                        </div>
                        <button wire:click="unequipItem('{{ $slot }}')"
                                class="text-xs text-gray-400 hover:text-red-500 shrink-0">Quitar</button>
                    @else
                        <div class="flex-1 mx-2 text-gray-300 italic text-xs">— vacío —</div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Mochila --}}
        <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">
            Mochila — {{ $hero->inventory->count() }} items
        </p>
        @if($hero->inventory->isEmpty())
            <p class="text-gray-300 italic text-xs">Vacía. Los items se obtienen en expediciones.</p>
        @else
            <div class="space-y-1">
                @foreach($hero->inventory as $invRow)
                    @php
                        $eq   = $invRow->equipment;
                        $c    = $elementColor[$eq->element->slug] ?? '#9ca3af';
                        $cur  = $equipped[$eq->piece_type] ?? null;
                        $diff = $cur ? $eq->stat_bonus - $cur->equipment->stat_bonus : null;
                    @endphp
                    <div class="flex items-center border border-gray-100 px-3 py-2 hover:bg-gray-50">
                        <div class="w-14 text-gray-400 text-xs uppercase shrink-0">{{ $eq->piece_type }}</div>
                        <div class="flex-1 mx-2 leading-tight">
                            <span class="font-semibold">{{ $eq->name }}</span>
                            @if($invRow->quantity > 1)
                                <span class="text-gray-400 text-xs"> ×{{ $invRow->quantity }}</span>
                            @endif
                            <span class="ml-1 text-xs px-1 rounded"
                                  style="background:{{ $c }}22;color:{{ $c }};border:1px solid {{ $c }}55">
                                {{ $eq->element->name }}
                            </span>
                            <span class="ml-1 text-gray-400 text-xs">
                                Nv{{ $eq->level }} · {{ $statLabel[$eq->piece_type] }}+{{ $eq->stat_bonus }}
                            </span>
                            @if($diff !== null)
                                <span class="ml-1 text-xs font-bold {{ $diff > 0 ? 'text-green-600' : ($diff < 0 ? 'text-red-500' : 'text-gray-400') }}">
                                    ({{ $diff > 0 ? '+' : '' }}{{ $diff }})
                                </span>
                            @endif
                            @php
                                $curSlot = $equipped[$eq->piece_type] ?? null;
                                $statActual  = $curSlot ? $curSlot->statEfectivo() : null;
                                $statNuevo   = $invRow->statEfectivo();
                                $diff        = $statActual !== null ? $statNuevo - $statActual : null;
                                $esFusion    = $curSlot && $curSlot->equipment_id === $eq->id;
                                $cargaFusion = $esFusion ? min($eq->carga_maxima, $curSlot->carga + $invRow->carga) : null;
                            @endphp

                            <span class="ml-1 text-gray-400 text-xs">
                                Nv{{ $eq->level }} · {{ $statLabel[$eq->piece_type] }}+{{ $invRow->statEfectivo() }}
                                · carga {{ $invRow->carga }}/{{ $eq->carga_maxima }}
                            </span>

                            @if($esFusion)
                                <span class="ml-1 text-xs text-blue-500">
                                    ⟳ fusión → {{ $cargaFusion }}/{{ $eq->carga_maxima }}
                                </span>
                            @elseif($diff !== null)
                                <span class="ml-1 text-xs font-bold {{ $diff > 0 ? 'text-green-600' : ($diff < 0 ? 'text-red-500' : 'text-gray-400') }}">
                                    ({{ $diff > 0 ? '+' : '' }}{{ $diff }})
                                </span>
                            @endif
                        </div>
                        <button wire:click="equipItem({{ $invRow->id }})"
                                class="text-xs bg-black text-white px-2 py-1 hover:bg-gray-700 shrink-0">
                            Equipar
                        </button>
                    </div>
                @endforeach
            </div>
        @endif

    {{-- ═══════════════════════════════════════════════════════ ESPERANDO ══ --}}
    @elseif($phase === 'waiting')
        <div wire:poll.1000ms="tick">
            <h2 class="font-bold text-lg mb-2">— En curso —</h2>
            @php
                $exp           = \App\Models\Expedition::find($expeditionId);
                $totalDuration = $exp?->duration_seconds ?? $selectedDuration;
                $isRest        = $exp?->event_type === 'rest';
                $kName         = \App\Livewire\GameCore::KINGDOMS[$selectedKingdom]['name'] ?? '';
                $kColor        = \App\Livewire\GameCore::KINGDOMS[$selectedKingdom]['color'] ?? '#111';
            @endphp
            <p class="mb-4">
                {{ $isRest ? 'El Buscador descansa en el Refugio...' : "El Buscador avanza hacia el reino de {$kName}..." }}
            </p>
            <p class="text-2xl font-bold">{{ $secondsLeft }}s</p>
            <div class="w-full bg-gray-200 h-2 mt-2 rounded">
                <div class="h-2 rounded transition-all"
                     style="width:{{ $totalDuration > 0 ? (($totalDuration-$secondsLeft)/$totalDuration)*100 : 100 }}%;
                            background:{{ $isRest ? '#60a5fa' : $kColor }}">
                </div>
            </div>
        </div>

    {{-- ═══════════════════════════════════════════════════════ RESULTADO ══ --}}
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
                $colores   = \App\Models\Talisman::COLORES;
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
            <p class="text-xs text-gray-500 mb-4">
                Entre las sombras del camino, un cofre olvidado.
            </p>
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
                        $lootSlug   = $resultado['loot_item_slug'];
                        $lootFusion = $resultado['loot_fusion'] ?? false;
                        $cargaAntes = $resultado['loot_carga_antes'] ?? 0;
                        $cargaDrop  = $resultado['loot_carga_drop'] ?? 0;
                        $cargaDespues = $resultado['loot_carga_despues'] ?? 0;
                        $cargaMax   = $resultado['loot_carga_maxima'] ?? 100;
                    @endphp
                    <p>+ {{ $lootFusion ? 'Fusión' : 'Loot' }}:
                        <strong>{{ $resultado['loot_item_name'] }}</strong>
                        <span class="px-1 rounded"
                            style="background:{{ ($colores[$lootSlug] ?? '#ccc') }}22;
                                    color:{{ $colores[$lootSlug] ?? '#666' }};
                                    border:1px solid {{ ($colores[$lootSlug] ?? '#ccc') }}55">
                            {{ $nombres[$lootSlug] ?? $lootSlug }}
                        </span>
                        @if($lootFusion)
                            <span class="text-blue-500">
                                carga: {{ $cargaDespues }}/{{ $cargaMax }}
                                ({{ $cargaAntes }}+{{ $cargaDrop }}{{ $cargaDespues >= $cargaMax ? ' · max' : '' }})
                            </span>
                        @else
                            <span class="text-gray-400">carga: {{ $cargaDrop }}/{{ $cargaMax }}</span>
                        @endif
                    </p>
                @elseif($resultado['toco_loot'] && !$resultado['loot_item_name'])
                    <p class="text-gray-400">+ Loot: el cofre estaba vacío en ese compartimento.</p>
                @endif
            </div>

        @else
            @php
                $kingdom   = $resultado['kingdom'] ?? $selectedKingdom;
                $kColor    = $colores[$kingdom] ?? '#9ca3af';
                $heroWon   = $resultado['hero_won'];
                $enemy     = $resultado['enemy'];
                $hpLeft    = $resultado['hero_hp_left'];
                $hpMax     = $hero->hp_maximo;
                $hpPct     = $hpMax > 0 ? round(($hpLeft / $hpMax) * 100) : 0;
                $hpColor   = $hpPct > 55 ? 'text-green-600' : ($hpPct > 25 ? 'text-yellow-600' : 'text-red-500');
                $chanceH   = $resultado['chance_heroe_golpea'] ?? '—';
                $chanceE   = $resultado['chance_enemigo_golpea'] ?? '—';
            @endphp

            {{-- Encabezado --}}
            <p class="font-bold mb-3 text-base" style="color:{{ $heroWon ? $kColor : '#dc2626' }}">
                {{ $heroWon
                    ? 'Victoria — ' . ($nombres[$kingdom] ?? $kingdom)
                    : 'Derrota — ' . ($nombres[$kingdom] ?? $kingdom) }}
            </p>

            {{-- Combatientes --}}
            <div class="grid grid-cols-2 gap-3 mb-4 text-xs">
                <div class="border border-gray-200 p-2 bg-gray-50">
                    <p class="font-bold mb-1">{{ $hero->name }}</p>
                    <p>HP: <span class="font-bold {{ $hpColor }}">{{ $hpLeft }}/{{ $hpMax }}</span>
                    <span class="text-gray-400">({{ $hpPct }}%)</span></p>
                    <p>Ataque: {{ $hero->ataque }} · Defensa: {{ $hero->defensa }}</p>
                    <p>Poder (vs {{ $nombres[$kingdom] ?? $kingdom }}):
                    <strong>{{ round($hero->talisman->poderContra($kingdom, $hero)) }}</strong></p>
                    <p>Chance de golpear: <strong>{{ $chanceH }}%</strong></p>
                </div>
                <div class="border p-2 bg-gray-50" style="border-color:{{ $kColor }}44">
                    <p class="font-bold mb-1" style="color:{{ $kColor }}">{{ $enemy['name'] }}</p>
                    <p>HP: <span class="font-bold {{ $heroWon ? 'text-gray-400' : 'text-red-600' }}">
                        {{ $heroWon ? '0' : '?' }}/{{ $enemy['hp'] }}</span></p>
                    <p>Ataque: {{ $enemy['ataque'] }} · Defensa: {{ $enemy['defensa'] }}</p>
                    <p>Poder: <strong>{{ round($enemy['poder']) }}</strong></p>
                    <p>Chance de golpear: <strong>{{ $chanceE }}%</strong></p>
                </div>
            </div>

            {{-- Métricas rápidas --}}
            <div class="grid grid-cols-4 gap-2 mb-4 text-xs text-center">
                <div class="border border-gray-200 p-2">
                    <div class="text-gray-400">Rondas</div>
                    <div class="font-bold text-base">{{ $resultado['rounds'] }}</div>
                </div>
                <div class="border border-gray-200 p-2">
                    <div class="text-gray-400">Oro</div>
                    <div class="font-bold text-base">+{{ $resultado['oro_ganado'] }}</div>
                </div>
                <div class="border border-gray-200 p-2">
                    <div class="text-gray-400">{{ $heroWon ? 'Esencia +' : 'Esencia −' }}</div>
                    <div class="font-bold text-base {{ $heroWon ? 'text-green-600' : 'text-red-500' }}">
                        {{ $heroWon ? '+' . $resultado['esencia_ganada'] : '-' . $resultado['esencia_perdida'] }}
                    </div>
                    <div class="text-gray-400" style="color:{{ $kColor }}">{{ $nombres[$kingdom] ?? $kingdom }}</div>
                </div>
                <div class="border border-gray-200 p-2">
                    <div class="text-gray-400">HP restante</div>
                    <div class="font-bold text-base {{ $hpColor }}">{{ $hpLeft }}</div>
                </div>
            </div>

            {{-- Loot --}}
            @if($resultado['loot_item_name'])
                @php
                    $lootSlug   = $resultado['loot_item_slug'];
                    $lootFusion = $resultado['loot_fusion'] ?? false;
                    $cargaAntes = $resultado['loot_carga_antes'] ?? 0;
                    $cargaDrop  = $resultado['loot_carga_drop'] ?? 0;
                    $cargaDespues = $resultado['loot_carga_despues'] ?? 0;
                    $cargaMax   = $resultado['loot_carga_maxima'] ?? 100;
                @endphp
                <p>+ {{ $lootFusion ? 'Fusión' : 'Loot' }}:
                    <strong>{{ $resultado['loot_item_name'] }}</strong>
                    <span class="px-1 rounded"
                        style="background:{{ ($colores[$lootSlug] ?? '#ccc') }}22;
                                color:{{ $colores[$lootSlug] ?? '#666' }};
                                border:1px solid {{ ($colores[$lootSlug] ?? '#ccc') }}55">
                        {{ $nombres[$lootSlug] ?? $lootSlug }}
                    </span>
                    @if($lootFusion)
                        <span class="text-blue-500">
                            carga: {{ $cargaDespues }}/{{ $cargaMax }}
                            ({{ $cargaAntes }}+{{ $cargaDrop }}{{ $cargaDespues >= $cargaMax ? ' · max' : '' }})
                        </span>
                    @else
                        <span class="text-gray-400">carga: {{ $cargaDrop }}/{{ $cargaMax }}</span>
                    @endif
                </p>
            @endif

            {{-- Log de rondas --}}
            <div class="bg-gray-100 p-3 mb-4 space-y-1 max-h-48 overflow-y-auto text-xs">
                @foreach($resultado['logs'] as $log)
                    <p>{{ $log['narrative_line'] }}</p>
                @endforeach
            </div>

            {{-- Talismán post-combate --}}
            <div class="mb-4 space-y-1">
                @foreach($esencias as $slug => $valor)
                    <div class="flex items-center gap-2">
                        <span class="w-12 text-xs" style="color:{{ $colores[$slug] }}">{{ $nombres[$slug] }}</span>
                        <div class="flex-1 bg-gray-200 h-1.5 rounded">
                            <div class="h-1.5 rounded" style="width:{{ $valor }}%; background:{{ $colores[$slug] }}"></div>
                        </div>
                        <span class="text-xs text-gray-400 w-10 text-right">{{ $valor }}/100</span>
                    </div>
                @endforeach
            </div>
            @if(!$resultado['hero_won'])
                <button wire:click="launchRest" class="bg-gray-700 text-white px-4 py-2 text-sm mr-2">
                    Revivir
                </button>
            @endif
        @endif

        <p class="text-xs text-gray-500 mb-3">Oro total: {{ $hero->oro }}</p>
        
        <button wire:click="backToHub" class="bg-black text-white px-4 py-2 text-sm">
            Volver al Refugio
        </button>
    {{-- ═══════════════════════════════════════════════════════════ MERCADO ══ --}}
    @elseif($phase === 'market')
        @php
            $colores = \App\Models\Talisman::COLORES;
            $nombres = \App\Models\Talisman::NOMBRES;
            $statLabel = [
                'casco'=>'INT','pecho'=>'RES','brazos'=>'FUE',
                'piernas'=>'DES','escudo'=>'DEF','arma'=>'ATQ','amuleto'=>'SUE',
            ];
        @endphp

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

        {{-- Contador de renovación --}}
        <div wire:poll.10000ms="refreshMarket" class="mb-4">
            <p class="text-xs text-gray-300 italic">
                El mercader ordena su mesa...
            </p>
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

                        {{-- Slot --}}
                        <div class="w-14 text-gray-400 text-xs uppercase shrink-0">
                            {{ $item['piece_type'] }}
                        </div>

                        {{-- Info --}}
                        <div class="flex-1 mx-2 leading-tight">
                            <span class="font-semibold text-sm">{{ $item['name'] }}</span>
                            <span class="ml-1 text-xs px-1 rounded"
                                style="background:{{ $c }}22;color:{{ $c }};border:1px solid {{ $c }}55">
                                {{ $item['element_name'] }}
                            </span>
                            <br>
                            <span class="text-xs text-gray-400">
                                {{ $statLabel[$item['piece_type']] }}+{{ $item['stat_bonus_efectivo'] }}
                                &nbsp;·&nbsp;
                                Alin+{{ $item['alignment_bonus_efectivo'] }}
                                &nbsp;·&nbsp;
                                Carga: {{ $item['carga'] }}/{{ $item['carga_maxima'] }}
                            </span>
                        </div>

                        {{-- Precio y acción --}}
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
    @endif
</div>