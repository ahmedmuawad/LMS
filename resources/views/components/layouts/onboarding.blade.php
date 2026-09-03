@props(['title', 'step', 'wizard'])
<x-layouts.app :title="$title">
<div class="min-h-screen flex flex-col">
    <header class="border-b border-line bg-surface">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-4">
            <span class="font-display font-extrabold text-lg">{{ __('تهيئة منصّتك') }}</span>
            <x-ui.theme-toggle />
        </div>
    </header>

    <div class="max-w-3xl w-full mx-auto px-4 sm:px-6 py-6 flex-1">
        <div class="mb-8 overflow-x-auto">
            <x-ui.steps :steps="array_column($wizard->stepLabels(), 'label')"
                        :current="$wizard->stepIndex($step)" />
        </div>

        {{ $slot }}
    </div>
</div>
</x-layouts.app>
