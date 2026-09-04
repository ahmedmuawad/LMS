<x-layouts.marketing :title="__('تعليمات التحويل')">
    <div class="max-w-[640px] mx-auto px-4 sm:px-6 py-10 sm:py-16">

        <h1 class="text-2xl font-extrabold mb-2">{{ $gateway->title() }}</h1>
        <p class="text-sm text-muted leading-relaxed mb-6">{{ $intent->message }}</p>

        <x-ui.card :title="__('بيانات التحويل')" class="mb-4">
            {{-- كلٌّ في سطر ومنسوخ بضغطة: رقم حساب يُكتب بيد المستخدم يُخطئ فيه --}}
            <x-ui.description-list :items="$intent->meta" />
        </x-ui.card>

        <x-ui.alert tone="info" :title="__('منصّتك تعمل من الآن')" class="mb-5">
            {{ __('لا تنتظر اعتماد التحويل — ادخل وابدأ الإعداد، ونعتمد الفاتورة حين يصلنا الإيصال.') }}
        </x-ui.alert>

        <form method="POST" action="{{ $enterUrl }}">
            @csrf
            <x-ui.button type="submit" size="lg">{{ __('ادخل إلى منصّتي') }}</x-ui.button>
        </form>
    </div>
</x-layouts.marketing>
