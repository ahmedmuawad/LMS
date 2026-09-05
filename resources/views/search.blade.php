@php
    $labels = [
        'courses' => 'الكورسات', 'services' => 'الخدمات',
        'products' => 'المنتجات', 'posts' => 'المقالات',
    ];
    $total = array_sum($counts);
@endphp

<x-layouts.app :title="$term === '' ? __('البحث') : __('البحث عن :term', ['term' => $term])">
<x-site.header />

<main id="main" class="max-w-[860px] mx-auto px-4 sm:px-6 py-8">

    <h1 class="text-xl sm:text-2xl font-extrabold mb-5">{{ __('البحث') }}</h1>

    <form method="GET" action="{{ url('/search') }}" class="flex flex-wrap gap-2 mb-6" role="search">
        <div class="min-w-[200px] flex-1">
            <label for="q" class="sr-only">{{ __('ابحث') }}</label>
            <x-ui.input id="q" name="q" type="search" :value="$term" autofocus
                        :placeholder="__('اسم كورس أو خدمة أو مقال…')" />
        </div>
        @if($only)<input type="hidden" name="type" value="{{ $only }}">@endif
        <x-ui.button type="submit">{{ __('ابحث') }}</x-ui.button>
    </form>

    @if($term === '')
        <p class="text-sm text-muted">{{ __('اكتب حرفين على الأقل.') }}</p>

    @elseif($total === 0)
        <x-ui.card>
            <x-ui.empty :title="__('لا نتائج لـ «:term»', ['term' => $term])">
                {{ __('جرّب كلمةً أقصر، أو تصفّح الكورسات مباشرةً.') }}
                <x-slot:action>
                    <x-ui.button size="sm" :href="url('/courses')">{{ __('تصفّح الكورسات') }}</x-ui.button>
                </x-slot:action>
            </x-ui.empty>
        </x-ui.card>

    @else
        {{--
            العدّادات فلاترُ لا زينة: من رأى «مقالات ٣» يريد أن
            يضغطها فيرى الثلاثة، لا أن يقرأ الرقم ويبحث عنها بعينه.
        --}}
        <div class="flex flex-wrap gap-2 mb-6" role="group" aria-label="{{ __('تصفية حسب النوع') }}">
            <a href="{{ url('/search?q='.urlencode($term)) }}"
               @class([
                   'min-h-9 px-3 inline-flex items-center rounded-full text-xs font-semibold border transition-colors',
                   'bg-primary text-primary-on border-transparent' => $only === null,
                   'bg-surface text-muted border-line-strong hover:text-content' => $only !== null,
               ])>{{ __('الكل') }} <span class="font-mono tabular ms-1.5">{{ $total }}</span></a>

            @foreach($counts as $type => $count)
                @continue($count === 0)
                <a href="{{ url('/search?q='.urlencode($term).'&type='.$type) }}"
                   @class([
                       'min-h-9 px-3 inline-flex items-center rounded-full text-xs font-semibold border transition-colors',
                       'bg-primary text-primary-on border-transparent' => $only === $type,
                       'bg-surface text-muted border-line-strong hover:text-content' => $only !== $type,
                   ])>
                    {{ __($labels[$type] ?? $type) }}
                    <span class="font-mono tabular ms-1.5">{{ $count }}</span>
                </a>
            @endforeach
        </div>

        <div class="grid gap-2">
            @foreach($results as $hit)
                <a href="{{ $hit->url }}"
                   class="surface-card p-4 flex items-start gap-3 hover:border-primary transition-colors group">
                    <span class="shrink-0 text-lg leading-6 text-subtle" aria-hidden="true">{{ $hit->icon() }}</span>

                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-baseline gap-x-2">
                            <span class="font-semibold text-sm group-hover:text-primary transition-colors">{{ $hit->title }}</span>
                            <span class="text-2xs text-subtle">{{ $hit->typeLabel() }}</span>
                        </span>

                        @if($hit->excerpt !== '')
                            <span class="block text-xs text-muted leading-relaxed mt-1 line-clamp-2">{{ $hit->excerpt }}</span>
                        @endif
                    </span>
                </a>
            @endforeach
        </div>
    @endif

</main>

<x-site.footer />
</x-layouts.app>
