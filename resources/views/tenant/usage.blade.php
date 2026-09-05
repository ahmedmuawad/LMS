@php
    $labels = [
        'students' => 'الطلبة', 'instructors' => 'المدرّسون', 'staff' => 'الموظّفون',
        'courses' => 'الكورسات', 'branches' => 'الفروع', 'groups' => 'المجموعات',
        'storage_gb' => 'مساحة التخزين', 'video_minutes' => 'دقائق الفيديو', 'emails' => 'الإيميلات',
    ];
    $units = ['storage_gb' => 'جيجابايت', 'video_minutes' => 'دقيقة'];

    // اللون يتغيّر عند ٨٠٪ ثم ٩٥٪: التحذير قبل المنع لا معه
    $tone = fn (?float $p): string => match (true) {
        $p === null => 'primary',
        $p >= 95 => 'danger',
        $p >= 80 => 'warning',
        default => 'success',
    };
@endphp

<x-layouts.admin :title="__('استهلاك باقتك')" current="usage">
<div class="max-w-[900px]">

    <x-ui.page-header :title="__('استهلاك باقتك')"
                      :subtitle="__('ما استُهلك من كل حدّ وما بقي — يُحتسب لحظياً من بياناتك.')" />

    @if($plan)
        <x-ui.alert tone="info" class="mb-6">
            {{ __('باقتك الحالية: :plan', ['plan' => $plan->name[app()->getLocale()] ?? $plan->name['ar'] ?? $plan->key]) }}
        </x-ui.alert>
    @endif

    @if($rows === [])
        <x-ui.card>
            <x-ui.empty :title="__('لا حدود على باقتك')">
                {{ __('باقتك بلا حدود رقمية — أنشئ ما تشاء.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-3">
            @foreach($rows as $row)
                @php
                    $unlimited = $row['limit'] === null;
                    $percent = $row['percent'];
                    $t = $tone($percent);
                @endphp

                <div class="surface-card p-4">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 mb-2.5">
                        <p class="text-sm font-semibold flex-1">{{ __($labels[$row['key']] ?? $row['key']) }}</p>

                        <p class="font-mono text-sm tabular">
                            <span class="font-semibold">{{ number_format($row['used']) }}</span>
                            @if($unlimited)
                                <span class="text-subtle">/ {{ __('بلا حد') }}</span>
                            @else
                                <span class="text-subtle">/ {{ number_format($row['limit']) }}</span>
                            @endif
                            @if(isset($units[$row['key']]))
                                <span class="text-2xs text-subtle">{{ __($units[$row['key']]) }}</span>
                            @endif
                        </p>
                    </div>

                    @unless($unlimited)
                        <x-ui.progress :value="$percent ?? 0" :tone="$t"
                                       :label="__($labels[$row['key']] ?? $row['key'])" />

                        <div class="flex flex-wrap items-center gap-x-3 mt-2">
                            <p @class([
                                'text-2xs font-mono tabular',
                                'text-danger font-semibold' => $percent >= 95,
                                'text-warning font-semibold' => $percent >= 80 && $percent < 95,
                                'text-subtle' => $percent < 80,
                            ])>{{ $percent }}%</p>

                            @if($percent >= 80)
                                <p class="text-2xs text-muted">
                                    {{ $percent >= 100
                                        ? __('بلغتَ الحدّ — الإضافة موقوفة حتى تحذف أو تُرقّي.')
                                        : __('بقي :n فقط.', ['n' => number_format(max(0, $row['limit'] - $row['used']))]) }}
                                </p>
                                <a href="{{ url('/admin/billing') }}"
                                   class="tap-link text-2xs text-primary font-semibold hover:underline">{{ __('ترقية') }} ←</a>
                            @endif
                        </div>
                    @endunless
                </div>
            @endforeach
        </div>

        <p class="text-2xs text-subtle mt-5 leading-relaxed">
            {{ __('الطلبة والكورسات والمجموعات والفروع والمساحة تُعَدّ لحظياً من بياناتك، فحذف أيٍّ منها يحرّر مكانه فوراً. أمّا دقائق الفيديو والإيميلات فتُحتسب تراكمياً لكل شهر.') }}
        </p>
    @endif

</div>
</x-layouts.admin>
