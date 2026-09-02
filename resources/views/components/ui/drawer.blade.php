@props(['name', 'title' => null])
<div x-data="{ open: false }"
     x-on:open-drawer.window="if ($event.detail === @js($name)) open = true"
     x-on:close-drawer.window="if ($event.detail === @js($name)) open = false"
     @keydown.escape.window="open = false" x-show="open" x-cloak
     class="fixed inset-0 z-50" role="dialog" aria-modal="true" @if($title) aria-label="{{ $title }}" @endif>
    <div x-show="open" x-transition.opacity class="absolute inset-0" style="background: var(--sem-overlay)" @click="open = false"></div>
    <div x-show="open" x-trap.noscroll="open"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="translate-x-full rtl:-translate-x-full"
         x-transition:enter-end="translate-x-0"
         class="absolute inset-y-0 end-0 w-full max-w-md bg-surface-raised border-s border-line shadow-xl flex flex-col">
        <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-line">
            <h2 class="text-base font-bold">{{ $title }}</h2>
            <button type="button" @click="open = false" class="size-8 grid place-items-center rounded-md text-muted hover:bg-surface-sunken hover:text-content transition-colors" aria-label="{{ __('إغلاق') }}">✕</button>
        </div>
        <div class="p-5 overflow-y-auto flex-1">{{ $slot }}</div>
        @isset($footer)<div class="flex justify-end gap-2 px-5 py-4 border-t border-line bg-surface-sunken">{{ $footer }}</div>@endisset
    </div>
</div>
