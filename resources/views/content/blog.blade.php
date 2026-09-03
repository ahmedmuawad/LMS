<x-layouts.app :title="__('المدونة')">
<x-site.header />

<main id="main" class="max-w-[1200px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.page-header :title="__('المدونة')"
                      :subtitle="trans_choice('{0} لا مقالات بعد|{1} مقال واحد|{2} مقالان|[3,10] :count مقالات|[11,*] :count مقالاً', $posts->total(), ['count' => $posts->total()])" />

    <form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
        <x-ui.field :label="__('ابحث')" for="q" class="mb-0 w-full sm:w-72">
            <x-ui.input name="q" id="q" type="search" value="{{ request('q') }}" :placeholder="__('عنوان المقال…')" />
        </x-ui.field>
        <x-ui.field :label="__('القسم')" for="category" class="mb-0 w-full sm:w-56">
            <x-ui.select name="category" id="category">
                <option value="">{{ __('كل الأقسام') }}</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.button size="sm" type="submit" class="h-11">{{ __('تصفية') }}</x-ui.button>
    </form>

    @if($posts->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا مقالات مطابقة')">{{ __('جرّب كلمة أعمّ أو امسح الفلاتر.') }}</x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($posts as $post)
                <article class="surface-card overflow-hidden flex flex-col">
                    <a href="{{ url('/blog/'.$post->slug) }}" class="block aspect-[16/9] bg-surface-sunken relative overflow-hidden">
                        @if($post->cover)
                            <img src="{{ $post->cover->url() }}" alt="{{ $post->cover->alt ?? '' }}" class="size-full object-cover" loading="lazy">
                        @else
                            <span class="absolute inset-0 grid place-items-center text-3xl text-subtle" aria-hidden="true">¶</span>
                        @endif
                    </a>

                    <div class="p-4 flex flex-col gap-2 flex-1 min-w-0">
                        @if($post->category)<span class="text-2xs text-subtle">{{ $post->category->name }}</span>@endif

                        <h2 class="font-bold leading-snug">
                            <a href="{{ url('/blog/'.$post->slug) }}" class="tap-link hover:text-primary transition-colors">{{ $post->title }}</a>
                        </h2>

                        @if($post->excerpt)
                            <p class="text-sm text-muted leading-relaxed line-clamp-3">{{ $post->excerpt }}</p>
                        @endif

                        <div class="flex items-center justify-between gap-2 text-2xs text-subtle mt-auto pt-2">
                            <span>{{ $post->published_at?->translatedFormat('j F Y') }}</span>
                            @if(setting('content.reading_time', true) && $post->reading_minutes > 0)
                                <span class="font-mono">{{ trans_choice('{1} دقيقة قراءة|{2} دقيقتان|[3,10] :count دقائق قراءة|[11,*] :count دقيقة قراءة', $post->reading_minutes, ['count' => $post->reading_minutes]) }}</span>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        @if($posts->hasPages())
            <div class="mt-6">
                <x-ui.pagination :current="$posts->currentPage()" :last="$posts->lastPage()"
                                 :url="request()->fullUrlWithQuery(['page' => '']).''" />
            </div>
        @endif
    @endif
</main>

<x-site.footer />
</x-layouts.app>
