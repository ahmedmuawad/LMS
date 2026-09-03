@props(['tone' => 'neutral', 'text' => null])
<x-ui.badge :tone="$tone">{{ $text ?: '—' }}</x-ui.badge>
