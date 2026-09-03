@php
    use App\Core\Billing\Models\Invoice;
    $tones = [
        'draft' => 'neutral', 'open' => 'info', 'paid' => 'success',
        'overdue' => 'danger', 'void' => 'neutral', 'refunded' => 'warning',
    ];
@endphp

<x-layouts.super-admin :title="__('فاتورة :number', ['number' => $invoice->number])" current="invoices">
<div class="max-w-[1000px]">

    <x-ui.page-header :title="__('فاتورة :number', ['number' => $invoice->number])"
                      :subtitle="$invoice->tenant?->name"
                      :back="url('/admin/invoices')">
        <x-slot:actions>
            <x-ui.badge :tone="$tones[$invoice->status] ?? 'neutral'">
                {{ __(Invoice::STATUSES[$invoice->status] ?? $invoice->status) }}
            </x-ui.badge>
            @if($invoice->isOverdue())
                <x-ui.badge tone="danger">{{ __('متأخّرة :days يوماً', ['days' => (int) $invoice->due_at->diffInDays()]) }}</x-ui.badge>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_300px] items-start">

        <div class="grid gap-4 min-w-0">
            <x-ui.card :title="__('البنود')" :padding="false">
                <div class="overflow-x-auto">
                    <x-ui.table>
                        <thead>
                            <tr>
                                @foreach ([__('البند'), __('الكمية'), __('السعر'), __('الإجمالي')] as $th)
                                    <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->lines as $line)
                                <tr>
                                    <td class="px-4 py-3 border-b border-line">
                                        <div class="text-sm font-medium">
                                            {{ is_array($line['title'] ?? null)
                                                ? ($line['title'][app()->getLocale()] ?? $line['title']['ar'] ?? '—')
                                                : ($line['title'] ?? '—') }}
                                        </div>
                                        @isset($line['description'])
                                            <div class="text-xs text-subtle">{{ $line['description'] }}</div>
                                        @endisset
                                    </td>
                                    <td class="px-4 py-3 border-b border-line font-mono text-xs tabular">{{ $line['quantity'] ?? 1 }}</td>
                                    <td class="px-4 py-3 border-b border-line font-mono text-xs tabular">
                                        {{ App\Core\Support\Money::fromMinor((int) ($line['unit_minor'] ?? 0), $invoice->currency)->format() }}
                                    </td>
                                    <td class="px-4 py-3 border-b border-line font-mono text-xs tabular">
                                        {{ App\Core\Support\Money::fromMinor((int) ($line['total_minor'] ?? 0), $invoice->currency)->format() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </div>
                <x-slot:footer>
                    <x-ui.description-list :items="array_filter([
                        __('المجموع') => App\Core\Support\Money::fromMinor($invoice->subtotal_minor, $invoice->currency)->format(),
                        $invoice->tax_minor > 0 ? ($invoice->tax_label ?? __('الضريبة')).' ('.rtrim(rtrim((string) $invoice->tax_rate, '0'), '.').'%)' : null
                            => $invoice->tax_minor > 0 ? App\Core\Support\Money::fromMinor($invoice->tax_minor, $invoice->currency)->format() : null,
                        __('الإجمالي') => $invoice->total()->format(),
                        __('المدفوع') => App\Core\Support\Money::fromMinor($invoice->paid_minor, $invoice->currency)->format(),
                        __('المتبقّي') => $invoice->outstanding()->format(),
                    ])" />
                </x-slot:footer>
            </x-ui.card>

            <x-ui.card :title="__('الدفعات')">
                @if($invoice->payments->isEmpty())
                    <x-ui.empty :title="__('لا دفعات بعد')">{{ __('سجّل دفعة يدوية إن وصلك تحويل بنكي أو نقدي.') }}</x-ui.empty>
                @else
                    <x-ui.timeline :items="$invoice->payments->map(fn ($p) => [
                        'title' => $p->amount()->format().' · '.$p->gateway,
                        'meta'  => ($p->reference ?? '—').' · '.$p->paid_at?->diffForHumans(),
                        'tone'  => $p->status === 'succeeded' ? 'success' : ($p->status === 'failed' ? 'danger' : null),
                    ])->all()" />
                @endif
            </x-ui.card>
        </div>

        <div class="grid gap-4 min-w-0">
            <x-ui.card :title="__('البيانات')">
                <x-ui.description-list :items="[
                    __('المشترك')       => $invoice->tenant?->name ?? '—',
                    __('البريد')        => $invoice->billing_details['email'] ?? '—',
                    __('الدولة')        => $invoice->billing_details['country'] ?? '—',
                    __('الدورة')        => ($invoice->period_start?->format('Y-m-d') ?? '—').' → '.($invoice->period_end?->format('Y-m-d') ?? '—'),
                    __('تاريخ الإصدار')  => $invoice->issued_at?->format('Y-m-d') ?? '—',
                    __('تاريخ الاستحقاق') => $invoice->due_at?->format('Y-m-d') ?? '—',
                    __('تاريخ السداد')   => $invoice->paid_at?->format('Y-m-d') ?? '—',
                ]" />
            </x-ui.card>

            @if(! in_array($invoice->status, ['paid', 'void'], true))
                <x-ui.card :title="__('تسجيل دفعة')"
                           :subtitle="__('لِما يصلنا خارج البوابات: تحويل بنكي أو نقدي.')">
                    <form method="POST" action="{{ url('/admin/invoices/'.$invoice->id.'/pay') }}">
                        @csrf
                        <x-ui.field :label="__('المبلغ')" for="amount" :error="$errors->first('amount')">
                            <x-ui.input name="amount" id="amount" type="number" step="0.01"
                                        value="{{ $invoice->outstanding()->toDecimal() }}" class="font-mono" />
                        </x-ui.field>
                        <x-ui.field :label="__('الوسيلة')" for="gateway">
                            <x-ui.select name="gateway" id="gateway">
                                <option value="bank_transfer">{{ __('تحويل بنكي') }}</option>
                                <option value="cash">{{ __('نقدي') }}</option>
                                <option value="manual">{{ __('تسوية يدوية') }}</option>
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field :label="__('المرجع')" for="reference" :hint="__('رقم التحويل أو الإيصال.')">
                            <x-ui.input name="reference" id="reference" class="font-mono" />
                        </x-ui.field>
                        <x-ui.button type="submit" size="sm" class="w-full">{{ __('تسجيل الدفعة') }}</x-ui.button>
                    </form>

                    <form method="POST" action="{{ url('/admin/invoices/'.$invoice->id.'/void') }}" class="mt-3">
                        @csrf @method('PUT')
                        <x-ui.button type="submit" size="sm" variant="ghost" class="w-full">{{ __('إلغاء الفاتورة') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
</div>
</x-layouts.super-admin>
