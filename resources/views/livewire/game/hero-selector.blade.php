<div>
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

        <div class="flex gap-2">
            <button wire:click="createHero" class="bg-black text-white px-4 py-2">
                Comenzar
            </button>
            <button wire:click="$set('phase', 'select')" class="border border-gray-400 px-4 py-2">
                Volver
            </button>
        </div>
    @endif
</div>
