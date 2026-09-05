<x-layouts.admin :title="__('نصوص الواجهة')" current="translations">
<div class="max-w-[1000px]">

    <x-ui.page-header :title="__('نصوص الواجهة')"
                      :subtitle="__('ترجم منصّتك، أو أعد صياغة كلماتها بلغة طلبتك.')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif

    {{--
        العدّاد أولاً: من يفتح الشاشة يسأل «كم بقي؟» لا «كم نصّاً عندكم؟»
    --}}
    <div class="surface-card p-4 mb-5 flex flex-wrap items-center gap-4">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold">
                {{ __(':done من :total نصّاً مترجَمة', ['done' => $done, 'total' => $total]) }}
            </p>
            <div class="mt-2 h-1.5 w-full max-w-xs rounded-full bg-surface-sunken overflow-hidden">
                <div class="h-full bg-primary" style="width: {{ $total > 0 ? round($done / $total * 100) : 0 }}%"></div>
            </div>
        </div>

        {{--
            إعادة المسح بعد كل نشر.

            النصوص تُقرأ من الكود وتُخزَّن مؤقتاً ساعة؛ ومن أضاف شاشةً
            جديدة لا ينتظر ساعةً ليترجمها.
        --}}
        <form method="POST" action="{{ route('admin.translations.rescan') }}" class="shrink-0">
            @csrf
            <x-ui.button type="submit" variant="secondary" size="sm">{{ __('أعد مسح النصوص') }}</x-ui.button>
        </form>
    </div>

    <form method="GET" class="surface-card p-4 mb-5 grid gap-3 sm:grid-cols-[1fr_auto_auto_auto]">
        <x-ui.input name="q" :value="$search" :placeholder="__('ابحث في النصّ أو ترجمته…')" />

        <x-ui.select name="locale" aria-label="{{ __('اللغة') }}">
            @foreach($locales as $code => $label)
                <option value="{{ $code }}" @selected($locale === $code)>{{ $label }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.select name="filter" aria-label="{{ __('التصفية') }}">
            <option value="missing" @selected($filter === 'missing')>{{ __('غير المترجَم') }}</option>
            <option value="done" @selected($filter === 'done')>{{ __('المترجَم') }}</option>
            <option value="all" @selected($filter === 'all')>{{ __('الكل') }}</option>
        </x-ui.select>

        <x-ui.button type="submit" variant="secondary">{{ __('اعرض') }}</x-ui.button>
    </form>

    @if($rows->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا نتائج')">
                {{ $filter === 'missing'
                    ? __('كل النصوص المطابقة مترجَمة. جرّب «الكل».')
                    : __('لا نصّ يطابق بحثك.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <form method="POST" action="{{ route('admin.translations.store') }}">
            @csrf
            <input type="hidden" name="locale" value="{{ $locale }}">

            <div class="grid gap-2 mb-5">
                @foreach($rows as $source)
                    {{--
                        اسم الحقل مُرمَّزٌ بـbase64.

                        النصّ العربي فيه نقاطٌ وأقواس، وPHP تُحوّل النقطة
                        في اسم الحقل إلى شرطةٍ سفلية — فيعود مفتاحٌ غير
                        الذي أُرسل، وتُحفظ الترجمة لنصٍّ لا وجود له.
                    --}}
                    @php $key = base64_encode($source); @endphp

                    <div class="surface-card p-3 grid gap-2 sm:grid-cols-2 items-start">
                        <p class="text-sm leading-relaxed min-w-0 break-words" dir="rtl">{{ $source }}</p>

                        <div class="min-w-0">
                            <x-ui.input :name="'values['.$key.']'"
                                        :value="$saved[$source] ?? ''"
                                        :dir="($locales[$locale] ?? '') === 'العربية' ? 'rtl' : 'ltr'"
                                        :placeholder="__('اتركه فارغاً ليبقى كما هو')" />

                            {{--
                                النائب يُذكَّر به: مترجِمٌ يحذف `:name`
                                يُنتج جملةً ناقصةً بلا اسم الطالب — ولا
                                رسالة خطأ تُنبّهه.
                            --}}
                            @if(str_contains($source, ':'))
                                @php preg_match_all('/:[a-z_]+/i', $source, $vars); @endphp
                                @if($vars[0] !== [])
                                    <p class="text-2xs text-warning mt-1 font-mono" dir="ltr">
                                        {{ __('أبقِ:') }} {{ implode(' · ', $vars[0]) }}
                                    </p>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center gap-3 mb-5">
                <x-ui.button type="submit">{{ __('احفظ هذه الصفحة') }}</x-ui.button>
                <p class="text-2xs text-muted">{{ __('الحفظ يخصّ الصفحة المعروضة وحدها.') }}</p>
            </div>
        </form>

        {{ $rows->links() }}
    @endif

</div>
</x-layouts.admin>
