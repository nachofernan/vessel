<div class="p-6 max-w-2xl mx-auto font-mono text-sm">
    @if($phase === 'select' || $phase === 'create')
        <livewire:game.hero-selector :phase="$phase" />
    @elseif($phase === 'hub')
        <livewire:game.hub :heroId="$heroId" />
    @elseif($phase === 'inventory')
        <livewire:game.inventory :heroId="$heroId" />
    @elseif($phase === 'market')
        <livewire:game.market :heroId="$heroId" />
    @elseif($phase === 'waiting' || $phase === 'result')
        <livewire:game.expedition :heroId="$heroId" :expeditionId="$expeditionId" :kingdom="$selectedKingdom" />
    @elseif($phase === 'cheats')
        <livewire:game.cheats :heroId="$heroId" />
    @endif
</div>
