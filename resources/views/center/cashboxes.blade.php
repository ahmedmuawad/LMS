<x-layouts.admin :title="__('الخزنة')" current="fees">
<div class="max-w-[1100px]">

    <x-ui.page-header :title="__('الخزائن')"
                      :subtitle="__('ما تقوله السجلات مقابل ما في الدرج — والفرق يُسجَّل ولا يُطمس.')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif
    @error('counted')<x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    <div class="grid gap-4 lg:grid-cols-2 mb-4">
        @foreach($boxes as $row)
            @php $box = $row['box']; @endphp
            <x-ui.card :title="$box->name" :subtitle="$box->branch?->name">
                <x-slot:actions>
                    @if($row['closedToday'])
                        <x-ui.badge tone="success">{{ __('أُقفلت اليوم') }}</x-ui.badge>
                    @endif
                </x-slot:actions>

                <x-ui.description-list :items="[
                    __('الرصيد المسجَّل') => $box->balance()->format(),
                    __('المتوقَّع اليوم') => $row['expected']->format(),
                ]" />

                @unless($row['closedToday'])
                    <form method="POST" action="{{ url('/admin/cashboxes/'.$box->id.'/close') }}" class="mt-4 pt-4 border-t border-line">
                        @csrf
                        <x-ui.field :label="__('المعدود فعلاً')" :for="'c'.$box->id"
                                    :hint="__('اعدُد ما في الدرج واكتبه كما هو — لا كما ينبغي أن يكون.')">
                            <x-ui.input type="number" step="0.01" min="0" name="counted" :id="'c'.$box->id" class="font-mono" />
                        </x-ui.field>
                        <x-ui.field :label="__('تفسير الفرق')" :for="'e'.$box->id"
                                    :hint="__('مطلوب إن اختلف المعدود عن المتوقَّع.')">
                            <x-ui.input name="explanation" :id="'e'.$box->id" />
                        </x-ui.field>
                        <x-ui.button size="sm" type="submit" class="w-full">{{ __('تقفيل اليوم') }}</x-ui.button>
                    </form>
                @endunless
            </x-ui.card>
        @endforeach
    </div>

    <x-ui.card :title="__('آخر الحركات')" :padding="false">
        @if($movements->isEmpty())
            <div class="p-5"><x-ui.empty :title="__('لا حركات بعد')">{{ __('كل تحصيل أو صرف سيظهر هنا برصيده بعده.') }}</x-ui.empty></div>
        @else
            <div class="overflow-x-auto">
                <x-ui.table>
                    <thead>
                        <tr>
                            @foreach ([__('الخزنة'), __('النوع'), __('المبلغ'), __('الرصيد بعدها'), __('المرجع'), __('متى')] as $th)
                                <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($movements as $movement)
                            <tr class="hover:bg-surface-sunken transition-colors">
                                <td class="px-4 py-3 border-b border-line text-sm">{{ $movement->cashbox?->name }}</td>
                                <td class="px-4 py-3 border-b border-line text-sm">
                                    {{ __(App\Modules\Center\Models\CashMovement::TYPES[$movement->type] ?? $movement->type) }}
                                </td>
                                <td class="px-4 py-3 border-b border-line font-mono text-sm tabular
                                           {{ $movement->amount_minor >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $movement->amount()->format() }}
                                </td>
                                <td class="px-4 py-3 border-b border-line font-mono text-xs tabular text-muted">{{ $movement->balanceAfter()->format() }}</td>
                                <td class="px-4 py-3 border-b border-line font-mono text-2xs">{{ $movement->reference ?? '—' }}</td>
                                <td class="px-4 py-3 border-b border-line text-xs text-subtle whitespace-nowrap">{{ $movement->created_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </div>
        @endif
    </x-ui.card>
</div>
</x-layouts.admin>
