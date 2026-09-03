<x-layouts.app :title="__('النقاش والأسئلة')">
<x-site.header />

<main id="main" class="max-w-[900px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.page-header :title="__('النقاش والأسئلة')"
                      :subtitle="trans_choice('{0} لا أسئلة بعد|{1} سؤال واحد|{2} سؤالان|[3,10] :count أسئلة|[11,*] :count سؤالاً', $discussions->total(), ['count' => $discussions->total()])" />

    <form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
        <x-ui.field :label="__('ابحث')" for="q" class="mb-0 w-full sm:w-64">
            <x-ui.input name="q" id="q" type="search" value="{{ request('q') }}" :placeholder="__('كلمة من السؤال…')" />
        </x-ui.field>
        <x-ui.field :label="__('الكورس')" for="course" class="mb-0 w-full sm:w-56">
            <x-ui.select name="course" id="course">
                <option value="">{{ __('كل الكورسات') }}</option>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}" @selected(request('course') == $course->id)>{{ $course->title }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.field :label="__('العرض')" for="filter" class="mb-0 w-full sm:w-44">
            <x-ui.select name="filter" id="filter">
                <option value="">{{ __('الكل') }}</option>
                <option value="unanswered" @selected(request('filter') === 'unanswered')>{{ __('بلا إجابة') }}</option>
            </x-ui.select>
        </x-ui.field>
        <x-ui.button size="sm" type="submit" class="h-11">{{ __('تصفية') }}</x-ui.button>
    </form>

    @if($discussions->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا أسئلة مطابقة')">{{ __('اسأل من صفحة الدرس، ويصل سؤالك للمدرّس وزملائك.') }}</x-ui.empty>
        </x-ui.card>
    @else
        <ul class="flex flex-col gap-2">
            @foreach($discussions as $discussion)
                <li class="surface-card p-4 flex items-start gap-4">
                    <div class="shrink-0 text-center w-14">
                        <p class="font-mono text-lg font-bold tabular">{{ $discussion->votes_count }}</p>
                        <p class="text-2xs text-subtle">{{ __('صوت') }}</p>
                    </div>

                    <div class="shrink-0 text-center w-14">
                        <p @class(['font-mono text-lg font-bold tabular', 'text-success' => $discussion->isAnswered()])>{{ $discussion->replies_count }}</p>
                        <p class="text-2xs text-subtle">{{ __('ردّ') }}</p>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            @if($discussion->is_pinned)<x-ui.badge tone="accent">{{ __('مثبّت') }}</x-ui.badge>@endif
                            @if($discussion->isAnswered())<x-ui.badge tone="success">{{ __('أُجيب') }}</x-ui.badge>@endif
                        </div>

                        <h2 class="font-semibold leading-snug">
                            <a href="{{ url('/discussions/'.$discussion->id) }}" class="tap-link hover:text-primary transition-colors">{{ $discussion->title }}</a>
                        </h2>

                        <p class="text-sm text-muted line-clamp-2 mt-1">{{ $discussion->body }}</p>

                        <p class="text-2xs text-subtle mt-2">
                            {{ $discussion->user?->name }}
                            @if($discussion->course) · {{ $discussion->course->title }} @endif
                            · {{ $discussion->created_at?->diffForHumans() }}
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>

        @if($discussions->hasPages())
            <div class="mt-6">
                <x-ui.pagination :current="$discussions->currentPage()" :last="$discussions->lastPage()"
                                 :url="request()->fullUrlWithQuery(['page' => '']).''" />
            </div>
        @endif
    @endif
</main>

<x-site.footer />
</x-layouts.app>
