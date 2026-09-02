@props(['title' => null, 'subtitle' => null, 'padding' => true])
<div {{ $attributes->merge(['class' => 'surface-card overflow-hidden']) }}>
    @if($title || isset($header))
        <div class="flex items-start justify-between gap-4 px-5 pt-4 pb-3 border-b border-line">
            <div class="min-w-0">
                @if($title)<h3 class="text-base font-bold truncate">{{ $title }}</h3>@endif
                @if($subtitle)<p class="text-xs text-subtle mt-0.5">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="flex items-center gap-2 shrink-0">{{ $actions }}</div>@endisset
        </div>
    @endif
    <div class="{{ $padding ? 'p-5' : '' }}">{{ $slot }}</div>
    @isset($footer)
        <div class="px-5 py-3 border-t border-line bg-surface-sunken">{{ $footer }}</div>
    @endisset
</div>
