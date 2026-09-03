<x-layouts.app :title="__('برنامج التسويق بالعمولة')">
<x-site.header />

<main id="main" class="max-w-[900px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.page-header :title="__('التسويق بالعمولة')"
                      :subtitle="__('شارك رابطك، ونحن نحتسب لك كل بيع جاء منه.')" />

    @if(session('status'))
        <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @if($affiliate === null)
        <div class="surface-card p-6">
            <h2 class="text-lg font-bold mb-2">{{ __('انضمّ إلى البرنامج') }}</h2>

            @if(setting()->translated('growth.affiliates_terms'))
                <div class="text-sm text-muted leading-relaxed whitespace-pre-line mb-5">{{ setting()->translated('growth.affiliates_terms') }}</div>
            @else
                <p class="text-sm text-muted leading-relaxed mb-5">
                    {{ __('تحصل على :rate% من كل بيع يأتي من رابطك، وتُصرف العمولة بعد :days يوماً من البيع.', [
                        'rate' => setting('growth.affiliates_default_rate', 10),
                        'days' => setting('growth.affiliates_hold_days', 14),
                    ]) }}
                </p>
            @endif

            <form method="POST" action="{{ url('/affiliate/join') }}" class="flex flex-col gap-3 max-w-md">
                @csrf

                <x-ui.field :label="__('طريقة الاستلام المفضّلة')" for="payout_method" class="mb-0">
                    <x-ui.select name="payout_method" id="payout_method">
                        <option value="bank">{{ __('تحويل بنكي') }}</option>
                        <option value="wallet">{{ __('محفظة إلكترونية') }}</option>
                        <option value="credit">{{ __('رصيد في المحفظة الداخلية') }}</option>
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field :label="__('كيف تنوي التسويق؟')" for="notes" class="mb-0">
                    <x-ui.textarea name="notes" id="notes" rows="3" :placeholder="__('قناة يوتيوب، مجموعة طلابية، مدونة…')" />
                </x-ui.field>

                <x-ui.button type="submit" class="self-start">{{ __('أرسل الطلب') }}</x-ui.button>
            </form>
        </div>
    @elseif($affiliate->status === 'pending')
        <x-ui.alert tone="info" :title="__('طلبك قيد المراجعة')">
            {{ __('سنراجع طلبك ونفعّل رابطك قريباً.') }}
        </x-ui.alert>
    @elseif($affiliate->status === 'suspended')
        <x-ui.alert tone="danger" :title="__('الحساب موقوف')">
            {{ __('راسلنا لمعرفة التفاصيل.') }}
        </x-ui.alert>
    @else
        <div class="surface-card p-5 mb-5">
            <p class="text-sm font-semibold mb-2">{{ __('رابطك') }}</p>
            <div class="flex flex-col sm:flex-row gap-2" x-data="{ copied: false }">
                <input type="text" readonly value="{{ $affiliate->link() }}"
                       class="flex-1 min-w-0 min-h-11 bg-surface-sunken text-content text-sm rounded-md border border-line-strong px-3 font-mono"
                       aria-label="{{ __('رابط التسويق') }}">
                <button type="button"
                        @click="navigator.clipboard.writeText('{{ $affiliate->link() }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="min-h-11 px-5 rounded-md bg-primary text-primary-on font-semibold text-sm w-full sm:w-auto sm:shrink-0">
                    <span x-show="! copied">{{ __('انسخ') }}</span>
                    <span x-show="copied" x-cloak>{{ __('نُسخ ✓') }}</span>
                </button>
            </div>
            <p class="text-2xs text-subtle mt-2">
                {{ __('أضف ?ref=:code إلى أي صفحة في الموقع.', ['code' => $affiliate->code]) }}
            </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <x-ui.stat :label="__('النقرات')" :value="number_format((int) $affiliate->clicks_count)" />
            <x-ui.stat :label="__('المبيعات')" :value="number_format((int) $affiliate->conversions_count)" />
            <x-ui.stat :label="__('نسبة التحويل')" :value="$affiliate->conversionRate().'%'" />
            <x-ui.stat :label="__('رصيدك')" :value="$affiliate->balance()->format()" />
        </div>

        @if($monthly !== [])
            <div class="surface-card p-5 mb-6">
                <h2 class="font-bold text-sm mb-4">{{ __('آخر ستة أشهر') }}</h2>
                @php $peak = max($monthly) ?: 1; @endphp
                <ul class="flex items-end gap-2 h-32">
                    @foreach($monthly as $month => $minor)
                        <li class="flex-1 flex flex-col items-center gap-1.5 min-w-0">
                            <span class="text-2xs font-mono text-subtle">{{ number_format($minor / 100, 0) }}</span>
                            <span class="w-full rounded-t bg-primary" style="height: {{ max(4, (int) round($minor / $peak * 90)) }}px"
                                  aria-hidden="true"></span>
                            <span class="text-2xs text-subtle truncate w-full text-center">{{ \Illuminate\Support\Carbon::parse($month.'-01')->translatedFormat('M') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <h2 class="text-lg font-bold mb-3">{{ __('آخر التحويلات') }}</h2>

        @if($conversions->isEmpty())
            <x-ui.card>
                <x-ui.empty :title="__('لا مبيعات بعد')">{{ __('شارك رابطك حيث يوجد من يهمّه ما تعرضه.') }}</x-ui.empty>
            </x-ui.card>
        @else
            <ul class="surface-card divide-y divide-[var(--color-line)]">
                @foreach($conversions as $conversion)
                    <li class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium">{{ $conversion->amount()->format() }}</p>
                            <p class="text-2xs text-subtle mt-0.5">{{ $conversion->created_at?->translatedFormat('j M Y') }}</p>
                        </div>
                        <div class="text-end shrink-0">
                            <p class="font-mono font-bold tabular text-success">{{ $conversion->commission()->format() }}</p>
                            <x-ui.badge :tone="match($conversion->status) {
                                'approved', 'paid' => 'success', 'rejected' => 'danger', default => 'warning',
                            }" class="mt-1">
                                {{ __(App\Modules\Growth\Models\AffiliateConversion::STATUSES[$conversion->status]) }}
                            </x-ui.badge>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</main>

<x-site.footer />
</x-layouts.app>
