@php
    $canEnrol = $enrollment === null && $course->isOpenForEnrollment();
    $totalItems = collect($sections)->sum('total');

    $meta = app(App\Core\Seo\Seo::class)->forModel($course, [
        'breadcrumbs' => [
            ['name' => __('الكورسات'), 'url' => url('/courses')],
            ['name' => (string) $course->title, 'url' => url('/courses/'.$course->slug)],
        ],
    ]);
@endphp

<x-layouts.app :title="$course->title" :meta="$meta">

{{-- حدث «شوهد المنتج»: أساس إعادة الاستهداف في كل المنصّات --}}
<x-analytics.event name="view_item" :data="[
    'currency' => (string) $course->currency,
    'value' => round((int) $course->price_minor / 100, 2),
    'items' => [[
        'item_id' => (string) $course->slug,
        'item_name' => (string) $course->title,
        'item_category' => (string) ($course->category?->name ?? ''),
        'price' => round((int) $course->price_minor / 100, 2),
    ]],
]" />
<x-site.header />

<main id="main" class="max-w-[1200px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.breadcrumb class="mb-4" :items="[
        ['label' => __('الكورسات'), 'url' => url('/courses')],
        ['label' => $course->title],
    ]" />

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px] items-start">

        <div class="min-w-0">
            <h1 class="text-2xl sm:text-3xl font-bold leading-tight mb-3">{{ $course->title }}</h1>

            @if($course->excerpt)
                <p class="text-muted leading-relaxed mb-4">{{ $course->excerpt }}</p>
            @endif

            <div class="flex flex-wrap items-center gap-2 mb-6">
                @if($course->category)<x-ui.badge tone="primary">{{ $course->category->name }}</x-ui.badge>@endif
                @if($course->level)<x-ui.badge>{{ $course->level->name }}</x-ui.badge>@endif
                @if($course->instructor)
                    <span class="text-sm text-muted">{{ __('المدرّس:') }} <span class="font-medium text-content">{{ $course->instructor->name() }}</span></span>
                @endif
            </div>

            @if(filled($course->outcomes))
                <x-ui.card :title="__('ماذا ستتعلّم')" class="mb-4">
                    <ul class="grid gap-2 sm:grid-cols-2">
                        @foreach($course->outcomes as $outcome)
                            <li class="flex items-start gap-2 text-sm">
                                <span class="text-success shrink-0 mt-0.5" aria-hidden="true">✓</span>
                                <span>{{ is_array($outcome) ? ($outcome[app()->getLocale()] ?? reset($outcome)) : $outcome }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            <x-ui.card :title="__('المنهج')"
                       :subtitle="trans_choice('{0} لا عناصر بعد|{1} عنصر واحد|{2} عنصران|[3,10] :count عناصر|[11,*] :count عنصراً', $totalItems, ['count' => $totalItems])"
                       :padding="false" class="mb-4">
                @if($sections === [])
                    <div class="p-5"><x-ui.empty :title="__('المنهج قيد الإعداد')">{{ __('سيظهر هنا فور إضافة أول درس.') }}</x-ui.empty></div>
                @else
                    <div class="divide-y divide-[var(--color-line)]">
                        @foreach($sections as $section)
                            <details class="group" @if($loop->first) open @endif>
                                <summary class="flex items-center justify-between gap-3 px-5 py-3.5 cursor-pointer hover:bg-surface-sunken transition-colors">
                                    <span class="font-semibold text-sm min-w-0 truncate">{{ $section['title'] }}</span>
                                    <span class="text-2xs text-subtle font-mono tabular shrink-0">{{ $section['done'] }}/{{ $section['total'] }}</span>
                                </summary>
                                <ul>
                                    @foreach($section['items'] as $row)
                                        <li class="flex items-center gap-3 px-5 py-2.5 border-t border-line text-sm">
                                            <span aria-hidden="true" class="text-subtle w-4 text-center shrink-0">{{ $row['item']->icon() }}</span>
                                            <span class="flex-1 min-w-0 truncate {{ $row['locked'] ? 'text-subtle' : '' }}">{{ $row['title'] }}</span>

                                            @if($row['completed'])
                                                <x-ui.badge tone="success">{{ __('مكتمل') }}</x-ui.badge>
                                            @elseif($row['preview'] && $enrollment === null)
                                                <x-ui.badge tone="info">{{ __('معاينة مجانية') }}</x-ui.badge>
                                            @elseif($row['locked'])
                                                <span class="text-2xs text-subtle shrink-0">{{ $row['lock_reason'] }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            @if(filled($course->requirements))
                <x-ui.card :title="__('المتطلبات')" class="mb-4">
                    <ul class="grid gap-1.5 text-sm list-disc list-inside text-muted">
                        @foreach($course->requirements as $requirement)
                            <li>{{ is_array($requirement) ? ($requirement[app()->getLocale()] ?? reset($requirement)) : $requirement }}</li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            @if($course->description)
                <x-ui.card :title="__('عن الكورس')" class="mb-4">
                    <div class="prose-sm leading-relaxed whitespace-pre-line text-muted">{{ $course->description }}</div>
                </x-ui.card>
            @endif

            <x-ui.card :title="__('آراء الطلاب')">
                @if($reviews->isEmpty())
                    <x-ui.empty :title="__('لا آراء بعد')">{{ __('كن أول من يشارك رأيه بعد إتمام الكورس.') }}</x-ui.empty>
                @else
                    <ul class="grid gap-4">
                        @foreach($reviews as $review)
                            <li class="flex gap-3">
                                <x-ui.avatar :name="$review->user?->name ?? '؟'" size="sm" class="shrink-0" />
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold">{{ $review->user?->name }}</p>
                                    <p class="text-2xs text-warning" aria-label="{{ __(':n من ٥', ['n' => $review->rating]) }}">
                                        {{ str_repeat('★', $review->rating) }}<span class="text-subtle">{{ str_repeat('★', 5 - $review->rating) }}</span>
                                    </p>
                                    @if($review->body)<p class="text-sm text-muted mt-1 leading-relaxed">{{ $review->body }}</p>@endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- نموذج التقييم لمن يحقّ له: الأهلية تُفحص في الخادم أيضاً --}}
                @auth
                    @if($enrollment !== null && setting('community.reviews', true))
                        <form method="POST" action="{{ url('/courses/'.$course->slug.'/reviews') }}"
                              class="mt-5 pt-5 border-t border-default flex flex-col gap-3"
                              x-data="{ rating: {{ (int) old('rating', 0) }} }">
                            @csrf

                            <p class="text-sm font-semibold">{{ __('قيّم الكورس') }}</p>

                            @if($errors->has('review'))
                                <x-ui.alert tone="danger">{{ $errors->first('review') }}</x-ui.alert>
                            @endif

                            <div class="flex items-center gap-1" role="radiogroup" aria-label="{{ __('التقييم من خمس') }}">
                                @foreach(range(1, 5) as $star)
                                    <button type="button" @click="rating = {{ $star }}"
                                            :class="rating >= {{ $star }} ? 'text-warning' : 'text-subtle'"
                                            class="size-11 grid place-items-center text-xl transition-colors"
                                            :aria-checked="rating === {{ $star }}" role="radio"
                                            aria-label="{{ trans_choice('{1} نجمة واحدة|{2} نجمتان|[3,10] :count نجوم', $star, ['count' => $star]) }}">★</button>
                                @endforeach
                            </div>

                            <input type="hidden" name="rating" :value="rating">

                            <x-ui.textarea name="body" rows="3" :placeholder="__('ما الذي أفادك؟ وما الذي ينقصه؟')" />

                            <x-ui.button type="submit" size="sm" class="self-start" x-bind:disabled="rating === 0">
                                {{ __('أرسل التقييم') }}
                            </x-ui.button>
                        </form>
                    @endif
                @endauth
            </x-ui.card>

            {{-- سؤال المدرّس: من يجد من يسأله يكمل الكورس --}}
            @if(setting('community.discussions', true))
                <x-ui.card :title="__('اسأل المدرّس')" class="mt-4">
                    @auth
                        @if($enrollment !== null)
                            <form method="POST" action="{{ url('/courses/'.$course->slug.'/discussions') }}" class="flex flex-col gap-3">
                                @csrf

                                @if($errors->has('discussion'))
                                    <x-ui.alert tone="danger">{{ $errors->first('discussion') }}</x-ui.alert>
                                @endif

                                <x-ui.field :label="__('عنوان السؤال')" for="q-title" class="mb-0" :error="$errors->first('title')">
                                    <x-ui.input name="title" id="q-title" required minlength="5" maxlength="200"
                                                :placeholder="__('ما الذي لم يتّضح لك؟')" />
                                </x-ui.field>

                                <x-ui.field :label="__('تفاصيل')" for="q-body" class="mb-0" :error="$errors->first('body')">
                                    <x-ui.textarea name="body" id="q-body" rows="4" required minlength="10" />
                                </x-ui.field>

                                <x-ui.button type="submit" size="sm" class="self-start">{{ __('اطرح السؤال') }}</x-ui.button>
                            </form>
                        @else
                            <p class="text-sm text-muted">{{ __('سجّل في الكورس لتسأل المدرّس وترى أسئلة زملائك.') }}</p>
                        @endif
                    @else
                        <p class="text-sm text-muted">
                            {{ __('سجّل دخولك لتسأل.') }}
                            <a href="{{ url('/login') }}" class="tap-link text-primary font-semibold">{{ __('تسجيل الدخول') }}</a>
                        </p>
                    @endauth

                    <p class="mt-4 pt-4 border-t border-default">
                        <a href="{{ url('/discussions?course='.$course->id) }}" class="tap-link text-sm text-primary font-semibold">
                            {{ __('كل أسئلة هذا الكورس') }}
                        </a>
                    </p>
                </x-ui.card>
            @endif
        </div>

        {{-- بطاقة الشراء --}}
        <aside class="lg:sticky lg:top-20 min-w-0">
            <x-ui.card :padding="false">
                <div class="aspect-video bg-surface-sunken relative">
                    @if($course->cover_path)
                        <img src="{{ $course->cover_path }}" alt="" class="size-full object-cover">
                    @else
                        <span class="absolute inset-0 grid place-items-center text-4xl text-subtle" aria-hidden="true">▤</span>
                    @endif
                </div>

                <div class="p-5 grid gap-3">
                    <p class="font-mono text-2xl font-medium tabular">
                        @if($course->isFree())
                            <span class="text-success">{{ __('مجاني') }}</span>
                        @else
                            {{ $course->price()->format() }}
                        @endif
                    </p>

                    @error('enroll')<x-ui.alert tone="danger">{{ $message }}</x-ui.alert>@enderror

                    @if($enrollment !== null)
                        <x-ui.progress :value="$enrollment->progress_percent" :label="__('تقدّمك')" />
                        <p class="text-2xs text-subtle font-mono tabular">{{ $enrollment->progress_percent }}%</p>
                        <x-ui.button :href="url('/learn/'.$course->slug)" class="w-full">
                            {{ $enrollment->progress_percent > 0 ? __('أكمل من حيث توقّفت') : __('ابدأ التعلّم') }}
                        </x-ui.button>
                        @if($enrollment->daysLeft() !== null)
                            <p class="text-2xs text-subtle text-center">
                                {{ trans_choice('{0} انتهت مدة وصولك|{1} يتبقّى يوم واحد|{2} يتبقّى يومان|[3,10] يتبقّى :count أيام|[11,*] يتبقّى :count يوماً', $enrollment->daysLeft(), ['count' => $enrollment->daysLeft()]) }}
                            </p>
                        @endif
                    @elseif($canEnrol && $course->isFree())
                        <form method="POST" action="{{ url('/courses/'.$course->slug.'/enroll') }}">
                            @csrf
                            <x-ui.button type="submit" class="w-full">{{ __('سجّل مجاناً') }}</x-ui.button>
                        </form>
                    @elseif($canEnrol)
                        <form method="POST" action="{{ url('/cart/add') }}">
                            @csrf
                            <input type="hidden" name="course_id" value="{{ $course->id }}">
                            <x-ui.button type="submit" class="w-full">{{ __('أضف إلى السلة') }}</x-ui.button>
                        </form>
                    @else
                        <x-ui.alert tone="warning">{{ __('التسجيل في هذا الكورس مغلق الآن.') }}</x-ui.alert>
                    @endif

                    <x-ui.description-list :items="array_filter([
                        __('العناصر') => $totalItems ?: null,
                        __('المدة') => $course->duration_minutes > 0
                            ? trans_choice('{1} دقيقة|{2} دقيقتان|[3,10] :count دقائق|[11,*] :count دقيقة', (int) $course->duration_minutes, ['count' => (int) $course->duration_minutes])
                            : null,
                        __('مدة الوصول') => (int) $course->access_days === 0
                            ? __('مدى الحياة')
                            : trans_choice('{1} يوم واحد|{2} يومان|[3,10] :count أيام|[11,*] :count يوماً', (int) $course->access_days, ['count' => (int) $course->access_days]),
                        __('الشهادة') => $course->certificate_enabled ? __('عند الإكمال') : null,
                        __('اللغة') => config('locales.supported.'.$course->language.'.native') ?? $course->language,
                    ])" />
                </div>
            </x-ui.card>
        </aside>
    </div>
</main>

<x-site.footer />
</x-layouts.app>
