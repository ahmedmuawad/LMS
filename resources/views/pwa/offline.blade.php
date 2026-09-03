<x-layouts.app :title="__('بلا اتصال')">
<main id="main" class="min-h-screen grid place-items-center px-4">
    <div class="text-center max-w-[38ch]">
        <div class="size-16 rounded-2xl grid place-items-center mx-auto mb-4 text-2xl bg-surface-sunken text-subtle" aria-hidden="true">⚡</div>
        <h1 class="text-2xl font-bold mb-2">{{ __('لا اتصال بالإنترنت') }}</h1>
        <p class="text-muted leading-relaxed mb-6">
            {{ __('تعذّر الوصول إلى الموقع. تفقّد اتصالك ثم أعد المحاولة — ما فتحته قبل قليل ما زال متاحاً.') }}
        </p>
        <button type="button" onclick="location.reload()"
                class="inline-flex items-center justify-center min-h-11 px-5 rounded-md bg-primary text-primary-on font-semibold">
            {{ __('أعد المحاولة') }}
        </button>
    </div>
</main>
</x-layouts.app>
