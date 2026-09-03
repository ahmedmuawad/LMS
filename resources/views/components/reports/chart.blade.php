@props(['series' => [], 'label' => null, 'divide' => 1])
{{--
    رسم بأعمدة CSS لا بمكتبة.

    مكتبة رسوم ٩٠ ك.ب لأربعة رسوم في شاشة واحدة تكلفة لا تُبرَّر،
    والأداء أولوية معلنة. والأرقام تبقى مقروءة للقارئ الآلي.
--}}
@php
    $values = array_values($series);
    $peak = $values === [] ? 1 : max(max($values), 1);
    $labels = array_keys($series);
    $step = max(1, (int) ceil(count($labels) / 8));
@endphp

<div {{ $attributes->merge(['class' => 'surface-card p-4']) }}>
    @if($label)<h3 class="font-bold text-sm mb-4">{{ $label }}</h3>@endif

    @if($values === [])
        <p class="text-sm text-subtle text-center py-6">{{ __('لا بيانات في هذه المدة.') }}</p>
    @else
        <div class="overflow-x-auto">
            <ol class="flex items-end gap-1 h-32 min-w-full" style="min-width: {{ count($values) * 12 }}px">
                @foreach($series as $day => $value)
                    <li class="flex-1 min-w-[8px] h-full flex items-end"
                        title="{{ $day }}: {{ number_format($value / $divide, $divide > 1 ? 2 : 0) }}">
                        <span class="w-full rounded-t transition-colors {{ $value > 0 ? 'bg-primary' : 'bg-surface-sunken' }}"
                              style="height: {{ max(2, (int) round($value / $peak * 100)) }}%" aria-hidden="true"></span>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="flex justify-between mt-2 text-2xs text-subtle font-mono">
            @foreach($labels as $index => $day)
                @if($index % $step === 0)
                    <span>{{ \Illuminate\Support\Carbon::parse($day)->format('j/n') }}</span>
                @endif
            @endforeach
        </div>

        <table class="sr-only">
            <caption>{{ $label }}</caption>
            <thead><tr><th>{{ __('اليوم') }}</th><th>{{ __('القيمة') }}</th></tr></thead>
            <tbody>
                @foreach($series as $day => $value)
                    <tr><td>{{ $day }}</td><td>{{ number_format($value / $divide, $divide > 1 ? 2 : 0) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
