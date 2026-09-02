@props(['current' => 1, 'last' => 1, 'url' => '?page='])
@if($last > 1)
<nav aria-label="{{ __('ترقيم الصفحات') }}" {{ $attributes->merge(['class' => 'flex items-center justify-between gap-3 flex-wrap']) }}>
    <p class="text-xs text-subtle">{{ __('صفحة') }} <span class="font-mono">{{ $current }}</span> {{ __('من') }} <span class="font-mono">{{ $last }}</span></p>
    <ul class="flex items-center gap-1">
        <li>
            <a href="{{ $current > 1 ? $url.($current - 1) : '#' }}" @if($current <= 1) aria-disabled="true" tabindex="-1" @endif
               class="grid place-items-center size-9 rounded-md text-sm border border-line-strong transition-colors {{ $current <= 1 ? 'opacity-40 pointer-events-none' : 'hover:bg-surface-sunken' }}"
               aria-label="{{ __('السابق') }}"><span class="flip-rtl" aria-hidden="true">←</span></a>
        </li>
        @foreach(collect(range(max(1, $current - 2), min($last, $current + 2))) as $p)
            <li>
                <a href="{{ $url.$p }}" @if($p === $current) aria-current="page" @endif
                   class="grid place-items-center size-9 rounded-md text-sm font-medium border transition-colors tabular
                          {{ $p === $current ? 'bg-primary text-primary-on border-primary' : 'border-line-strong hover:bg-surface-sunken' }}">{{ $p }}</a>
            </li>
        @endforeach
        <li>
            <a href="{{ $current < $last ? $url.($current + 1) : '#' }}" @if($current >= $last) aria-disabled="true" tabindex="-1" @endif
               class="grid place-items-center size-9 rounded-md text-sm border border-line-strong transition-colors {{ $current >= $last ? 'opacity-40 pointer-events-none' : 'hover:bg-surface-sunken' }}"
               aria-label="{{ __('التالي') }}"><span class="flip-rtl" aria-hidden="true">→</span></a>
        </li>
    </ul>
</nav>
@endif
