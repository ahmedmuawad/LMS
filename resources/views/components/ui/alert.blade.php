@props(['tone' => 'info', 'title' => null, 'icon' => null])
@php
    $tones = [
        'success' => ['bg-success-subtle text-success border-success', '✓'],
        'warning' => ['bg-warning-subtle text-warning border-warning', '⚠'],
        'danger'  => ['bg-danger-subtle text-danger border-danger', '✕'],
        'info'    => ['bg-info-subtle text-info border-info', 'ℹ'],
    ];
    [$cls, $defaultIcon] = $tones[$tone] ?? $tones['info'];
@endphp
<div role="{{ $tone === 'danger' ? 'alert' : 'status' }}"
     {{ $attributes->merge(['class' => 'flex gap-3 items-start px-4 py-3 rounded-md text-sm border-s-[3px] '.$cls]) }}>
    <span aria-hidden="true" class="shrink-0 leading-6">{{ $icon ?? $defaultIcon }}</span>
    <div class="min-w-0">
        @if($title)<strong class="block mb-0.5">{{ $title }}</strong>@endif
        <div class="opacity-95">{{ $slot }}</div>
    </div>
</div>
