@props(['class' => ''])
<div aria-hidden="true" {{ $attributes->merge(['class' =>
    'rounded-sm bg-surface-sunken motion-safe:animate-pulse h-3 '.$class]) }}></div>
