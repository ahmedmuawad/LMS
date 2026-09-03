@php
    $types = ['boolean' => 'ميزة', 'limit' => 'حد ثابت', 'quota' => 'حصة متجدّدة'];
@endphp

<x-layouts.super-admin :title="__('الباقات والمزايا')" current="plans">
<div class="max-w-[1400px]">

    <x-ui.page-header :title="__('الباقات والمزايا')"
                      :subtitle="__('مصفوفة واحدة — هي نفسها جدول المقارنة في صفحة التسعير.')" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        @foreach($plans as $plan)
            <x-ui.stat :label="$plan->name[app()->getLocale()] ?? $plan->name['ar'] ?? $plan->key"
                       :value="$plan->priceIn(array_key_first($currencies) ?: 'EGP')?->format() ?? '—'"
                       :delta="trans_choice('{0} لا مشتركين|{1} مشترك واحد|{2} مشتركان|[3,10] :count مشتركين|[11,*] :count مشتركاً', (int) ($counts[$plan->key] ?? 0), ['count' => (int) ($counts[$plan->key] ?? 0)])" />
        @endforeach
    </div>

    @if($plans->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا باقات بعد')">{{ __('أضف باقة أولاً لتظهر هنا مصفوفة المزايا.') }}</x-ui.empty>
        </x-ui.card>
    @else
        <x-ui.card :title="__('مصفوفة المزايا')" :padding="false">
            <div class="overflow-x-auto">
                <x-ui.table>
                    <thead>
                        <tr>
                            <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap sticky start-0 z-10">{{ __('الميزة') }}</th>
                            @foreach($plans as $plan)
                                <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">
                                    {{ $plan->name[app()->getLocale()] ?? $plan->name['ar'] ?? $plan->key }}
                                    @unless($plan->is_active)<span class="block text-2xs text-subtle font-normal">{{ __('متوقّفة') }}</span>@endunless
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($features as $feature)
                            <tr class="hover:bg-surface-sunken transition-colors">
                                <td class="px-4 py-3 border-b border-line bg-surface sticky start-0 z-10">
                                    <div class="text-sm font-medium whitespace-nowrap">{{ $feature->name[app()->getLocale()] ?? $feature->name['ar'] ?? $feature->key }}</div>
                                    <div class="font-mono text-2xs text-subtle">{{ $feature->key }} · {{ __($types[$feature->type] ?? $feature->type) }}{{ $feature->unit ? ' · '.$feature->unit : '' }}</div>
                                </td>
                                @foreach($plans as $plan)
                                    <td class="px-4 py-3 border-b border-line">
                                        <form method="POST" action="{{ url('/admin/plans/'.$plan->key.'/feature') }}" class="flex items-center gap-1.5">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="feature_key" value="{{ $feature->key }}">
                                            <x-ui.input name="value"
                                                        value="{{ $matrix[$plan->key][$feature->key] ?? '' }}"
                                                        class="w-24 !py-1.5 font-mono text-xs"
                                                        :placeholder="$feature->type === 'boolean' ? '0 / 1' : '0 / unlimited'"
                                                        :aria-label="__(':feature في باقة :plan', [
                                                            'feature' => $feature->name[app()->getLocale()] ?? $feature->name['ar'] ?? $feature->key,
                                                            'plan' => $plan->name[app()->getLocale()] ?? $plan->name['ar'] ?? $plan->key,
                                                        ])" />
                                            <x-ui.button size="sm" variant="ghost" type="submit">{{ __('حفظ') }}</x-ui.button>
                                        </form>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </div>
            <x-slot:footer>
                <p class="text-2xs text-subtle">
                    {{ __('كل تعديل هنا يُبطل الصلاحيات المحفوظة لكل مشتركي الباقة فوراً، ويُقيَّد في سجلّ التدخّلات.') }}
                </p>
            </x-slot:footer>
        </x-ui.card>

        <x-ui.card :title="__('الأسعار المثبّتة')" :subtitle="__('لا نحوّل بسعر الصرف — لكل عملة سعرها المدروس.')" class="mt-4" :padding="false">
            <div class="overflow-x-auto">
                <x-ui.table>
                    <thead>
                        <tr>
                            <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ __('الباقة') }}</th>
                            @foreach($currencies as $currency)
                                <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap font-mono">{{ $currency }}</th>
                            @endforeach
                            <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ __('التجربة') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($plans as $plan)
                            <tr class="hover:bg-surface-sunken transition-colors">
                                <td class="px-4 py-3 border-b border-line text-sm font-medium whitespace-nowrap">{{ $plan->name[app()->getLocale()] ?? $plan->name['ar'] ?? $plan->key }}</td>
                                @foreach($currencies as $currency)
                                    <td class="px-4 py-3 border-b border-line font-mono text-xs tabular whitespace-nowrap">{{ $plan->priceIn($currency)?->format() ?? '—' }}</td>
                                @endforeach
                                <td class="px-4 py-3 border-b border-line text-xs text-muted whitespace-nowrap">
                                    {{ trans_choice('{0} بلا تجربة|{1} يوم واحد|{2} يومان|[3,10] :count أيام|[11,*] :count يوماً', $plan->trial_days, ['count' => $plan->trial_days]) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </div>
        </x-ui.card>
    @endif
</div>
</x-layouts.super-admin>
