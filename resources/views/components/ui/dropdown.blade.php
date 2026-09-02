@props(['align' => 'end', 'width' => 'w-56'])
<div x-data="{ open: false }" @keydown.escape.window="open = false" class="relative inline-block">
    <div @click="open = !open" :aria-expanded="open ? 'true' : 'false'" aria-haspopup="true">{{ $trigger }}</div>
    <div x-show="open" x-cloak x-trap.noscroll.noautofocus="open" @click.outside="open = false"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="absolute z-40 mt-1 {{ $width }} bg-surface-raised border border-line rounded-lg shadow-lg p-1 {{ $align === 'end' ? 'end-0' : 'start-0' }}"
         role="menu">
        {{ $slot }}
    </div>
</div>
