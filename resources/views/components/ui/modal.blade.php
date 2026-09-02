@props(['name', 'title' => null, 'size' => 'md'])
@php $sizes = ['sm' => 'max-w-sm', 'md' => 'max-w-lg', 'lg' => 'max-w-2xl', 'xl' => 'max-w-4xl']; @endphp
<div x-data="{ open: false }"
     x-on:open-modal.window="if ($event.detail === @js($name)) open = true"
     x-on:close-modal.window="if ($event.detail === @js($name)) open = false"
     @keydown.escape.window="open = false"
     x-show="open" x-cloak
     class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
     role="dialog" aria-modal="true" @if($title) aria-label="{{ $title }}" @endif>
    <div x-show="open" x-transition.opacity class="absolute inset-0" style="background: var(--sem-overlay)" @click="open = false"></div>
    <div x-show="open" x-trap.noscroll="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         class="relative w-full {{ $sizes[$size] ?? $sizes['md'] }} bg-surface-raised border border-line rounded-t-2xl sm:rounded-xl shadow-xl max-h-[90dvh] flex flex-col">
        @if($title)
            <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-line">
                <h2 class="text-base font-bold">{{ $title }}</h2>
                <button type="button" @click="open = false" class="size-8 grid place-items-center rounded-md text-muted hover:bg-surface-sunken hover:text-content transition-colors" aria-label="{{ __('إغلاق') }}">✕</button>
            </div>
        @endif
        <div class="p-5 overflow-y-auto">{{ $slot }}</div>
        @isset($footer)
            <div class="flex justify-end gap-2 px-5 py-4 border-t border-line bg-surface-sunken rounded-b-2xl sm:rounded-b-xl">{{ $footer }}</div>
        @endisset
    </div>
</div>
