@props(['steps' => [], 'current' => 0])
<ol {{ $attributes->merge(['class' => 'flex items-center gap-2 overflow-x-auto pb-1']) }}>
    @foreach($steps as $i => $label)
        <li class="flex items-center gap-2 shrink-0" @if($i === $current) aria-current="step" @endif>
            <span @class([
                'size-7 rounded-full grid place-items-center text-xs font-semibold font-mono shrink-0',
                'bg-completed text-status-on' => $i < $current,
                'bg-primary text-primary-on'  => $i === $current,
                'bg-surface-sunken text-subtle' => $i > $current,
            ]) aria-hidden="true">{{ $i < $current ? '✓' : $i + 1 }}</span>
            <span @class(['text-sm whitespace-nowrap', 'font-semibold' => $i === $current, 'text-subtle' => $i > $current])>{{ $label }}</span>
            @if($i < count($steps) - 1)<span class="w-6 h-px bg-line-strong shrink-0" aria-hidden="true"></span>@endif
        </li>
    @endforeach
</ol>
