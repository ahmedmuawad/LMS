@php
    use App\Core\Billing\Models\Invoice;
    use App\Core\Billing\Models\Subscription;
    use App\Core\Entitlements\Entitlements;

    $invoiceTones = [
        'draft' => 'neutral', 'open' => 'info', 'paid' => 'success',
        'overdue' => 'danger', 'void' => 'neutral', 'refunded' => 'warning',
    ];

    $valueLabel = function (?string $v): string {
        return match ($v) {
            null, '0', 'false' => '—',
            Entitlements::UNLIMITED => __('بلا حد'),
            '1', 'true' => '✓',
            default => $v,
        };
    };
@endphp

<x-layouts.admin :title="__('الاشتراك والفواتير')" current="billing">
<div class="max-w-[1200px]">

    <x-ui.page-header :title="__('الاشتراك والفواتير')"
                      :subtitle="__('باقتك الحالية، وما يمكنك الترقية إليه، وسجلّ فواتيرك.')" />

    @if($subscription?->status === 'past_due')
        <x-ui.alert tone="danger" :title="__('تعذّر تحصيل اشتراكك')" class="mb-4">
            {{ __('منصّتك تعمل الآن لطلابك، لكن لوحة التحكم ستُقفل إن لم يُسدَّد المستحق.') }}
        </x-ui.alert>
    @elseif($subscription?->status === 'trialing')
        <x-ui.alert tone="info" :title="__('أنت في التجربة المجانية')" class="mb-4">
            {{ __('تنتهي في :date — اختر باقتك قبلها حتى لا تتوقّف لوحة التحكم.', [
                'date' => $subscription->trial_ends_at?->format('Y-m-d') ?? '—',
            ]) }}
        </x-ui.alert>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <x-ui.stat :label="__('باقتك')"
                   :value="$plan?->name[app()->getLocale()] ?? $plan?->name['ar'] ?? ($tenant->plan_key ?? '—')" />
        <x-ui.stat :label="__('القيمة')" :value="$subscription?->amount()->format() ?? '—'"
                   :delta="$subscription ? ($subscription->interval === 'year' ? __('سنوياً') : __('شهرياً')) : null" />
        <x-ui.stat :label="__('الحالة')"
                   :value="__(Subscription::STATUSES[$subscription?->status] ?? '—')" />
        <x-ui.stat :label="__('التجديد القادم')"
                   :value="$subscription?->current_period_end?->format('Y-m-d') ?? '—'"
                   :delta="$subscription?->auto_renew === false ? __('التجديد التلقائي موقوف') : null" />
    </div>

    <x-ui.card :title="__('الباقات')" :subtitle="__('الأسعار مثبّتة بعملتك ولا تُحوَّل بسعر الصرف.')"
               :padding="false" class="mb-4">
        @if($plans->isEmpty())
            <div class="p-5"><x-ui.empty :title="__('لا باقات معروضة')">{{ __('تواصل معنا لاختيار ما يناسب منصّتك.') }}</x-ui.empty></div>
        @else
            <div class="overflow-x-auto">
                <x-ui.table>
                    <thead>
                        <tr>
                            <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap sticky start-0 z-10">{{ __('الميزة') }}</th>
                            @foreach($plans as $option)
                                <th class="bg-surface-sunken text-start font-semibold text-xs px-4 py-3 border-b border-line whitespace-nowrap
                                           {{ $option->key === $tenant->plan_key ? 'text-primary' : 'text-muted' }}">
                                    <div>{{ $option->name[app()->getLocale()] ?? $option->name['ar'] ?? $option->key }}</div>
                                    <div class="font-mono text-sm mt-0.5">{{ $option->priceIn($tenant->currency)?->format() ?? '—' }}</div>
                                    @if($option->key === $tenant->plan_key)
                                        <x-ui.badge tone="primary" class="mt-1">{{ __('باقتك') }}</x-ui.badge>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($features as $feature)
                            <tr class="hover:bg-surface-sunken transition-colors">
                                <td class="px-4 py-3 border-b border-line bg-surface sticky start-0 z-10 text-sm whitespace-nowrap">
                                    {{ $feature->name[app()->getLocale()] ?? $feature->name['ar'] ?? $feature->key }}
                                    @if($feature->unit)<span class="text-2xs text-subtle">({{ $feature->unit }})</span>@endif
                                </td>
                                @foreach($plans as $option)
                                    <td class="px-4 py-3 border-b border-line font-mono text-xs tabular
                                               {{ ($matrix[$option->key][$feature->key] ?? null) === null ? 'text-subtle' : '' }}">
                                        {{ $valueLabel($matrix[$option->key][$feature->key] ?? null) }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </div>
            <x-slot:footer>
                <p class="text-2xs text-subtle">
                    {{ __('التنزيل لا يحذف شيئاً: ما تجاوز الحد الجديد يبقى يعمل، وتُمنع الإضافة الجديدة فقط.') }}
                </p>
            </x-slot:footer>
        @endif
    </x-ui.card>

    <x-ui.card :title="__('فواتيرك')" :padding="false">
        @if($invoices->isEmpty())
            <div class="p-5">
                <x-ui.empty :title="__('لا فواتير بعد')">{{ __('تُصدَر الفاتورة مع كل دورة اشتراك وتظهر هنا.') }}</x-ui.empty>
            </div>
        @else
            <div class="overflow-x-auto">
                <x-ui.table>
                    <thead>
                        <tr>
                            @foreach ([__('الرقم'), __('الحالة'), __('الإجمالي'), __('الدورة'), __('الاستحقاق')] as $th)
                                <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                            <tr class="hover:bg-surface-sunken transition-colors">
                                <td class="px-4 py-3 border-b border-line font-mono text-xs">{{ $invoice->number }}</td>
                                <td class="px-4 py-3 border-b border-line">
                                    <x-ui.badge :tone="$invoiceTones[$invoice->status] ?? 'neutral'">
                                        {{ __(Invoice::STATUSES[$invoice->status] ?? $invoice->status) }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-4 py-3 border-b border-line font-mono text-xs tabular">{{ $invoice->total()->format() }}</td>
                                <td class="px-4 py-3 border-b border-line text-xs text-muted whitespace-nowrap">
                                    {{ $invoice->period_start?->format('Y-m-d') }} → {{ $invoice->period_end?->format('Y-m-d') }}
                                </td>
                                <td class="px-4 py-3 border-b border-line text-xs whitespace-nowrap
                                           {{ $invoice->isOverdue() ? 'text-danger font-semibold' : 'text-subtle' }}">
                                    {{ $invoice->due_at?->format('Y-m-d') ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </div>
        @endif
    </x-ui.card>
</div>
</x-layouts.admin>
