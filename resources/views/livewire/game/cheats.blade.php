@php
    $colores = \App\Models\Talisman::COLORES;
    $nombres = \App\Models\Talisman::NOMBRES;
    $slots   = ['casco','pecho','brazos','piernas','escudo','arma','amuleto'];
    $equipped = $hero->equippedItems->keyBy('piece_type');
@endphp

<div>
    <div class="flex justify-between items-center mb-4">
        <h2 class="font-bold text-lg text-red-500">⚙ Panel de Desarrollo</h2>
        <button wire:click="backToHub"
                class="text-xs border border-gray-400 px-2 py-1 hover:bg-gray-100">
            ← Refugio
        </button>
    </div>

    @if($cheatMessage)
        <div class="mb-4 px-3 py-2 text-xs border
            {{ str_starts_with($cheatMessage, '✗')
                ? 'border-red-300 bg-red-50 text-red-600'
                : 'border-green-300 bg-green-50 text-green-700' }}">
            {{ $cheatMessage }}
        </div>
    @endif

    {{-- ── Stats ───────────────────────────────────────────────────────────────── --}}
    <div class="mb-5 p-3 border border-gray-200">
        <p class="text-xs text-gray-400 uppercase tracking-wide mb-3">Stats del héroe</p>
        <div class="grid grid-cols-5 gap-2 mb-3">
            @foreach(['fuerza'=>'FUE','resistencia'=>'RES','destreza'=>'DES','inteligencia'=>'INT','suerte'=>'SUE'] as $stat => $lbl)
                <div>
                    <label class="text-xs text-gray-400 block mb-1">{{ $lbl }}</label>
                    <input type="number" wire:model="cheatStats.{{ $stat }}"
                        min="1" max="99"
                        class="w-full border border-gray-300 px-2 py-1 text-sm text-center" />
                </div>
            @endforeach
        </div>
        <div class="flex items-center gap-3">
            <button wire:click="cheatSaveStats"
                    class="text-xs bg-gray-800 text-white px-3 py-1 hover:bg-black">
                Guardar stats
            </button>
            <button wire:click="cheatRestoreHP"
                    class="text-xs border border-gray-400 px-3 py-1 hover:bg-gray-100">
                Restaurar HP
            </button>
            <span class="text-xs text-gray-400">
                HP actual: {{ $hero->hp_actual }}/{{ $hero->hp_maximo }}
            </span>
        </div>
    </div>

    {{-- ── Oro ─────────────────────────────────────────────────────────────────── --}}
    <div class="mb-5 p-3 border border-gray-200">
        <p class="text-xs text-gray-400 uppercase tracking-wide mb-3">Oro</p>
        <div class="flex items-center gap-3">
            <input type="number" wire:model="cheatOro"
                min="0"
                class="border border-gray-300 px-2 py-1 text-sm w-32 text-center" />
            <button wire:click="cheatSaveOro"
                    class="text-xs bg-gray-800 text-white px-3 py-1 hover:bg-black">
                Guardar oro
            </button>
            <span class="text-xs text-gray-400">Actual: {{ $hero->oro }}</span>
        </div>
    </div>

    {{-- ── Esencias ────────────────────────────────────────────────────────────── --}}
    <div class="mb-5 p-3 border border-gray-200">
        <p class="text-xs text-gray-400 uppercase tracking-wide mb-3">Esencias del Talismán</p>
        <div class="grid grid-cols-7 gap-2 mb-3">
            @foreach(\App\Models\Talisman::ELEMENTOS as $slug)
                <div>
                    <label class="text-xs block mb-1" style="color:{{ $colores[$slug] }}">
                        {{ $nombres[$slug] }}
                    </label>
                    <input type="number" wire:model="cheatEsencias.{{ $slug }}"
                        min="0" max="9999"
                        class="w-full border border-gray-300 px-1 py-1 text-sm text-center" />
                </div>
            @endforeach
        </div>
        <button wire:click="cheatSaveEsencias"
                class="text-xs bg-gray-800 text-white px-3 py-1 hover:bg-black">
            Guardar esencias
        </button>
    </div>

    {{-- ── Sellos ──────────────────────────────────────────────────────────────── --}}
    <div class="mb-5 p-3 border border-gray-200">
        <p class="text-xs text-gray-400 uppercase tracking-wide mb-3">
            Sellos (anillo 1) — click para toggle
        </p>
        <div class="flex gap-2 flex-wrap">
            @foreach(\App\Models\Talisman::ELEMENTOS as $slug)
                @php $tieneSello = $hero->hasSeal($slug, 1); @endphp
                <button wire:click="cheatToggleSeal('{{ $slug }}')"
                        class="text-xs px-3 py-1 border rounded transition-all"
                        style="
                            border-color: {{ $colores[$slug] }};
                            background: {{ $tieneSello ? $colores[$slug] : 'transparent' }};
                            color: {{ $tieneSello ? '#fff' : $colores[$slug] }};
                        ">
                    {{ $tieneSello ? '✦' : '○' }} {{ $nombres[$slug] }}
                </button>
            @endforeach
        </div>
        <p class="text-xs text-gray-300 mt-2">
            Sellos totales: {{ $hero->totalSeals() }} · HP máximo: {{ $hero->hp_maximo }}
        </p>
    </div>

    {{-- ── Equipo equipado ────────────────────────────────────────────────────── --}}
    <div class="mb-5 p-3 border border-gray-200">
        <p class="text-xs text-gray-400 uppercase tracking-wide mb-3">Equipo equipado</p>
        <div class="space-y-1">
            @foreach($slots as $slot)
                @php $slotData = $equipped[$slot] ?? null; @endphp
                <div class="flex items-center gap-2 text-xs">
                    <span class="w-14 text-gray-400 uppercase">{{ $slot }}</span>
                    @if($slotData)
                        @php $c = $colores[$slotData->equipment->element->slug] ?? '#9ca3af'; @endphp
                        <span style="color:{{ $c }}">
                            {{ $slotData->equipment->name }}
                        </span>
                        <span class="text-gray-400">
                            carga {{ $slotData->carga }}/{{ $slotData->equipment->carga_maxima }}
                        </span>
                        <button wire:click="cheatUnequipSlot('{{ $slot }}')"
                                class="text-red-400 hover:text-red-600 ml-auto">
                            × quitar
                        </button>
                    @else
                        <span class="text-gray-200 italic">— vacío —</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- ── Agregar equipo al inventario ───────────────────────────────────────── --}}
    <div class="mb-5 p-3 border border-gray-200">
        <p class="text-xs text-gray-400 uppercase tracking-wide mb-3">Agregar pieza al inventario</p>
        <div class="flex gap-2 items-end flex-wrap">
            <div>
                <label class="text-xs text-gray-400 block mb-1">Elemento</label>
                <select wire:model="cheatEquipElement"
                        class="border border-gray-300 px-2 py-1 text-sm">
                    @foreach(\App\Models\Talisman::ELEMENTOS as $slug)
                        <option value="{{ $slug }}">{{ \App\Models\Talisman::NOMBRES[$slug] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-400 block mb-1">Slot</label>
                <select wire:model="cheatEquipSlot"
                        class="border border-gray-300 px-2 py-1 text-sm">
                    @foreach(['casco','pecho','brazos','piernas','escudo','arma','amuleto'] as $s)
                        <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-400 block mb-1">Carga</label>
                <input type="number" wire:model="cheatEquipCarga"
                    min="1" max="100"
                    class="border border-gray-300 px-2 py-1 text-sm w-20 text-center" />
            </div>
            <button wire:click="cheatAddEquip"
                    class="text-xs bg-gray-800 text-white px-3 py-2 hover:bg-black">
                Agregar
            </button>
        </div>
    </div>

    {{-- ── Inventario actual ───────────────────────────────────────────────────── --}}
    @if($hero && $hero->inventory->isNotEmpty())
        <div class="mb-5 p-3 border border-gray-200">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-3">Inventario actual</p>
            <div class="space-y-1">
                @foreach($hero->inventory as $invRow)
                    @php $c = $colores[$invRow->equipment->element->slug] ?? '#9ca3af'; @endphp
                    <div class="flex items-center gap-2 text-xs">
                        <span class="w-14 text-gray-400">{{ $invRow->equipment->piece_type }}</span>
                        <span style="color:{{ $c }}">{{ $invRow->equipment->name }}</span>
                        <span class="text-gray-400">
                            carga {{ $invRow->carga }}/{{ $invRow->equipment->carga_maxima }}
                        </span>
                        <button wire:click="cheatRemoveEquip({{ $invRow->id }})"
                                class="text-red-400 hover:text-red-600 ml-auto">
                            × eliminar
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ── Simulador de combate ────────────────────────────────────────────────── --}}
    <div class="mb-5 p-3 border border-red-200 bg-red-50">
        <p class="text-xs text-red-400 uppercase tracking-wide mb-3">Simulador de combate</p>

        <div class="flex gap-2 items-end flex-wrap mb-3">
            <div>
                <label class="text-xs text-gray-400 block mb-1">Reino</label>
                <select wire:model="simKingdom"
                        class="border border-gray-300 px-2 py-1 text-sm">
                    @foreach(\App\Models\Talisman::ELEMENTOS as $slug)
                        <option value="{{ $slug }}">{{ \App\Models\Talisman::NOMBRES[$slug] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-400 block mb-1">Duración</label>
                <select wire:model="simDuration"
                        class="border border-gray-300 px-2 py-1 text-sm">
                    @foreach([10, 20, 30, 40, 50] as $d)
                        <option value="{{ $d }}">{{ $d }}s</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-400 block mb-1">Batallas</label>
                <input type="number" wire:model="simCount"
                    min="1" max="200"
                    class="border border-gray-300 px-2 py-1 text-sm w-20 text-center" />
            </div>
            <button wire:click="runSimulation"
                    wire:loading.attr="disabled"
                    class="text-xs bg-red-700 text-white px-3 py-2 hover:bg-red-900 disabled:opacity-50">
                <span wire:loading.remove wire:target="runSimulation">Simular</span>
                <span wire:loading wire:target="runSimulation">Calculando...</span>
            </button>
        </div>

        @if(!empty($simResults))
            @php
                $total     = count($simResults);
                $victorias = collect($simResults)->where('outcome', 'victory')->count();
                $derrotas  = collect($simResults)->where('outcome', 'defeat')->count();
                $empates   = collect($simResults)->where('outcome', 'draw')->count();
                $avgRounds = round(collect($simResults)->avg('rounds'), 1);
                $avgHpLeft = round(collect($simResults)->avg('hero_hp_left'), 1);
                $avgEnemyHp = round(collect($simResults)->avg('enemy_hp_ini'), 1);
                $chanceH   = $simResults[0]['chance_h'] ?? '—';
                $chanceE   = $simResults[0]['chance_e'] ?? '—';
            @endphp

            {{-- Resumen --}}
            <div class="grid grid-cols-4 gap-2 mb-3 text-xs text-center">
                <div class="border border-green-200 bg-green-50 p-2 rounded">
                    <div class="text-green-600 font-bold text-base">{{ $victorias }}</div>
                    <div class="text-gray-400">Victorias</div>
                    <div class="text-green-600">{{ round($victorias/$total*100) }}%</div>
                </div>
                <div class="border border-red-200 bg-red-50 p-2 rounded">
                    <div class="text-red-600 font-bold text-base">{{ $derrotas }}</div>
                    <div class="text-gray-400">Derrotas</div>
                    <div class="text-red-500">{{ round($derrotas/$total*100) }}%</div>
                </div>
                <div class="border border-gray-200 bg-gray-50 p-2 rounded">
                    <div class="text-gray-600 font-bold text-base">{{ $empates }}</div>
                    <div class="text-gray-400">Empates</div>
                    <div class="text-gray-500">{{ round($empates/$total*100) }}%</div>
                </div>
                <div class="border border-gray-200 p-2 rounded">
                    <div class="text-gray-700 font-bold text-base">{{ $avgRounds }}</div>
                    <div class="text-gray-400">Rondas prom.</div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-2 mb-3 text-xs text-center">
                <div class="border border-gray-200 p-2 rounded">
                    <div class="text-gray-700 font-bold">{{ $avgHpLeft }}</div>
                    <div class="text-gray-400">HP héroe prom. final</div>
                </div>
                <div class="border border-gray-200 p-2 rounded">
                    <div class="text-gray-700 font-bold">{{ $avgEnemyHp }}</div>
                    <div class="text-gray-400">HP enemigo prom. inicial</div>
                </div>
                <div class="border border-gray-200 p-2 rounded">
                    <div class="text-gray-700 font-bold">{{ $chanceH }}% / {{ $chanceE }}%</div>
                    <div class="text-gray-400">Chance golpe H/E</div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="text-xs w-full border-collapse font-mono" id="sim-table">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="border border-gray-300 px-2 py-1 text-left">#</th>
                            <th class="border border-gray-300 px-2 py-1">Resultado</th>
                            <th class="border border-gray-300 px-2 py-1">Rondas</th>
                            <th class="border border-gray-300 px-2 py-1">HP héroe final</th>
                            <th class="border border-gray-300 px-2 py-1">HP enemigo ini</th>
                            <th class="border border-gray-300 px-2 py-1">Poder enemigo</th>
                            <th class="border border-gray-300 px-2 py-1">ATQ enemigo</th>
                            <th class="border border-gray-300 px-2 py-1">DEF enemigo</th>
                            <th class="border border-gray-300 px-2 py-1">Chance H%</th>
                            <th class="border border-gray-300 px-2 py-1">Chance E%</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($simResults as $row)
                            <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }}
                                {{ $row['outcome'] === 'victory' ? 'text-green-700' :
                                ($row['outcome'] === 'defeat'  ? 'text-red-600'   : 'text-gray-500') }}">
                                <td class="border border-gray-200 px-2 py-0.5">{{ $row['n'] }}</td>
                                <td class="border border-gray-200 px-2 py-0.5 text-center font-bold">
                                    {{ match($row['outcome']) {
                                        'victory' => 'V',
                                        'defeat'  => 'D',
                                        default   => 'E',
                                    } }}
                                </td>
                                <td class="border border-gray-200 px-2 py-0.5 text-center">{{ $row['rounds'] }}</td>
                                <td class="border border-gray-200 px-2 py-0.5 text-center">{{ $row['hero_hp_left'] }}</td>
                                <td class="border border-gray-200 px-2 py-0.5 text-center">{{ $row['enemy_hp_ini'] }}</td>
                                <td class="border border-gray-200 px-2 py-0.5 text-center">{{ $row['enemy_poder'] }}</td>
                                <td class="border border-gray-200 px-2 py-0.5 text-center">{{ $row['enemy_ataque'] }}</td>
                                <td class="border border-gray-200 px-2 py-0.5 text-center">{{ $row['enemy_defensa'] }}</td>
                                <td class="border border-gray-200 px-2 py-0.5 text-center">{{ $row['chance_h'] }}</td>
                                <td class="border border-gray-200 px-2 py-0.5 text-center">{{ $row['chance_e'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
