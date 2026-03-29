<div class="p-6 max-w-2xl mx-auto font-mono text-sm">

    {{-- ═══════════════════════════════════════════════════════ CREAR HÉROE ══ --}}
    @if($phase === 'select')
        @php $heroes = $this->heroesDeEstaIp(); @endphp
        <h1 class="text-xl font-bold mb-4">The Vessel — Farlock's Codex</h1>
        <p class="text-gray-500 mb-4">Buscadores registrados en esta pc:</p>
        <div class="space-y-2 mb-6">
            @foreach($heroes as $h)
                <div class="flex items-center justify-between border border-gray-200 px-3 py-2">
                    <div class="text-sm">
                        <span class="font-bold">{{ $h->name }}</span>
                        <span class="text-gray-400 ml-2">HP {{ $h->hp_actual }}/{{ $h->hp_maximo }} · Oro {{ $h->oro }}</span>
                    </div>
                    <button wire:click="selectHero({{ $h->id }})"
                            class="text-xs bg-black text-white px-2 py-1 hover:bg-gray-700">
                        Continuar
                    </button>
                </div>
            @endforeach
        </div>
        <button wire:click="$set('phase', 'create')"
                class="text-xs border border-gray-400 px-3 py-2 hover:bg-gray-100">
            Crear nuevo Buscador
        </button>
    @elseif($phase === 'create')
        <h1 class="text-xl font-bold mb-4">The Vessel — Farlock's Codex</h1>
        <p class="mb-4 text-gray-500">Un estudioso encontró el Códice. El Talismán lo eligió.</p>

        <input wire:model="heroName" placeholder="Nombre del Buscador (opcional)"
            class="border px-3 py-2 w-full mb-5" />

        @php
            $puntosUsados = array_sum($statsCreacion);
            $puntosLibres = 25 - $puntosUsados;
            $labels = [
                'fuerza' => ['FUE', 'Ataque base en combate'],
                'resistencia' => ['RES', 'Defensa base y HP'],
                'destreza' => ['DES', 'Esquive, doble golpe y crítico'],
                'inteligencia' => ['INT', 'Precios en el mercado'],
                'suerte' => ['SUE', 'Probabilidad de cofres y loot'],
            ];
        @endphp

        <p class="text-xs text-gray-400 uppercase tracking-wide mb-3">
            Distribuí tus puntos —
            <span class="{{ $puntosLibres > 0 ? 'text-yellow-600' : 'text-gray-400' }}">
                {{ $puntosLibres }} disponibles
            </span>
        </p>

        <div class="space-y-2 mb-6">
            @foreach($statsCreacion as $stat => $valor)
                @php [$lbl, $desc] = $labels[$stat]; @endphp
                <div class="flex items-center gap-3 border border-gray-100 px-3 py-2">
                    <div class="w-8 text-xs font-bold text-gray-500">{{ $lbl }}</div>
                    <div class="flex-1 text-xs text-gray-400">{{ $desc }}</div>
                    <button wire:click="bajarStat('{{ $stat }}')"
                            class="w-6 h-6 border border-gray-300 text-gray-500 hover:bg-gray-100
                                {{ $valor <= 1 ? 'opacity-30 cursor-not-allowed' : '' }}">
                        −
                    </button>
                    <span class="w-6 text-center font-bold text-sm
                                {{ $valor === 10 ? 'text-yellow-600' : ($valor === 1 ? 'text-red-400' : 'text-gray-800') }}">
                        {{ $valor }}
                    </span>
                    <button wire:click="subirStat('{{ $stat }}')"
                            class="w-6 h-6 border border-gray-300 text-gray-500 hover:bg-gray-100
                                {{ ($valor >= 10 || $puntosLibres <= 0) ? 'opacity-30 cursor-not-allowed' : '' }}">
                        +
                    </button>
                </div>
            @endforeach
        </div>

        <button wire:click="createHero" class="bg-black text-white px-4 py-2">
            Comenzar
        </button>

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
                <button wire:click="logout"
                        class="text-xs text-gray-500 border border-gray-300 px-2 py-1 hover:bg-gray-50">
                    Salir
                </button>
                <!-- <button wire:click="resetGame"
                        wire:confirm="¿Reiniciar partida? El héroe y todos sus datos se eliminarán."
                        class="text-xs text-red-500 border border-red-300 px-2 py-1 hover:bg-red-50">
                    Reiniciar
                </button> -->
            </div>
        </div>

        <p>Buscador: <strong>{{ $hero->name }}</strong>
           &nbsp;·&nbsp; HP: {{ $hero->hp_actual }}/{{ $hero->hp_maximo }}
           &nbsp;·&nbsp; Oro: {{ $hero->oro }}
           @if($hero->totalSeals() > 0)
               &nbsp;·&nbsp; <span class="text-yellow-600">Sellos: {{ $hero->totalSeals() }}</span>
           @endif
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
            $poderTotal = $hero->talisman->poderTotal();
        @endphp
        <div class="mb-5 p-3 border border-gray-200 bg-gray-50">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Talismán — Esencias (Poder Total: {{ $poderTotal }})</p>
            <div class="space-y-1 mb-3">
                @foreach($esenciasFarmeadas as $slug => $valorFarmeado)
                    @php
                        $color    = $colores[$slug];
                        $efectivo = $esenciasEfectivas[$slug];
                        $bonus    = $efectivo - $valorFarmeado;
                        $barBase  = $max > 0 ? ($valorFarmeado / $max) * 100 : 0;
                        $barBonus = $max > 0 ? ($bonus / $max) * 100 : 0;
                        $tieneSello = $hero->hasSeal($slug, 1);
                    @endphp
                    <div class="flex items-center gap-2">
                        <span class="w-12 text-xs" style="color:{{ $color }}">{{ $nombres[$slug] }}</span>
                        <div class="flex-1 bg-gray-200 h-2 rounded overflow-hidden flex">
                            <div class="h-2" style="width:{{ $barBase }}%; background:{{ $color }}"></div>
                            <div class="h-2" style="width:{{ $barBonus }}%; background:{{ $color }}; opacity:0.35"></div>
                        </div>
                        <span class="text-xs text-gray-500 w-24 text-right">
                            @if($tieneSello)
                                <span style="color:{{ $color }}">✦</span>
                            @endif
                            {{ $valorFarmeado }}/{{ $max }}
                            @if($bonus > 0)
                                <span style="color:{{ $color }}">(+{{ $bonus }})</span>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>

            {{-- Arma y escudo activos --}}
            @php
                $armaSlot   = $hero->equippedItems->first(fn($e) => $e->piece_type === 'arma');
                $escudoSlot = $hero->equippedItems->first(fn($e) => $e->piece_type === 'escudo');
            @endphp
            <div class="grid grid-cols-2 gap-2 mt-1">
                <div class="text-xs">
                    <span class="text-gray-400 uppercase tracking-wide">Arma</span><br>
                    @if($armaSlot)
                        <span style="color:{{ $colores[$armaSlot->equipment->element->slug] ?? '#9ca3af' }}">
                            ● {{ $armaSlot->equipment->element->name }}
                        </span>
                        <span class="text-gray-500"> · {{ $armaSlot->equipment->name }} · ATQ+{{ $armaSlot->statEfectivo() }}</span>
                    @else
                        <span class="text-gray-300 italic">— sin arma —</span>
                    @endif
                </div>
                <div class="text-xs">
                    <span class="text-gray-400 uppercase tracking-wide">Escudo</span><br>
                    @if($escudoSlot)
                        <span style="color:{{ $colores[$escudoSlot->equipment->element->slug] ?? '#9ca3af' }}">
                            ● {{ $escudoSlot->equipment->element->name }}
                        </span>
                        <span class="text-gray-500"> · {{ $escudoSlot->equipment->name }} · DEF+{{ $escudoSlot->statEfectivo() }}</span>
                    @else
                        <span class="text-gray-300 italic">— sin escudo —</span>
                    @endif
                </div>
            </div>
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
                    @if($hero->hasSeal($slug, 1))
                        <span class="block text-xs" style="color:{{ $info['color'] }}">✦</span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- Guardián disponible --}}
        @if($this->guardianDisponible($selectedKingdom))
            @php $kColor = \App\Livewire\GameCore::KINGDOMS[$selectedKingdom]['color'] ?? '#111'; @endphp
            <div class="mb-4 p-3 border-2 rounded"
                 style="border-color:{{ $kColor }}; background:{{ $kColor }}11">
                <p class="text-xs font-bold mb-1" style="color:{{ $kColor }}">
                    ⚔ Guardián del reino disponible
                </p>
                <p class="text-xs text-gray-500 mb-2">
                    La esencia del reino está al máximo. El guardián espera. Misión de 60 segundos.
                </p>
                <button wire:click="launchGuardian('{{ $selectedKingdom }}')"
                        class="text-xs text-white px-3 py-2 font-bold"
                        style="background:{{ $kColor }}">
                    Enfrentar al Guardián — {{ \App\Livewire\GameCore::KINGDOMS[$selectedKingdom]['name'] }}
                </button>
            </div>
        @endif

        {{-- Selector de duración --}}
        @php
            $duraciones    = $this->duracionesParaKingdom($selectedKingdom);
            $kColor        = \App\Livewire\GameCore::KINGDOMS[$selectedKingdom]['color'] ?? '#111';
            $esenciaActual = $hero->talisman->getEsencia($selectedKingdom);
        @endphp
        <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Duración de expedición</p>
        <div class="flex gap-2 mb-1">
            @foreach($duraciones as $opcion)
                @php
                    $activo    = $selectedDuration === $opcion['duracion'];
                    $bloqueado = !$opcion['desbloqueada'];
                @endphp
                @if($bloqueado)
                    <div class="flex-1 text-center border border-dashed border-gray-200 py-2 px-1 rounded opacity-50 cursor-not-allowed"
                         title="Requiere {{ $opcion['esencia_requerida'] }} de esencia">
                        <div class="text-xs text-gray-400 font-bold">{{ $opcion['duracion'] }}s</div>
                        <div class="text-xs text-gray-300 leading-none mt-0.5">
                            ⊘ {{ $opcion['esencia_requerida'] }}
                        </div>
                    </div>
                @else
                    <button wire:click="selectDuration({{ $opcion['duracion'] }})"
                            class="flex-1 text-center border py-2 px-1 rounded transition-all"
                            style="
                                border-color: {{ $activo ? $kColor : '#e5e7eb' }};
                                background: {{ $activo ? $kColor.'22' : 'white' }};
                                color: {{ $activo ? $kColor : '#6b7280' }};
                            ">
                        <div class="text-xs font-bold">{{ $opcion['duracion'] }}s</div>
                        <div class="text-xs leading-none mt-0.5"
                             style="color: {{ $activo ? $kColor : '#d1d5db' }}">
                            {{ $opcion['esencia_requerida'] > 0 ? '≥'.$opcion['esencia_requerida'] : 'libre' }}
                        </div>
                    </button>
                @endif
            @endforeach
        </div>
        <p class="text-xs text-gray-300 mb-4">
            Esencia {{ \App\Models\Talisman::NOMBRES[$selectedKingdom] }} farmeada:
            <strong class="text-gray-400">{{ $esenciaActual }}/100</strong>
        </p>

        <button wire:click="launchExpedition"
                class="text-white px-4 py-2 text-sm"
                style="background: {{ $kColor }}">
            Partir — Reino de {{ \App\Livewire\GameCore::KINGDOMS[$selectedKingdom]['name'] }} · {{ $selectedDuration }}s
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

    {{-- ═══════════════════════════════════════════════════════ ESPERANDO ══ --}}
    @elseif($phase === 'waiting')
        <div wire:poll.1000ms="tick">
            <h2 class="font-bold text-lg mb-2">— En curso —</h2>
            @php
                $exp           = \App\Models\Expedition::find($expeditionId);
                $totalDuration = $exp?->duration_seconds ?? $selectedDuration;
                $isRest        = $exp?->event_type === 'rest';
                $isGuardian    = $exp?->event_type === 'guardian';
                $kName         = \App\Livewire\GameCore::KINGDOMS[$selectedKingdom]['name'] ?? '';
                $kColor        = \App\Livewire\GameCore::KINGDOMS[$selectedKingdom]['color'] ?? '#111';
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
                $kingdom     = $resultado['kingdom'] ?? $selectedKingdom;
                $kColor      = $colores[$kingdom] ?? '#9ca3af';
                $heroWon     = $resultado['hero_won'];
                $enemy       = $resultado['enemy'];
                $hpLeft      = $resultado['hero_hp_left'];
                $hpMax       = $hero->hp_maximo;
                $hpPct       = $hpMax > 0 ? round(($hpLeft / $hpMax) * 100) : 0;
                $hpColor     = $hpPct > 55 ? 'text-green-600' : ($hpPct > 25 ? 'text-yellow-600' : 'text-red-500');
                $chanceH     = $resultado['chance_heroe_golpea'] ?? '—';
                $chanceE     = $resultado['chance_enemigo_golpea'] ?? '—';
                $isGuardian  = $resultado['is_guardian'] ?? false;
                $selloObt    = $resultado['sello_obtenido'] ?? false;
            @endphp

            {{-- Encabezado --}}
            @if($isGuardian && $heroWon)
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
            @elseif($isGuardian && !$heroWon)
                <p class="font-bold mb-3 text-base text-red-600">
                    Derrota — el Guardián de {{ $nombres[$kingdom] ?? $kingdom }} resiste.
                </p>
            @else
                <p class="font-bold mb-3 text-base" style="color:{{ $heroWon ? $kColor : '#dc2626' }}">
                    {{ $heroWon
                        ? 'Victoria — ' . ($nombres[$kingdom] ?? $kingdom)
                        : 'Derrota — '  . ($nombres[$kingdom] ?? $kingdom) }}
                </p>
            @endif

            {{-- Combatientes --}}
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
                    <p>HP: <span class="font-bold {{ $heroWon ? 'text-gray-400' : 'text-red-600' }}">
                        {{ $heroWon ? '0' : '?' }}/{{ $enemy['hp'] }}</span></p>
                    <p>Ataque: {{ $enemy['ataque'] }} · Defensa: {{ $enemy['defensa'] }}</p>
                    <p>Poder: <strong>{{ round($enemy['poder']) }}</strong></p>
                    <p>Chance de golpear: <strong>{{ $chanceE }}%</strong></p>
                </div>
            </div>

            {{-- Métricas --}}
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
                    <div style="color:{{ $kColor }}">{{ $nombres[$kingdom] ?? $kingdom }}</div>
                </div>
                <div class="border border-gray-200 p-2">
                    <div class="text-gray-400">HP restante</div>
                    <div class="font-bold text-base {{ $hpColor }}">{{ $hpLeft }}</div>
                </div>
            </div>

            {{-- Loot (solo combates normales) --}}
            @if(!$isGuardian && ($resultado['loot_item_name'] ?? null))
                @php
                    $lootSlug     = $resultado['loot_item_slug'];
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
                            <span class="text-blue-500">carga: {{ $cargaDespues }}/{{ $cargaMax }} ({{ $cargaAntes }}+{{ $cargaDrop }})</span>
                        @else
                            <span class="text-gray-400">carga: {{ $cargaDrop }}/{{ $cargaMax }}</span>
                        @endif
                    </p>
                </div>
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

            @if(!$heroWon)
                <div class="flex gap-2 mb-3">
                    <button wire:click="launchRest"
                            class="bg-gray-700 text-white px-4 py-2 text-sm">
                        Descansar (10s)
                    </button>
                    <button wire:click="backToHub"
                            class="bg-black text-white px-4 py-2 text-sm">
                        Volver al Refugio
                    </button>
                </div>
            @endif

        @endif

        <p class="text-xs text-gray-500 mb-3">Oro total: {{ $hero->oro }}</p>

        @if($heroWon ?? true)
            <button wire:click="backToHub" class="bg-black text-white px-4 py-2 text-sm">
                Volver al Refugio
            </button>
        @endif

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
    @endif
</div>