<x-layouts.admin :title="__('نمط المنصة')" current="platform-mode">
<div class="max-w-[900px]">

    <x-ui.page-header :title="__('نمط المنصة')"
                      :subtitle="__('يحدّد النمط أقسام لوحتك: ما لا يخصّك يُخفى ولا يُحذف.')" />

    @if(session('status'))
        <x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>
    @endif

    @error('mode')
        <x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>
    @enderror

    <x-ui.alert tone="info" class="mb-6">
        {{ __('التبديل لا يحذف شيئاً. المجموعات والحصص والطلبة تبقى في مكانها، وتعود كما هي إن رجعت إلى النمط نفسه.') }}
    </x-ui.alert>

    <form method="POST" action="{{ route('admin.platform-mode.update') }}" x-data="{ mode: @js($current) }">
        @csrf
        @method('PUT')

        <fieldset class="mb-8">
            <legend class="text-sm font-bold mb-3">{{ __('ماذا تدير؟') }}</legend>

            <div class="grid gap-3 sm:grid-cols-2">
                @foreach($modes as $item)
                    <label @class([
                        'relative flex gap-3 p-4 rounded-lg border transition-colors',
                        'cursor-pointer' => $item['allowed'],
                        'opacity-55 cursor-not-allowed' => ! $item['allowed'],
                    ])
                           :class="mode === @js($item['key'])
                                ? 'border-primary bg-primary-subtle'
                                : 'border-line-strong bg-surface hover:border-line-stronger'">

                        <input type="radio" name="mode" value="{{ $item['key'] }}"
                               x-model="mode" class="sr-only"
                               @disabled(! $item['allowed'])
                               @checked($current === $item['key'])>

                        <span class="text-xl shrink-0 leading-none" aria-hidden="true">{{ $item['icon'] }}</span>

                        <span class="min-w-0">
                            <span class="block text-sm font-semibold">
                                {{ $item['name'] }}
                                @if($current === $item['key'])
                                    <span class="text-2xs font-normal text-muted">· {{ __('الحالي') }}</span>
                                @endif
                            </span>
                            <span class="block text-xs text-muted leading-relaxed mt-1">{{ $item['summary'] }}</span>

                            @unless($item['allowed'])
                                {{-- مقفول لا مخفيّ: من يقرأ هذه الشاشة هو من يملك الترقية --}}
                                <a href="{{ route('admin.billing') }}"
                                   class="tap-link inline-block text-2xs text-primary font-semibold mt-2 hover:underline">
                                    {{ __('غير متاح في باقتك — رقِّ الباقة') }} ←
                                </a>
                            @endunless
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="mb-8">
            <legend class="text-sm font-bold mb-3">{{ __('كيف تقدّم دروسك؟') }}</legend>

            <div class="grid gap-2 sm:grid-cols-3">
                @foreach($deliveries as $item)
                    <label class="flex items-center gap-2.5 min-h-11 px-3 rounded-lg border border-line-strong
                                  bg-surface cursor-pointer text-sm has-[:checked]:border-primary has-[:checked]:bg-primary-subtle">
                        <input type="radio" name="delivery" value="{{ $item['key'] }}"
                               class="accent-[var(--sem-primary)]"
                               @checked($currentDelivery === $item['key'])>
                        {{ $item['name'] }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="flex flex-wrap items-center gap-3 pt-5 border-t border-line">
            <x-ui.button type="submit">{{ __('حفظ النمط') }}</x-ui.button>
            <p class="text-xs text-muted">{{ __('ستتغيّر القائمة الجانبية فور الحفظ.') }}</p>
        </div>
    </form>

</div>
</x-layouts.admin>
