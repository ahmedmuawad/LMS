@props(['tone' => 'neutral', 'icon' => null, 'pulse' => false])
@php
    $tones = [
        'neutral'  => 'bg-surface-sunken text-muted',
        'primary'  => 'bg-primary-subtle text-primary',
        'accent'   => 'bg-accent-subtle text-accent-text',
        'success'  => 'bg-success-subtle text-success',
        'warning'  => 'bg-warning-subtle text-warning',
        'danger'   => 'bg-danger-subtle text-danger',
        'info'     => 'bg-info-subtle text-info',
        'live'     => 'bg-danger-subtle text-live',
        'locked'   => 'bg-surface-sunken text-locked',
    ];
@endphp
<span {{ $attributes->merge(['class' =>
    'inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-0.5 rounded-full leading-6 '
    .($tones[$tone] ?? $tones['neutral'])]) }}>
    @if($pulse)
        <span class="size-[7px] rounded-full bg-current motion-safe:animate-pulse" aria-hidden="true"></span>
    @elseif($icon)
        <span aria-hidden="true">{{ $icon }}</span>
    @endif
    {{ $slot }}
</span>
