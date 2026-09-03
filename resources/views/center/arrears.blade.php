<x-layouts.admin :title="__('المتأخرات')" current="fees">
<div class="max-w-[1200px]">

    <x-ui.page-header :title="__('الأقساط والمتأخرات')"
                      :subtitle="__('من عليه فلوس — مرتّباً بالأقدم استحقاقاً.')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif
    @error('amount')<x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    <div class="grid gap-4 sm:grid-cols-3 mb-6">
        <x-ui.stat :label="__('إجمالي المستحق')" :value="$outstanding->format()" />
        <x-ui.stat :label="__('المتأخر عن موعده')" :value="$overdue->format()"
                   :delta="$overdueCount > 0 ? __('يحتاج متابعة') : null"
                   :trend="$overdueCount > 0 ? 'down' : null" />
        <x-ui.stat :label="__('فواتير متأخرة')" :value="number_format($overdueCount)" />
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
        <x-ui.field :label="__('المجموعة')" for="group" class="mb-0 w-full sm:w-64">
            <x-ui.select name="group" id="group" onchange="this.form.submit()">
                <option value="">{{ __('كل المجموعات') }}</option>
                @foreach($groups as $group)
                    <option value="{{ $group->id }}" @selected(request('group') == $group->id)>{{ $group->name }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.field :label="__('العرض')" for="only" class="mb-0 w-full sm:w-48">
            <x-ui.select name="only" id="only" onchange="this.form.submit()">
                <option value="">{{ __('كل المستحق') }}</option>
                <option value="overdue" @selected(request('only') === 'overdue')>{{ __('المتأخر فقط') }}</option>
            </x-ui.select>
        </x-ui.field>
        <noscript><x-ui.button size="sm" type="submit" class="h-11">{{ __('تصفية') }}</x-ui.button></noscript>
    </form>

    <x-ui.card :padding="false">
        @if($invoices->isEmpty())
            <div class="p-5">
                <x-ui.empty :title="__('لا مستحقات')" tone="success" icon="✓">
                    {{ __('كل الأقساط محصّلة — وهذا نادر، فاحتفظ به.') }}
                </x-ui.empty>
            </div>
        @else
            <div class="overflow-x-auto">
                <x-ui.table>
                    <thead>
                        <tr>
                            @foreach ([__('الطالب'), __('المجموعة'), __('الفترة'), __('المستحق'), __('الاستحقاق'), __('التأخير'), ''] as $th)
                                <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                            <tr class="hover:bg-surface-sunken transition-colors"
                                x-data="{ open: false }">
                                <td class="px-4 py-3 border-b border-line">
                                    <a href="{{ url('/admin/center-students/'.$invoice->student_id) }}"
                                       class="tap-link text-sm font-medium text-primary hover:underline">
                                        {{ $invoice->student?->name() }}
                                    </a>
                                    <span class="block text-2xs text-subtle font-mono">{{ $invoice->student?->code }}</span>
                                </td>
                                <td class="px-4 py-3 border-b border-line text-sm">{{ $invoice->group?->name ?? '—' }}</td>
                                <td class="px-4 py-3 border-b border-line font-mono text-xs">{{ $invoice->period }}</td>
                                <td class="px-4 py-3 border-b border-line font-mono text-sm tabular font-semibold">
                                    {{ $invoice->remaining()->format() }}
                                </td>
                                <td class="px-4 py-3 border-b border-line font-mono text-xs whitespace-nowrap">
                                    {{ $invoice->due_on?->format('Y-m-d') }}
                                </td>
                                <td class="px-4 py-3 border-b border-line">
                                    @if($invoice->isOverdue())
                                        <x-ui.badge tone="danger">
                                            {{ trans_choice('{1} يوم|{2} يومان|[3,10] :count أيام|[11,*] :count يوماً', $invoice->daysLate(), ['count' => $invoice->daysLate()]) }}
                                        </x-ui.badge>
                                    @else
                                        <x-ui.badge tone="info">{{ __('لم يحن') }}</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 border-b border-line">
                                    <x-ui.button size="sm" variant="secondary" type="button" @click="open = ! open">
                                        {{ __('تحصيل') }}
                                    </x-ui.button>
                                </td>
                            </tr>
                            <tr x-show="open" x-cloak>
                                <td colspan="7" class="px-4 py-3 border-b border-line bg-surface-sunken">
                                    <form method="POST" action="{{ url('/admin/fees/collect') }}"
                                          class="flex flex-wrap items-end gap-2">
                                        @csrf
                                        <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                                        <input type="hidden" name="student_id" value="{{ $invoice->student_id }}">

                                        <x-ui.field :label="__('المبلغ')" :for="'a'.$invoice->id" class="mb-0 w-32">
                                            <x-ui.input type="number" step="0.01" min="0.01" name="amount" :id="'a'.$invoice->id"
                                                        value="{{ $invoice->remaining()->toDecimal() }}" class="font-mono" />
                                        </x-ui.field>

                                        <x-ui.field :label="__('الطريقة')" :for="'m'.$invoice->id" class="mb-0 w-40">
                                            <x-ui.select name="method" :id="'m'.$invoice->id">
                                                @foreach(App\Modules\Center\Models\Payment::METHODS as $value => $label)
                                                    <option value="{{ $value }}">{{ __($label) }}</option>
                                                @endforeach
                                            </x-ui.select>
                                        </x-ui.field>

                                        <x-ui.button size="sm" type="submit">{{ __('تحصيل وطباعة إيصال') }}</x-ui.button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </div>

            @if($invoices->hasPages())
                <x-slot:footer>
                    <x-ui.pagination :current="$invoices->currentPage()" :last="$invoices->lastPage()"
                                     :url="request()->fullUrlWithQuery(['page' => '']).''" />
                </x-slot:footer>
            @endif
        @endif
    </x-ui.card>
</div>
</x-layouts.admin>
