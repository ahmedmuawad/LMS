@php
    use App\Modules\Commerce\Models\Order;
    use App\Modules\Commerce\Models\Refund;
    $tones = [
        'pending' => 'neutral', 'awaiting_payment' => 'warning', 'paid' => 'success',
        'processing' => 'info', 'completed' => 'success', 'cancelled' => 'neutral',
        'refunded' => 'warning', 'failed' => 'danger',
    ];
@endphp

<x-layouts.admin :title="__('طلب :n', ['n' => $order->number])" current="orders">
<div class="max-w-[1100px]">

    <x-ui.page-header :title="__('طلب :n', ['n' => $order->number])"
                      :subtitle="$order->customerName().' · '.($order->customerEmail() ?? '—')"
                      :back="url('/admin/orders')">
        <x-slot:actions>
            <x-ui.badge :tone="$tones[$order->status] ?? 'neutral'">
                {{ __(Order::STATUSES[$order->status] ?? $order->status) }}
            </x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    @error('refund')<x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px] items-start">

        <div class="grid gap-4 min-w-0">
            <x-ui.card :title="__('البنود')" :padding="false">
                <div class="overflow-x-auto">
                    <x-ui.table>
                        <thead>
                            <tr>
                                @foreach ([__('البند'), __('الكمية'), __('السعر'), __('الإجمالي'), __('التسليم')] as $th)
                                    <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr>
                                    <td class="px-4 py-3 border-b border-line text-sm">{{ $item->title() }}</td>
                                    <td class="px-4 py-3 border-b border-line font-mono text-xs tabular">{{ $item->quantity }}</td>
                                    <td class="px-4 py-3 border-b border-line font-mono text-xs tabular">{{ $item->unitPrice()->format() }}</td>
                                    <td class="px-4 py-3 border-b border-line font-mono text-xs tabular">{{ $item->total()->format() }}</td>
                                    <td class="px-4 py-3 border-b border-line">
                                        @if($item->isFulfilled())
                                            <x-ui.badge tone="success">{{ __('سُلّم') }}</x-ui.badge>
                                        @else
                                            <x-ui.badge tone="warning">{{ __('بانتظار') }}</x-ui.badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </div>
                <x-slot:footer>
                    <x-ui.description-list :items="array_filter([
                        __('المجموع') => $order->subtotal()->format(),
                        $order->discount()->isZero() ? null : __('الخصم :code', ['code' => $order->coupon_code ?? '']) => $order->discount()->isZero() ? null : '− '.$order->discount()->format(),
                        $order->shipping()->isZero() ? null : __('الشحن') => $order->shipping()->isZero() ? null : $order->shipping()->format(),
                        $order->tax()->isZero() ? null : __('الضريبة') => $order->tax()->isZero() ? null : $order->tax()->format(),
                        __('الإجمالي') => $order->total()->format(),
                        __('المدفوع') => $order->paid()->format(),
                        $order->outstanding()->isZero() ? null : __('المتبقّي') => $order->outstanding()->isZero() ? null : $order->outstanding()->format(),
                    ])" />
                </x-slot:footer>
            </x-ui.card>

            <x-ui.card :title="__('الدفعات')">
                @if($order->payments->isEmpty())
                    <x-ui.empty :title="__('لا دفعات بعد')">{{ __('سجّل دفعة يدوية إن وصلك تحويل أو نقد.') }}</x-ui.empty>
                @else
                    <x-ui.timeline :items="$order->payments->map(fn ($p) => [
                        'title' => $p->amount()->format().' · '.$p->gateway,
                        'meta'  => ($p->gateway_ref ?? '—').' · '.($p->paid_at ?? $p->created_at)?->diffForHumans(),
                        'tone'  => $p->succeeded() ? 'success' : ($p->status === 'failed' ? 'danger' : null),
                        'body'  => $p->failure_reason,
                    ])->all()" />
                @endif
            </x-ui.card>

            @if($order->refunds->isNotEmpty())
                <x-ui.card :title="__('طلبات الاسترداد')">
                    <ul class="grid gap-3">
                        @foreach($order->refunds as $refund)
                            <li class="rounded-md border border-line p-3">
                                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                    <x-ui.badge :tone="match($refund->status) {
                                        'processed' => 'success', 'rejected' => 'neutral',
                                        'approved' => 'info', default => 'warning',
                                    }">{{ __(Refund::STATUSES[$refund->status] ?? $refund->status) }}</x-ui.badge>
                                    <span class="font-mono text-sm tabular">{{ $refund->amount()->format() }}</span>
                                </div>

                                @if($refund->reason)<p class="text-sm text-muted mb-2">{{ $refund->reason }}</p>@endif

                                @if($refund->status === 'requested')
                                    <form method="POST" action="{{ url('/admin/refunds/'.$refund->id) }}"
                                          class="flex flex-wrap items-end gap-2">
                                        @csrf @method('PUT')
                                        <x-ui.field :label="__('ملاحظة')" :for="'n'.$refund->id" class="mb-0 flex-1 min-w-[160px]">
                                            <x-ui.input name="note" :id="'n'.$refund->id" />
                                        </x-ui.field>
                                        <x-ui.button size="sm" type="submit" name="decision" value="approve">{{ __('اعتماد') }}</x-ui.button>
                                        <x-ui.button size="sm" variant="ghost" type="submit" name="decision" value="reject">{{ __('رفض') }}</x-ui.button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>

        <div class="grid gap-4 min-w-0">
            <x-ui.card :title="__('العميل')">
                <x-ui.description-list :items="array_filter([
                    __('الاسم') => $order->customerName(),
                    __('البريد') => $order->customerEmail(),
                    __('الهاتف') => $order->billing['phone'] ?? null,
                    __('الدولة') => $order->billing['country'] ?? null,
                    __('وسيلة الدفع') => $order->gateway,
                    __('تاريخ الطلب') => $order->placed_at?->format('Y-m-d H:i'),
                    __('تاريخ الدفع') => $order->paid_at?->format('Y-m-d H:i'),
                ])" />
            </x-ui.card>

            @if(! $order->isPaid() && ! in_array($order->status, ['cancelled', 'refunded'], true))
                <x-ui.card :title="__('تسجيل دفعة')">
                    <form method="POST" action="{{ url('/admin/orders/'.$order->id.'/pay') }}">
                        @csrf
                        <x-ui.field :label="__('المبلغ')" for="amount" :error="$errors->first('amount')">
                            <x-ui.input name="amount" id="amount" type="number" step="0.01"
                                        value="{{ $order->outstanding()->toDecimal() }}" class="font-mono" />
                        </x-ui.field>
                        <x-ui.field :label="__('الوسيلة')" for="gateway">
                            <x-ui.select name="gateway" id="gateway">
                                <option value="bank_transfer">{{ __('تحويل بنكي') }}</option>
                                <option value="cash">{{ __('نقدي') }}</option>
                                <option value="manual">{{ __('تسوية يدوية') }}</option>
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field :label="__('المرجع')" for="reference">
                            <x-ui.input name="reference" id="reference" class="font-mono" />
                        </x-ui.field>
                        <x-ui.button type="submit" size="sm" class="w-full">{{ __('تسجيل وفتح المحتوى') }}</x-ui.button>
                    </form>

                    <form method="POST" action="{{ url('/admin/orders/'.$order->id.'/cancel') }}" class="mt-3"
                          x-data @submit="if (! confirm(@js(__('سيُلغى الطلب. متابعة؟')))) $event.preventDefault()">
                        @csrf @method('PUT')
                        <x-ui.button type="submit" size="sm" variant="ghost" class="w-full">{{ __('إلغاء الطلب') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif

            @if($order->isRefundable())
                <x-ui.card :title="__('استرداد')"
                           :subtitle="__('سيُسحب الوصول ويبقى سجلّ الطالب ودرجاته.')">
                    <form method="POST" action="{{ url('/admin/orders/'.$order->id.'/refund') }}"
                          x-data @submit="if (! confirm(@js(__('سيُستردّ المبلغ ويُسحب الوصول. متابعة؟')))) $event.preventDefault()">
                        @csrf
                        <x-ui.field :label="__('السبب')" for="reason">
                            <x-ui.input name="reason" id="reason" :placeholder="__('طلب العميل…')" />
                        </x-ui.field>
                        <x-ui.button type="submit" size="sm" variant="danger" class="w-full">
                            {{ __('استرداد :amount', ['amount' => $order->total()->minus($order->refunded())->format()]) }}
                        </x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
</div>
</x-layouts.admin>
