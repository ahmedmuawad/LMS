<x-layouts.admin :title="__('المسوّقون')">

<x-ui.page-header :title="__('التسويق بالعمولة')"
                  :subtitle="trans_choice('{0} لا مسوّقين|{1} مسوّق واحد|{2} مسوّقان|[3,10] :count مسوّقين|[11,*] :count مسوّقاً', $affiliates->total(), ['count' => $affiliates->total()])" />

@if(session('status'))
    <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
@endif

@unless(setting('growth.affiliates_enabled', false))
    <x-ui.alert tone="warning" :title="__('البرنامج معطّل')" class="mb-4">
        {{ __('لن تُحتسب عمولة ولا تُسجَّل نقرة حتى تفعّله من إعدادات النمو.') }}
    </x-ui.alert>
@endunless

@if($pendingConversions > 0)
    <x-ui.alert tone="info" class="mb-4">
        {{ trans_choice('{1} عمولة واحدة قيد النضج|{2} عمولتان قيد النضج|[3,10] :count عمولات قيد النضج|[11,*] :count عمولة قيد النضج', $pendingConversions, ['count' => $pendingConversions]) }}
        — {{ __('تُعتمد تلقائياً بعد انقضاء مهلة الاسترداد.') }}
    </x-ui.alert>
@endif

<form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
    <x-ui.field :label="__('الحالة')" for="status" class="mb-0 w-full sm:w-56">
        <x-ui.select name="status" id="status">
            <option value="">{{ __('الكل') }}</option>
            @foreach(App\Modules\Growth\Models\Affiliate::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ __($label) }}</option>
            @endforeach
        </x-ui.select>
    </x-ui.field>
    <x-ui.button size="sm" type="submit" class="h-11">{{ __('تصفية') }}</x-ui.button>
</form>

@if($affiliates->isEmpty())
    <x-ui.card>
        <x-ui.empty :title="__('لا مسوّقين بعد')">{{ __('فعّل البرنامج وشارك صفحته ليبدأ الانضمام.') }}</x-ui.empty>
    </x-ui.card>
@else
    <ul class="flex flex-col gap-3">
        @foreach($affiliates as $affiliate)
            <li class="surface-card p-4">
                <div class="flex flex-wrap items-start gap-4">
                    <x-ui.avatar :name="$affiliate->user?->name ?? ''" />

                    <div class="flex-1 min-w-0">
                        <p class="font-semibold">{{ $affiliate->user?->name }}</p>
                        <p class="text-2xs text-subtle font-mono mt-0.5">{{ $affiliate->code }}</p>
                    </div>

                    <dl class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                        <div>
                            <dt class="text-2xs text-subtle">{{ __('نقرات') }}</dt>
                            <dd class="font-mono tabular">{{ number_format((int) $affiliate->clicks_count) }}</dd>
                        </div>
                        <div>
                            <dt class="text-2xs text-subtle">{{ __('مبيعات') }}</dt>
                            <dd class="font-mono tabular">{{ number_format((int) $affiliate->conversions_count) }}</dd>
                        </div>
                        <div>
                            <dt class="text-2xs text-subtle">{{ __('تحويل') }}</dt>
                            <dd class="font-mono tabular">{{ $affiliate->conversionRate() }}%</dd>
                        </div>
                        <div>
                            <dt class="text-2xs text-subtle">{{ __('استحقّ') }}</dt>
                            <dd class="font-mono tabular font-bold">{{ $affiliate->earned()->format() }}</dd>
                        </div>
                    </dl>
                </div>

                <form method="POST" action="{{ route('admin.affiliates.update', ['id' => $affiliate->id]) }}"
                      class="mt-4 pt-3 border-t border-default flex flex-col sm:flex-row items-stretch sm:items-end gap-2">
                    @csrf
                    @method('PUT')

                    <x-ui.field :label="__('الحالة')" for="status-{{ $affiliate->id }}" class="mb-0 w-full sm:w-44">
                        <x-ui.select name="status" id="status-{{ $affiliate->id }}">
                            @foreach(App\Modules\Growth\Models\Affiliate::STATUSES as $value => $label)
                                <option value="{{ $value }}" @selected($affiliate->status === $value)>{{ __($label) }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field :label="__('نسبة خاصة')" for="rate-{{ $affiliate->id }}" class="mb-0 w-full sm:w-40"
                                :hint="__('فارغ = النسبة العامة')">
                        <x-ui.input type="number" name="commission_rate" id="rate-{{ $affiliate->id }}"
                                    min="0" max="90" step="0.5" value="{{ $affiliate->commission_rate }}" class="font-mono" />
                    </x-ui.field>

                    <x-ui.button type="submit" size="sm" class="h-11 w-full sm:w-auto sm:shrink-0">{{ __('حفظ') }}</x-ui.button>
                </form>
            </li>
        @endforeach
    </ul>

    @if($affiliates->hasPages())
        <div class="mt-6">
            <x-ui.pagination :current="$affiliates->currentPage()" :last="$affiliates->lastPage()"
                             :url="request()->fullUrlWithQuery(['page' => '']).''" />
        </div>
    @endif
@endif

</x-layouts.admin>
