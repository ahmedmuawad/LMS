@props(['label', 'value', 'delta' => null, 'trend' => null])
<div {{ $attributes->merge(['class' => 'surface-card p-4']) }}>
    <div class="text-xs text-subtle mb-1">{{ $label }}</div>
    <div class="font-mono text-2xl font-medium leading-tight tabular">{{ $value }}</div>
    @if($delta)
        <div @class([
            'text-xs mt-1 flex items-center gap-1',
            'text-success' => $trend === 'up',
            'text-danger'  => $trend === 'down',
            'text-subtle'  => ! in_array($trend, ['up', 'down'], true),
        ])>
            @if($trend === 'up')<span aria-hidden="true">▲</span>@elseif($trend === 'down')<span aria-hidden="true">▼</span>@endif
            {{ $delta }}
        </div>
    @endif
</div>
