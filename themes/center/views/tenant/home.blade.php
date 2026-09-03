{{-- عرض خاص بثيم السنتر: يسبق عرض النواة في ترتيب البحث --}}
<x-layouts.app :title="tenant('name')">
    <main id="main" class="min-h-screen grid place-items-center p-6">
        <div class="text-center max-w-md">
            <p class="text-xs text-accent-text font-semibold mb-2">{{ __('سنتر تعليمي') }}</p>
            <h1 class="text-2xl font-bold mb-2">{{ tenant('name') }}</h1>
            <p class="text-muted text-sm">
                {{ __('جدول الحصص والحضور والأقساط — كلها من مكان واحد.') }}
            </p>
        </div>
    </main>
</x-layouts.app>
