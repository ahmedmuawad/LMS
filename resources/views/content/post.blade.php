@php
    $meta = app(App\Core\Seo\Seo::class)->forModel($post, [
        'breadcrumbs' => [
            ['name' => __('المدونة'), 'url' => url('/blog')],
            ['name' => (string) $post->title, 'url' => url('/blog/'.$post->slug)],
        ],
    ]);
@endphp
<x-layouts.app :title="$post->title" :meta="$meta">
<x-site.header />

<main id="main" class="max-w-[760px] mx-auto px-4 sm:px-6 py-8">

    @unless($post->status === 'published')
        <x-ui.alert tone="warning" :title="__('معاينة مسودّة')" class="mb-6">
            {{ __('هذا المقال غير منشور — لا يراه الزوّار.') }}
        </x-ui.alert>
    @endunless

    <x-ui.breadcrumb :items="[
        ['label' => __('المدونة'), 'url' => url('/blog')],
        ['label' => $post->title],
    ]" />

    <article class="mt-4">
        <header class="flex flex-col gap-3">
            @if($post->category)
                <a href="{{ url('/blog?category='.$post->category->id) }}" class="tap-link text-2xs text-primary self-start">{{ $post->category->name }}</a>
            @endif

            <h1 class="text-2xl sm:text-3xl font-bold leading-tight">{{ $post->title }}</h1>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-2xs text-subtle">
                @if($post->author)<span>{{ $post->author->name }}</span>@endif
                <span>{{ $post->published_at?->translatedFormat('j F Y') }}</span>
                @if(setting('content.reading_time', true) && $post->reading_minutes > 0)
                    <span class="font-mono">{{ trans_choice('{1} دقيقة قراءة|{2} دقيقتان|[3,10] :count دقائق قراءة|[11,*] :count دقيقة قراءة', $post->reading_minutes, ['count' => $post->reading_minutes]) }}</span>
                @endif
                <span class="font-mono tabular">{{ trans_choice('{0} لا مشاهدات|{1} مشاهدة|{2} مشاهدتان|[3,10] :count مشاهدات|[11,*] :count مشاهدة', (int) $post->views_count, ['count' => (int) $post->views_count]) }}</span>
            </div>
        </header>

        @if($post->cover)
            <img src="{{ $post->cover->url() }}" alt="{{ $post->cover->alt ?? '' }}"
                 class="w-full rounded-lg my-6 bg-surface-sunken" width="{{ $post->cover->width }}" height="{{ $post->cover->height }}">
        @endif

        @if(filled($post->blocks))
            <div class="-mx-4 sm:-mx-6">
                <x-blocks.renderer :blocks="$post->blocks" />
            </div>
        @else
            <div class="mt-6 leading-loose whitespace-pre-line">{{ $post->body }}</div>
        @endif

        @if($post->tags->isNotEmpty())
            <div class="flex flex-wrap gap-2 mt-8">
                @foreach($post->tags as $tag)
                    <x-ui.tag>{{ $tag->name }}</x-ui.tag>
                @endforeach
            </div>
        @endif
    </article>

    {{-- ---------- التعليقات ---------- --}}
    @php $policy = (string) setting('content.comments', 'users'); @endphp

    @if($policy !== 'off' && $post->allow_comments)
        <section id="comments" class="mt-10 pt-8 border-t border-default">
            <h2 class="text-xl font-bold mb-4">
                {{ trans_choice('{0} لا تعليقات بعد|{1} تعليق واحد|{2} تعليقان|[3,10] :count تعليقات|[11,*] :count تعليقاً', $comments->count(), ['count' => $comments->count()]) }}
            </h2>

            @if($comments->isNotEmpty())
                <ol class="flex flex-col gap-4 mb-8">
                    @foreach($comments as $comment)
                        <li class="surface-card p-4">
                            <div class="flex items-start gap-3">
                                <x-ui.avatar :name="$comment->authorName()" size="sm" />
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-baseline gap-x-2">
                                        <span class="font-semibold text-sm">{{ $comment->authorName() }}</span>
                                        <span class="text-2xs text-subtle">{{ $comment->created_at?->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-sm text-muted leading-relaxed mt-1 whitespace-pre-line">{{ $comment->body }}</p>
                                </div>
                            </div>

                            @if($comment->replies->isNotEmpty())
                                <ol class="mt-4 ms-6 sm:ms-10 flex flex-col gap-3 border-s-2 border-default ps-4">
                                    @foreach($comment->replies as $reply)
                                        <li class="flex items-start gap-3">
                                            <x-ui.avatar :name="$reply->authorName()" size="sm" />
                                            <div class="flex-1 min-w-0">
                                                <div class="flex flex-wrap items-baseline gap-x-2">
                                                    <span class="font-semibold text-sm">{{ $reply->authorName() }}</span>
                                                    <span class="text-2xs text-subtle">{{ $reply->created_at?->diffForHumans() }}</span>
                                                </div>
                                                <p class="text-sm text-muted leading-relaxed mt-1 whitespace-pre-line">{{ $reply->body }}</p>
                                            </div>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif

            @if($policy === 'users' && auth()->guest())
                <x-ui.card>
                    <p class="text-sm text-muted">
                        {{ __('سجّل دخولك للمشاركة في النقاش.') }}
                        <a href="{{ url('/login') }}" class="tap-link text-primary font-semibold">{{ __('تسجيل الدخول') }}</a>
                    </p>
                </x-ui.card>
            @else
                <form method="POST" action="{{ url('/blog/'.$post->slug.'/comments') }}" class="surface-card p-4 flex flex-col gap-3">
                    @csrf

                    @guest
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ui.field :label="__('الاسم')" for="author_name" class="mb-0">
                                <x-ui.input name="author_name" id="author_name" required maxlength="80" />
                            </x-ui.field>
                            <x-ui.field :label="__('البريد')" for="author_email" class="mb-0" :hint="__('لا يُنشر.')">
                                <x-ui.input name="author_email" id="author_email" type="email" />
                            </x-ui.field>
                        </div>
                    @endguest

                    <x-ui.field :label="__('تعليقك')" for="body" class="mb-0" :error="$errors->first('body') ?: $errors->first('comment')">
                        <x-ui.textarea name="body" id="body" rows="4" required :placeholder="__('شاركنا رأيك…')" />
                    </x-ui.field>

                    <x-ui.button type="submit" class="self-start">{{ __('أرسل التعليق') }}</x-ui.button>
                </form>
            @endif
        </section>
    @endif

    @if($related->isNotEmpty())
        <section class="mt-10 pt-8 border-t border-default">
            <h2 class="text-xl font-bold mb-4">{{ __('اقرأ أيضاً') }}</h2>
            <ul class="grid gap-3 sm:grid-cols-3">
                @foreach($related as $other)
                    <li class="surface-card p-3">
                        <a href="{{ url('/blog/'.$other->slug) }}" class="tap-link font-semibold text-sm leading-snug hover:text-primary transition-colors">{{ $other->title }}</a>
                        <p class="text-2xs text-subtle mt-1">{{ $other->published_at?->translatedFormat('j F Y') }}</p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</main>

<x-site.footer />
</x-layouts.app>
