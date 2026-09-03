<x-layouts.admin :title="__('الأرباح والعمولات')" current="earnings">
<div class="max-w-[1100px]">

    <x-ui.page-header :title="__('الأرباح والعمولات')"
                      :subtitle="__('رصيدك ثلاث طبقات: ما لم ينضج بعد، وما صار متاحاً، وما حُوِّل إليك.')" />

    @if(session('status'))
        <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @error('payout')
        <x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>
    @enderror

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <x-ui.stat :label="__('متاح للسحب')" :value="$available->format()" />
        <x-ui.stat :label="__('قيد النضج')" :value="$pending->format()"
                   :delta="__('ينضج بعد انقضاء مهلة الاسترداد')" />
        <x-ui.stat :label="__('حُوِّل إليك')" :value="$paid->format()" />
        <x-ui.stat :label="__('مسترجَع')" :value="$reversed->format()"
                   :delta="$reversed->isZero() ? null : __('طلبات استُردّت')"
                   :trend="$reversed->isZero() ? null : 'down'" />
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]">

        <div class="grid gap-4 content-start">
            <x-ui.card :title="__('طلب سحب')">
                @if($openRequest)
                    <x-ui.alert tone="info">{{ __('لديك طلب سحب قيد المعالجة — سيصلك إشعار عند تحويله.') }}</x-ui.alert>
                @elseif(! $canRequest)
                    <p class="text-sm text-muted leading-relaxed">
                        {{ $minimum->isZero()
                            ? __('لا رصيد متاح للسحب الآن.')
                            : __('أقل رصيد يُطلب سحبه :amount، ورصيدك المتاح :balance.', ['amount' => $minimum->format(), 'balance' => $available->format()]) }}
                    </p>
                @else
                    <form method="POST" action="{{ url('/admin/earnings/payout') }}" class="grid gap-3">
                        @csrf

                        <x-ui.field :label="__('طريقة التحويل')" name="method" required>
                            <x-ui.select name="method">
                                @foreach(['bank' => __('حساب بنكي'), 'vodafone_cash' => __('فودافون كاش'), 'instapay' => __('إنستاباي')] as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>

                        <x-ui.field :label="__('الحساب أو الرقم')" name="destination"
                                    :hint="__('اتركه فارغاً لاستخدام بياناتك المحفوظة.')">
                            <x-ui.input name="destination" :value="old('destination')" />
                        </x-ui.field>

                        <div><x-ui.button type="submit">{{ __('اطلب سحب :amount', ['amount' => $available->format()]) }}</x-ui.button></div>
                    </form>
                @endif

                @if($rate !== null)
                    <p class="text-xs text-subtle mt-4 pt-4 border-t border-line">
                        {{ __('نصيبك من كل عملية بيع: :rate%', ['rate' => rtrim(rtrim(number_format((float) $rate, 2), '0'), '.')]) }}
                    </p>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('طلبات السحب')" :padding="false">
                @if($requests->isEmpty())
                    <div class="p-5"><x-ui.empty :title="__('لا طلبات')">{{ __('أول طلب سحب تقدّمه سيظهر هنا.') }}</x-ui.empty></div>
                @else
                    <ul class="divide-y divide-line">
                        @foreach($requests as $payout)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="font-mono text-sm tabular">{{ $payout->amount()->format() }}</p>
                                    <p class="text-2xs text-subtle mt-0.5">{{ $payout->reference }} · {{ $payout->created_at?->diffForHumans() }}</p>
                                </div>
                                <x-ui.badge :tone="$payout->status === 'paid' ? 'success' : ($payout->status === 'failed' ? 'danger' : 'warning')" class="shrink-0">
                                    {{ __(App\Modules\Commerce\Models\Payout::STATUSES[$payout->status] ?? $payout->status) }}
                                </x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        <x-ui.card :title="__('دفتر الأرباح')" :padding="false">
            @if($rows->isEmpty())
                <div class="p-5"><x-ui.empty :title="__('لا أرباح بعد')">{{ __('أول عملية بيع لكورساتك ستُقيَّد هنا.') }}</x-ui.empty></div>
            @else
                <div class="overflow-x-auto">
                    <x-ui.table>
                        <thead>
                            <tr>
                                @foreach ([__('المبلغ'), __('النسبة'), __('الحالة'), __('ينضج'), __('التاريخ')] as $th)
                                    <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr class="hover:bg-surface-sunken transition-colors">
                                    <td class="px-4 py-3 border-b border-line text-sm font-mono tabular">{{ $row->amount()->format() }}</td>
                                    <td class="px-4 py-3 border-b border-line text-sm font-mono tabular">{{ rtrim(rtrim(number_format((float) $row->rate, 2), '0'), '.') }}%</td>
                                    <td class="px-4 py-3 border-b border-line">
                                        <x-ui.badge :tone="match ($row->status) { 'paid' => 'success', 'available' => 'info', 'reversed' => 'danger', default => 'neutral' }">
                                            {{ __(App\Modules\Commerce\Models\InstructorEarning::STATUSES[$row->status] ?? $row->status) }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="px-4 py-3 border-b border-line text-xs text-subtle whitespace-nowrap">{{ $row->available_at?->translatedFormat('j M Y') ?? '—' }}</td>
                                    <td class="px-4 py-3 border-b border-line text-xs text-subtle whitespace-nowrap">{{ $row->created_at?->translatedFormat('j M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </div>

                @if($rows->hasPages())
                    <div class="p-4 border-t border-line">
                        <x-ui.pagination :current="$rows->currentPage()" :last="$rows->lastPage()"
                                         :url="request()->fullUrlWithQuery(['page' => '']).''" />
                    </div>
                @endif
            @endif
        </x-ui.card>
    </div>
</div>
</x-layouts.admin>
