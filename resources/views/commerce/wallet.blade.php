<x-layouts.app :title="__('محفظتي')">
<x-site.header />

<main id="main" class="max-w-[820px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.page-header :title="__('محفظتي')" :subtitle="__('رصيدك وسجلّ حركاته، وشحن كود جديد.')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif

    <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_300px] items-start">

        <div class="grid gap-4 min-w-0">
            <x-ui.card>
                <p class="text-xs text-subtle mb-1">{{ __('رصيدك') }}</p>
                <p class="font-mono text-3xl font-medium tabular">{{ $balance->format() }}</p>
            </x-ui.card>

            <x-ui.card :title="__('حركات المحفظة')" :padding="false">
                @if($transactions->isEmpty())
                    <div class="p-5">
                        <x-ui.empty :title="__('لا حركات بعد')">{{ __('اشحن رصيدك بكود لتبدأ.') }}</x-ui.empty>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <x-ui.table>
                            <thead>
                                <tr>
                                    @foreach ([__('الحركة'), __('المبلغ'), __('الرصيد بعدها'), __('متى')] as $th)
                                        <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transactions as $transaction)
                                    <tr class="hover:bg-surface-sunken transition-colors">
                                        <td class="px-4 py-3 border-b border-line text-sm">
                                            {{ $transaction->type === 'credit' ? __('شحن') : __('خصم') }}
                                            @if($transaction->reference)
                                                <span class="block text-2xs text-subtle font-mono">{{ $transaction->reference }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 border-b border-line font-mono text-sm tabular
                                                   {{ $transaction->type === 'credit' ? 'text-success' : 'text-danger' }}">
                                            {{ $transaction->type === 'credit' ? '+' : '−' }} {{ $transaction->amount()->format() }}
                                        </td>
                                        <td class="px-4 py-3 border-b border-line font-mono text-xs tabular text-muted">{{ $transaction->balanceAfter()->format() }}</td>
                                        <td class="px-4 py-3 border-b border-line text-xs text-subtle whitespace-nowrap">{{ $transaction->created_at?->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.table>
                    </div>
                @endif
            </x-ui.card>
        </div>

        <x-ui.card :title="__('اشحن بكود')"
                   :subtitle="__('اكتب الكود كما هو على الكرت — الشرطات جزء منه.')">
            @error('code')<x-ui.alert tone="danger" class="mb-3">{{ $message }}</x-ui.alert>@enderror

            <form method="POST" action="{{ url('/wallet/redeem') }}">
                @csrf
                <x-ui.field :label="__('الكود')" for="code">
                    <x-ui.input name="code" id="code" class="font-mono uppercase tracking-wider"
                                placeholder="ABCD-EFGH-JKLM-NPQR" autocomplete="off" />
                </x-ui.field>
                <x-ui.button type="submit" class="w-full">{{ __('شحن') }}</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</main>

<x-site.footer />
</x-layouts.app>
