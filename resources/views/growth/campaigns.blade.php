<x-layouts.admin :title="__('التسلسلات التسويقية')">

<x-ui.page-header :title="__('التسلسلات التسويقية')"
                  :subtitle="__('رسائل مؤجَّلة تُرسل لمن دخلها — ومن حقّق الهدف يخرج فوراً.')">
    <x-slot:actions>
        <x-ui.button type="button" x-data @click="$dispatch('open-modal', 'new-campaign')">{{ __('تسلسل جديد') }}</x-ui.button>
    </x-slot:actions>
</x-ui.page-header>

@if(session('status'))
    <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
@endif

@if($errors->any())
    <x-ui.alert tone="danger" class="mb-4">{{ $errors->first() }}</x-ui.alert>
@endif

@if($campaigns->isEmpty())
    <x-ui.card>
        <x-ui.empty :title="__('لا تسلسلات بعد')">
            {{ __('ابدأ بالسلة المتروكة: أعلى عائد في التجارة كلّها، وأسهل ما يُنسى.') }}
        </x-ui.empty>
    </x-ui.card>
@else
    <ul class="flex flex-col gap-3">
        @foreach($campaigns as $campaign)
            <li class="surface-card p-4 flex flex-wrap items-center gap-4">
                <div class="flex-1 min-w-0">
                    <p class="font-semibold">{{ $campaign->name }}</p>
                    <p class="text-2xs text-subtle mt-0.5">
                        {{ __(App\Modules\Growth\Models\Campaign::TRIGGERS[$campaign->trigger] ?? $campaign->trigger) }}
                        · {{ trans_choice('{0} بلا خطوات|{1} خطوة واحدة|{2} خطوتان|[3,10] :count خطوات|[11,*] :count خطوة', $campaign->steps()->count(), ['count' => $campaign->steps()->count()]) }}
                    </p>
                </div>

                <dl class="flex gap-x-6 text-sm">
                    <div>
                        <dt class="text-2xs text-subtle">{{ __('دخلوا') }}</dt>
                        <dd class="font-mono tabular">{{ number_format((int) $campaign->entered_count) }}</dd>
                    </div>
                    <div>
                        <dt class="text-2xs text-subtle">{{ __('تحوّلوا') }}</dt>
                        <dd class="font-mono tabular">{{ number_format((int) $campaign->converted_count) }}</dd>
                    </div>
                    <div>
                        <dt class="text-2xs text-subtle">{{ __('النسبة') }}</dt>
                        <dd class="font-mono tabular font-bold">{{ $campaign->conversionRate() }}%</dd>
                    </div>
                </dl>

                <x-ui.badge :tone="match($campaign->status) {
                    'active' => 'success', 'paused' => 'warning', default => 'neutral',
                }">
                    {{ __(['draft' => 'مسودّة', 'active' => 'يعمل', 'paused' => 'متوقّف'][$campaign->status]) }}
                </x-ui.badge>

                <x-ui.button as="a" :href="route('admin.campaigns.edit', ['id' => $campaign->id])"
                             size="sm" variant="secondary" class="w-full sm:w-auto sm:shrink-0">{{ __('حرّر') }}</x-ui.button>
            </li>
        @endforeach
    </ul>
@endif

<x-ui.modal name="new-campaign" :title="__('تسلسل جديد')">
    <form method="POST" action="{{ route('admin.campaigns.store') }}" class="flex flex-col gap-3">
        @csrf
        <x-ui.field :label="__('الاسم')" for="c-name" class="mb-0" required>
            <x-ui.input name="name" id="c-name" required maxlength="120" />
        </x-ui.field>
        <x-ui.field :label="__('المفتاح')" for="c-key" class="mb-0" required>
            <x-ui.input name="key" id="c-key" required maxlength="48" class="font-mono" />
        </x-ui.field>
        <x-ui.field :label="__('ما يُدخل الناس فيه')" for="c-trigger" class="mb-0" required>
            <x-ui.select name="trigger" id="c-trigger">
                @foreach(App\Modules\Growth\Models\Campaign::TRIGGERS as $value => $label)
                    <option value="{{ $value }}">{{ __($label) }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.button type="submit" class="self-start">{{ __('أنشئ وافتح المحرّر') }}</x-ui.button>
    </form>
</x-ui.modal>

</x-layouts.admin>
