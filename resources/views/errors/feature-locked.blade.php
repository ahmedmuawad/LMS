@php
    $labels = [
        'gamification' => 'التحفيز والشارات',
        'community' => 'المجتمع والنقاشات',
        'affiliates' => 'التسويق بالعمولة',
        'page_builder' => 'محرّر الصفحات',
        'custom_css' => 'CSS مخصّص',
        'custom_domain' => 'النطاق الخاص',
        'blog' => 'المدوّنة',
        'services_module' => 'الخدمات والحجوزات',
        'inventory' => 'المخزون',
        'recharge_codes' => 'أكواد الشحن',
        'multi_instructor' => 'تعدّد المدرّسين',
        'center_management' => 'إدارة المجموعات',
        'center_finance' => 'الأقساط والمالية',
        'parent_portal' => 'بوابة وليّ الأمر',
    ];

    $names = collect($features)->map(fn (string $f): string => __($labels[$f] ?? $f))->implode(__(' أو '));
@endphp

<x-layouts.app :title="__('ميزة خارج باقتك')">
<x-site.header />

<main id="main" class="max-w-[560px] mx-auto px-4 sm:px-6 py-16 sm:py-24 text-center">

    <span class="inline-grid place-items-center w-14 h-14 rounded-full bg-warning-subtle text-warning text-2xl mb-5"
          aria-hidden="true">⛨</span>

    <h1 class="text-xl sm:text-2xl font-extrabold mb-3">{{ __(':feature غير متاحة في باقتك', ['feature' => $names]) }}</h1>

    {{--
        السبب لا الرفض: «ممنوع» تجعل صاحب الحساب يظنّ عطلاً في
        منصّته، و«خارج باقتك» تجعله يعرف أن أمامه باباً.
    --}}
    <p class="text-sm text-muted leading-relaxed mb-8">
        {{ __('الميزة مبنيّة وجاهزة — وباقتك الحالية لا تشملها. رقِّ باقتك لتفتحها فوراً بلا إعداد ولا انتظار.') }}
    </p>

    <div class="flex flex-wrap gap-3 justify-center">
        <x-ui.button :href="url('/admin/billing')">{{ __('ترقية الباقة') }}</x-ui.button>
        <x-ui.button variant="secondary" :href="url('/admin/dashboard')">{{ __('عودة إلى اللوحة') }}</x-ui.button>
    </div>

</main>

<x-site.footer />
</x-layouts.app>
