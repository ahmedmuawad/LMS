@php
    use App\Modules\Lms\Models\Lesson;
    use App\Modules\Lms\Models\Quiz;

    /*
     | صفحة الكورس في «لوح الشرح».
     |
     | عمودان: المنهج يُقرأ، والبطاقة تُشترى. البطاقة ملتصقة بأعلى
     | الشاشة على الحواسيب لأن قرار الشراء يُتّخذ بعد قراءة المنهج
     | لا قبله — فيجب أن يجد الزرّ حيث توقّف لا حيث بدأ.
     */
    $canEnrol = $enrollment === null && $course->isOpenForEnrollment();
    $totalItems = collect($sections)->sum('total');
    $quizCount = collect($sections)->flatMap(fn (array $s): array => $s['items'])
        ->filter(fn (array $r): bool => $r['kind'] === 'quiz')->count();
    $hours = (int) round((int) $course->duration_minutes / 60);

    $compare = (int) ($course->compare_price_minor ?? 0);
    $hasDiscount = ! $course->isFree() && $compare > (int) $course->price_minor;

    // الدرس المفتوح للجميع: هو ما يجعل «شاهد درساً مجاناً» زرّاً لا وعداً
    $previewItem = collect($sections)->flatMap(fn (array $s): array => $s['items'])
        ->firstWhere('preview', true);

    $gatewayLabels = collect(config('payments.gateways', []))
        ->keyBy('key')
        ->map(fn (array $g): string => trim(explode('—', (string) ($g['label'] ?? ''))[0]));

    $payments = module_enabled('payments')
        ? collect(app(App\Modules\Commerce\Gateways\GatewayManager::class)->available($course->price()))
            ->map(fn ($g): string => (string) ($gatewayLabels[$g->key()] ?? $g->key()))
            ->filter()->values()
        : collect();

    $includes = array_values(array_filter([
        $totalItems > 0
            ? trans_choice('{1} عنصر واحد في المنهج|{2} عنصران في المنهج|[3,10] :count عناصر في المنهج|[11,*] :count عنصراً في المنهج', $totalItems, ['count' => $totalItems])
            : null,
        $quizCount > 0
            ? trans_choice('{1} اختبار بتصحيح فوري|{2} اختباران بتصحيح فوري|[3,10] :count اختبارات بتصحيح فوري|[11,*] :count اختباراً بتصحيح فوري', $quizCount, ['count' => $quizCount])
            : null,
        (int) $course->access_days === 0
            ? __('وصول مدى الحياة')
            : trans_choice('{1} وصول ليوم واحد|{2} وصول ليومين|[3,10] وصول لـ :count أيام|[11,*] وصول لـ :count يوماً', (int) $course->access_days, ['count' => (int) $course->access_days]),
        $course->certificate_enabled ? __('شهادة إتمام بكود تحقّق') : null,
        module_enabled('video') ? __('فيديو بجودة تتكيّف مع اتصالك') : null,
    ]));

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

<main id="main" class="max-w-[1180px] mx-auto px-4 sm:px-6 py-8 sm:py-10 pb-20">

    <nav class="text-sm text-muted mb-6" aria-label="{{ __('مسار التنقّل') }}">
        <a href="{{ url('/') }}" class="tap-link hover:text-content transition-colors">{{ __('الرئيسية') }}</a>
        <span class="mx-2 opacity-50" aria-hidden="true">/</span>
        <a href="{{ url('/courses') }}" class="tap-link hover:text-content transition-colors">{{ __('الكورسات') }}</a>
        <span class="mx-2 opacity-50" aria-hidden="true">/</span>
        <span class="text-content">{{ $course->title }}</span>
    </nav>

    <div class="grid gap-8 lg:gap-10 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] lg:items-start">

        <div class="min-w-0">
            @if($course->level || $course->category)
                <p class="flex flex-wrap gap-2 mb-4">
                    @if($course->level)
                        <span class="px-3 py-1 rounded-full bg-primary-subtle text-primary text-sm font-bold">{{ $course->level->name }}</span>
                    @endif
                    @if($course->category)
                        <span class="px-3 py-1 rounded-full bg-accent-subtle text-accent-text text-sm font-bold">{{ $course->category->name }}</span>
                    @endif
                </p>
            @endif

            <h1 class="text-3xl sm:text-4xl font-bold leading-tight mb-4">{{ $course->title }}</h1>

            @if($course->excerpt)
                <p class="text-lg text-muted leading-loose mb-6 text-pretty">{{ $course->excerpt }}</p>
            @endif

            <div class="flex flex-wrap gap-x-6 gap-y-2 py-5 border-y border-line mb-8">
                @if($totalItems > 0)
                    <p><span class="text-subtle">{{ __('العناصر') }} </span><strong class="font-mono tabular">{{ $totalItems }}</strong></p>
                @endif
                @if((int) $course->duration_minutes >= 60)
                    <p><span class="text-subtle">{{ __('المدة') }} </span><strong>{{ trans_choice('{1} ساعة|{2} ساعتان|[3,10] :count ساعات|[11,*] :count ساعة', $hours, ['count' => $hours]) }}</strong></p>
                @endif
                @if($quizCount > 0)
                    <p><span class="text-subtle">{{ __('الاختبارات') }} </span><strong class="font-mono tabular">{{ $quizCount }}</strong></p>
                @endif
                <p><span class="text-subtle">{{ __('اللغة') }} </span><strong>{{ config('locales.supported.'.$course->language.'.native') ?? $course->language }}</strong></p>
                @if($course->instructor)
                    <p><span class="text-subtle">{{ __('المدرّس') }} </span><strong>{{ $course->instructor->name() }}</strong></p>
                @endif
            </div>

            @if($course->cover_path || $course->promo_video)
                <div class="relative rounded-lg overflow-hidden border border-line shadow-md aspect-video bg-surface-sunken mb-9">
                    @if($course->cover_path)
                        <img src="{{ $course->cover_path }}" alt="" class="size-full object-cover">
                    @endif

                    @if($previewItem !== null)
                        <a href="{{ url('/learn/'.$course->slug.'/'.$previewItem['item']->getKey()) }}"
                           class="group absolute inset-0 grid place-items-center">
                            <span class="size-[74px] rounded-full bg-accent text-accent-on grid place-items-center text-2xl shadow-lg
                                         transition-transform group-hover:scale-105" aria-hidden="true">▶</span>
                            <span class="sr-only">{{ __('شاهد الدرس المجاني') }}</span>
                        </a>
                    @endif
                </div>
            @endif

            @if(filled($course->outcomes))
                <h2 class="text-xl sm:text-2xl font-bold mb-4">{{ __('ماذا ستتعلّم') }}</h2>
                <ul class="grid gap-2.5 sm:grid-cols-2 surface-card p-5 mb-9">
                    @foreach($course->outcomes as $outcome)
                        <li class="flex items-start gap-2.5">
                            <span class="text-success shrink-0 mt-0.5" aria-hidden="true">✓</span>
                            <span>{{ is_array($outcome) ? ($outcome[app()->getLocale()] ?? reset($outcome)) : $outcome }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <h2 class="text-xl sm:text-2xl font-bold mb-4">{{ __('المنهج') }}</h2>

            @if($sections === [])
                <div class="surface-card p-6">
                    <x-ui.empty :title="__('المنهج قيد الإعداد')">{{ __('سيظهر هنا فور إضافة أول درس.') }}</x-ui.empty>
                </div>
            @else
                <div class="border border-line rounded-lg overflow-hidden bg-surface mb-9">
                    @foreach($sections as $section)
                        @php
                            $minutes = collect($section['items'])->sum(function (array $row): int {
                                $itemable = $row['item']->itemable;

                                return match (true) {
                                    $itemable instanceof Lesson => (int) round((int) $itemable->duration_seconds / 60),
                                    $itemable instanceof Quiz => (int) $itemable->time_limit_minutes,
                                    default => 0,
                                };
                            });
                        @endphp

                        <details class="group border-t border-line first:border-t-0" @if($loop->first) open @endif>
                            <summary class="flex items-center justify-between gap-3 px-5 py-4 bg-surface-sunken cursor-pointer
                                            hover:bg-bg transition-colors">
                                <span class="font-bold min-w-0 truncate">{{ $section['title'] }}</span>
                                <span class="text-sm text-muted shrink-0 font-mono tabular">
                                    {{ $section['total'] }}@if($minutes > 0) · {{ $minutes }} {{ __('د') }}@endif
                                </span>
                            </summary>

                            @foreach($section['items'] as $row)
                                <div class="flex items-center gap-3.5 px-5 py-3.5 border-t border-line">
                                    <span class="text-primary shrink-0" aria-hidden="true">{{ $row['item']->icon() }}</span>

                                    <span class="flex-1 min-w-0 truncate {{ $row['locked'] ? 'text-subtle' : '' }}">{{ $row['title'] }}</span>

                                    @php $itemable = $row['item']->itemable; @endphp
                                    @if($itemable instanceof Lesson && (int) $itemable->duration_seconds > 0)
                                        <span class="text-sm text-subtle shrink-0 font-mono tabular">{{ $itemable->durationLabel() }}</span>
                                    @elseif($itemable instanceof Quiz && (int) $itemable->time_limit_minutes > 0)
                                        <span class="text-sm text-subtle shrink-0 font-mono tabular">{{ $itemable->time_limit_minutes }} {{ __('د') }}</span>
                                    @endif

                                    @if($row['completed'])
                                        <span class="text-xs font-bold text-success shrink-0">{{ __('مكتمل') }}</span>
                                    @elseif($row['preview'] && $enrollment === null)
                                        <span class="text-xs font-bold text-accent-text shrink-0">{{ __('مجاني') }}</span>
                                    @elseif($row['locked'])
                                        <span class="text-xs text-subtle shrink-0">{{ $row['lock_reason'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </details>
                    @endforeach
                </div>
            @endif

            @if(filled($course->requirements))
                <h2 class="text-xl sm:text-2xl font-bold mb-4">{{ __('المتطلبات') }}</h2>
                <ul class="surface-card p-5 mb-9 grid gap-2 list-disc list-inside text-muted">
                    @foreach($course->requirements as $requirement)
                        <li>{{ is_array($requirement) ? ($requirement[app()->getLocale()] ?? reset($requirement)) : $requirement }}</li>
                    @endforeach
                </ul>
            @endif

            @if($course->description)
                <h2 class="text-xl sm:text-2xl font-bold mb-4">{{ __('عن الكورس') }}</h2>
                <div class="surface-card p-5 mb-9 leading-loose whitespace-pre-line text-muted">{{ $course->description }}</div>
            @endif

            <x-ui.card :title="__('آراء الطلاب')" class="mb-4">
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
                              class="mt-5 pt-5 border-t border-line flex flex-col gap-3"
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
                <x-ui.card :title="__('اسأل المدرّس')">
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

                    <p class="mt-4 pt-4 border-t border-line">
                        <a href="{{ url('/discussions?course='.$course->id) }}" class="tap-link text-sm text-primary font-semibold">
                            {{ __('كل أسئلة هذا الكورس') }}
                        </a>
                    </p>
                </x-ui.card>
            @endif
        </div>

        {{-- بطاقة الاشتراك --}}
        <aside class="min-w-0 lg:sticky lg:top-24">
            <div class="surface-card shadow-lg overflow-hidden">
                <div class="p-6 grid gap-2.5">
                    <div class="flex items-baseline gap-3 flex-wrap">
                        <p class="text-4xl font-bold font-mono tabular">
                            @if($course->isFree())
                                <span class="text-success">{{ __('مجاني') }}</span>
                            @else
                                {{ $course->price()->format() }}
                            @endif
                        </p>

                        @if($hasDiscount)
                            <s class="text-lg text-subtle font-mono tabular">
                                {{ App\Core\Support\Money::fromMinor($compare, $course->currency ?? tenant('currency') ?? 'EGP')->format() }}
                            </s>
                        @endif
                    </div>

                    @if($course->ends_at !== null && $course->ends_at->isFuture())
                        <p class="text-sm font-semibold text-accent-text">
                            {{ __('التسجيل يُغلق :when', ['when' => $course->ends_at->diffForHumans()]) }}
                        </p>
                    @endif

                    @error('enroll')<x-ui.alert tone="danger">{{ $message }}</x-ui.alert>@enderror

                    @if($enrollment !== null)
                        <x-ui.progress :value="$enrollment->progress_percent" :label="__('تقدّمك')" />
                        <p class="text-2xs text-subtle font-mono tabular">{{ $enrollment->progress_percent }}%</p>

                        <a href="{{ url('/learn/'.$course->slug) }}"
                           class="block text-center px-5 py-4 rounded-md bg-primary text-primary-on font-bold text-lg hover:bg-primary-hover transition-colors">
                            {{ $enrollment->progress_percent > 0 ? __('أكمل من حيث توقّفت') : __('ابدأ التعلّم') }}
                        </a>

                        @if($enrollment->daysLeft() !== null)
                            <p class="text-2xs text-subtle text-center">
                                {{ trans_choice('{0} انتهت مدة وصولك|{1} يتبقّى يوم واحد|{2} يتبقّى يومان|[3,10] يتبقّى :count أيام|[11,*] يتبقّى :count يوماً', $enrollment->daysLeft(), ['count' => $enrollment->daysLeft()]) }}
                            </p>
                        @endif
                    @elseif($canEnrol && $course->isFree())
                        <form method="POST" action="{{ url('/courses/'.$course->slug.'/enroll') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full px-5 py-4 rounded-md bg-primary text-primary-on font-bold text-lg hover:bg-primary-hover transition-colors">
                                {{ __('سجّل مجاناً') }}
                            </button>
                        </form>
                    @elseif($canEnrol)
                        <form method="POST" action="{{ url('/cart/add') }}">
                            @csrf
                            <input type="hidden" name="course_id" value="{{ $course->id }}">
                            <button type="submit"
                                    class="w-full px-5 py-4 rounded-md bg-primary text-primary-on font-bold text-lg hover:bg-primary-hover transition-colors">
                                {{ __('اشترك في الكورس') }}
                            </button>
                        </form>
                    @else
                        <x-ui.alert tone="warning">{{ __('التسجيل في هذا الكورس مغلق الآن.') }}</x-ui.alert>
                    @endif

                    @if($previewItem !== null && $enrollment === null)
                        <a href="{{ url('/learn/'.$course->slug.'/'.$previewItem['item']->getKey()) }}"
                           class="block text-center px-5 py-4 rounded-md border border-line-strong font-semibold
                                  hover:border-primary hover:text-primary transition-colors">
                            {{ __('شاهد درساً مجاناً') }}
                        </a>
                    @endif
                </div>

                @if($includes !== [])
                    <div class="px-6 py-5 border-t border-line bg-bg">
                        <p class="font-bold mb-3">{{ __('الاشتراك يشمل') }}</p>
                        <ul class="grid gap-2.5 text-muted">
                            @foreach($includes as $line)
                                <li class="flex gap-2.5">
                                    <span class="text-success shrink-0" aria-hidden="true">✓</span>
                                    <span>{{ $line }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($payments->isNotEmpty())
                    <p class="px-6 py-4 border-t border-line text-sm text-subtle">{{ $payments->implode(' · ') }}</p>
                @endif
            </div>
        </aside>
    </div>
</main>

<x-site.footer />
</x-layouts.app>
