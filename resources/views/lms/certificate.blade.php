<x-layouts.app :title="__('التحقق من شهادة')">
<x-site.header />

<main id="main" class="max-w-[700px] mx-auto px-4 sm:px-6 py-12">
    <x-ui.page-header :title="__('التحقق من شهادة')"
                      :subtitle="__('الكود المُدخل: :code', ['code' => $code])" />

    @if($certificate === null)
        <x-ui.card>
            <x-ui.empty :title="__('لا شهادة بهذا الكود')" tone="danger" icon="✕">
                {{ __('راجع الكود كما هو مكتوب على الشهادة. الفراغات والشرطات جزء منه.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <x-ui.card>
            @if($valid)
                <x-ui.alert tone="success" :title="__('شهادة سارية')" class="mb-4">
                    {{ __('هذه الشهادة صادرة من هذه المنصة ولم تُلغَ.') }}
                </x-ui.alert>
            @else
                <x-ui.alert tone="danger" :title="__('شهادة غير سارية')" class="mb-4">
                    {{ $certificate->revoked_at !== null
                        ? __('أُلغيت هذه الشهادة في :date.', ['date' => $certificate->revoked_at->format('Y-m-d')])
                        : __('انتهت صلاحية هذه الشهادة في :date.', ['date' => $certificate->expires_at?->format('Y-m-d')]) }}
                </x-ui.alert>
            @endif

            <x-ui.description-list :items="array_filter([
                __('الكود') => $certificate->code,
                __('الطالب') => $certificate->data['student'] ?? $certificate->user?->name,
                __('الكورس') => $certificate->course?->title,
                __('المدرّس') => $certificate->data['instructor'] ?? null,
                __('تاريخ الإصدار') => $certificate->issued_at?->format('Y-m-d'),
                __('تنتهي في') => $certificate->expires_at?->format('Y-m-d'),
                __('الحالة') => $certificate->statusLabel(),
            ])" />
        </x-ui.card>
    @endif
</main>

<x-site.footer />
</x-layouts.app>
