<x-layouts.app :title="tenant('name')">
    <main id="main" class="min-h-screen grid place-items-center p-6">
        <div class="text-center max-w-md">
            <h1 class="text-2xl font-bold mb-2">{{ tenant('name') }}</h1>
            <p class="text-muted text-sm">
                {{ __('منصّتك جاهزة. ابدأ بإضافة أول كورس من لوحة التحكم.') }}
            </p>
            <p class="text-2xs text-subtle font-mono mt-4">
                {{ tenant('platform_mode') }} · {{ tenant('currency') }} · {{ tenant('status') }}
            </p>
        </div>
    </main>
</x-layouts.app>
