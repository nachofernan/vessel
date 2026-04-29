@php
    $sheet    = $hero->statSheet();
    $esencias = $hero->talisman->todasLasEsencias();
    $colores  = \App\Models\Talisman::COLORES;
    $nombres  = \App\Models\Talisman::NOMBRES;
    $kingdoms = \App\Models\Talisman::ELEMENTOS;
@endphp

<div>
    <div class="flex justify-between items-start mb-3">
        <h2 class="font-bold text-lg">— Refugio —</h2>
        <div class="flex gap-2">
            <button wire:click="goTo('cheats')"
                    class="text-xs border border-red-300 text-red-400 px-2 py-1 hover:bg-red-50">
                Dev
            </button>
            <button wire:click="goTo('market')"
                    class="text-xs border border-gray-400 px-2 py-1 hover:bg-gray-100">
                Mercado
            </button>
            <button wire:click="goTo('inventory')"
                    class="text-xs border border-gray-400 px-2 py-1 hover:bg-gray-100">
                Equipamiento
            </button>
            <button wire:click="logout"
                    class="text-xs text-gray-500 border border-gray-300 px-2 py-1 hover:bg-gray-50">
                Salir
            </button>
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
        $max        = \App\Models\Talisman::MAX_ESENCIA;
        $poderBase  = array_sum(array_map(fn($v) => (int)$v, $esenciasFarmeadas));
        $poderTotal = $hero->talisman->poderTotal($hero); // con equipo
    @endphp
    <div class="mb-5 p-3 border border-gray-200 bg-gray-50">
        <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">
            Talismán — Esencias (Poder: {{ $poderBase }} / {{ $poderTotal }})
        </p>
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
                    <div class="flex-1 bg-gray-200 h-2 rounded overflow-hidden">
                        <div class="h-2 rounded" style="width:{{ $barBase }}%; background:{{ $color }}"></div>
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
        @foreach($kingdoms as $slug)
            @php $info = ['name' => $nombres[$slug], 'color' => $colores[$slug]]; @endphp
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
        @php $kColor = $colores[$selectedKingdom] ?? '#111'; @endphp
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
                Enfrentar al Guardián — {{ $nombres[$selectedKingdom] }}
            </button>
        </div>
    @endif

    {{-- Selector de duración --}}
    @php
        $duraciones    = $this->duracionesParaKingdom($selectedKingdom);
        $kColor        = $colores[$selectedKingdom] ?? '#111';
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
        Esencia {{ $nombres[$selectedKingdom] }} farmeada:
        <strong class="text-gray-400">{{ $esenciaActual }}/100</strong>
    </p>

    <button wire:click="launchExpedition"
            class="text-white px-4 py-2 text-sm"
            style="background: {{ $kColor }}">
        Partir — Reino de {{ $nombres[$selectedKingdom] }} · {{ $selectedDuration }}s
    </button>
</div>
