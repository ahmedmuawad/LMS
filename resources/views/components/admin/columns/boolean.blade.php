@props(['on' => false])
<span @class(['inline-flex items-center gap-1.5 text-sm', 'text-success' => $on, 'text-subtle' => ! $on])>
    <span aria-hidden="true">{{ $on ? '✓' : '✕' }}</span>
    <span class="sr-only">{{ $on ? __('نعم') : __('لا') }}</span>
</span>
