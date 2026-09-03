<x-layouts.super-admin :title="__('صحة النظام')" current="health">
<div class="max-w-[1000px]">

    <x-ui.page-header :title="__('صحة النظام')"
                      :subtitle="__('فحوص تُجرى الآن عند فتح الصفحة — لا أرقام محفوظة من آخر مرة.')" />

    @php $failing = collect($checks)->reject(fn ($c) => $c['ok'])->count(); @endphp

    @if($failing > 0)
        <x-ui.alert tone="danger" :title="trans_choice('{1} فحص واحد فاشل|{2} فحصان فاشلان|[3,10] :count فحوص فاشلة|[11,*] :count فحصاً فاشلاً', $failing, ['count' => $failing])" class="mb-6">
            {{ __('راجع الفحوص المعلَّمة بالأحمر قبل أي تجهيز جديد.') }}
        </x-ui.alert>
    @else
        <x-ui.alert tone="success" :title="__('كل الفحوص سليمة')" class="mb-6">
            {{ __('القاعدة والكاش والطوابير والتخزين تعمل كما ينبغي.') }}
        </x-ui.alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-2 items-start">
        <x-ui.card :title="__('الفحوص')">
            <ul class="grid gap-3">
                @foreach($checks as $check)
                    <li class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium">{{ $check['label'] }}</p>
                            @if($check['error'])
                                <p class="text-xs text-danger mt-0.5 break-words">{{ $check['error'] }}</p>
                            @endif
                        </div>
                        <x-ui.badge :tone="$check['ok'] ? 'success' : 'danger'" class="shrink-0">
                            {{ $check['ok'] ? __('سليم') : __('فاشل') }}
                        </x-ui.badge>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>

        <div class="grid gap-4">
            <x-ui.card :title="__('البيئة')">
                <x-ui.description-list :items="collect($versions)->mapWithKeys(fn ($v, $k) => [__($k) => (string) $v])->all()" />
            </x-ui.card>

            <x-ui.card :title="__('ما يحتاج تدخّلاً')">
                <x-ui.description-list :items="[
                    __('مهام فاشلة في الطابور') => number_format($failedJobs),
                    __('تجهيز متعثّر (أكثر من ربع ساعة)') => number_format($stuck->count()),
                ]" />

                @if($stuck->isNotEmpty())
                    <ul class="grid gap-2 mt-3 pt-3 border-t border-line">
                        @foreach($stuck as $tenant)
                            <li class="text-sm">
                                <a href="{{ url('/admin/tenants/'.$tenant->id) }}" class="tap-link text-primary hover:underline">{{ $tenant->name }}</a>
                                <span class="text-2xs text-subtle font-mono ms-1">{{ $tenant->created_at?->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    </div>
</div>
</x-layouts.super-admin>
