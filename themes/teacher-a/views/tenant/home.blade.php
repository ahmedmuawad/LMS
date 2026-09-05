@php
    use App\Modules\Lms\Models\Course;
    use App\Modules\Lms\Models\CourseReview;
    use App\Modules\Lms\Models\Enrollment;
    use App\Modules\Lms\Models\Quiz;
    use Illuminate\Support\Facades\Schema;

    /*
     | «لوح الشرح» — صفحة مدرّس، لا صفحة منصّة.
     |
     | ترتيب الأقسام يتبع ترتيب أسئلة الزائر: مَن أنت (اللوح الداكن)،
     | ثم بم أثق (الأرقام)، ثم ماذا تبيع (الكورسات والمجموعات)، ثم
     | أسمعك قبل أن أدفع (المعاينة)، ثم مَن يطمئن وليّ أمري (التقرير).
     |
     | وكل قسمٍ لا يجد ما يملؤه يختفي: قسمٌ فارغ أسوأ من غيابه.
     */
    $wa = preg_replace('/\D+/', '', (string) $whatsapp);
    $lms = module_enabled('lms');
    $center = module_enabled('center');

    // ---- الأرقام: ما نعرفه فعلاً، ولا رقم مُختلَق ----
    $statStudents = setting('homepage.stat_students')
        ?: ($lms && Schema::hasTable('enrollments') ? Enrollment::distinct('user_id')->count('user_id') : 0);

    $statYears = setting('homepage.stat_years');   // لا مصدر له غير المدرّس نفسه

    $statCourses = $lms && Schema::hasTable('courses')
        ? Course::where('status', 'published')->where('visibility', 'public')->count() : 0;

    $statGroups = $center && Schema::hasTable('center_groups')
        ? App\Modules\Center\Models\Group::whereIn('status', ['open', 'running'])->count() : 0;

    $stats = array_values(array_filter([
        $statStudents > 0 ? ['value' => $statStudents, 'label' => __('طالب وطالبة')] : null,
        filled($statYears) ? ['value' => $statYears, 'label' => __('سنة خبرة في التدريس')] : null,
        $statCourses > 0 ? ['value' => $statCourses, 'label' => __('كورس مسجّل')] : null,
        $statGroups > 0 ? ['value' => $statGroups, 'label' => __('مجموعة مفتوحة')] : null,
    ]));

    // شريط واحد بعمود واحد ليس شريط أرقام — إمّا اثنان فأكثر أو لا شيء
    $stats = count($stats) >= 2 ? $stats : [];

    // ---- بطاقة الإنجاز العائمة فوق الصورة: من إعداد المدرّس وحده ----
    $highlight = filled(setting('homepage.highlight_value')) ? [
        'value' => setting('homepage.highlight_value'),
        'label' => setting('homepage.highlight_label') ?: __('نتيجة آخر دفعة'),
        'note' => setting('homepage.highlight_note'),
    ] : null;

    // ---- المعاينة المجانية: أول كورس فيه درسٌ مفتوح للجميع ----
    $previewCourse = null;
    $previewItems = collect();

    if ($lms && Schema::hasTable('course_items')) {
        $previewCourse = Course::where('status', 'published')->where('visibility', 'public')
            ->whereHas('items', fn ($q) => $q->where('is_preview', true))
            ->with(['items' => fn ($q) => $q->with('itemable')])
            ->latest('published_at')
            ->first();

        $previewItems = $previewCourse?->items->take(6) ?? collect();
    }

    // ---- آراء الطلبة: تقييمات معتمَدة فعلاً، لا شهادات مكتوبة في لوحة ----
    $quotes = $lms && Schema::hasTable('course_reviews')
        ? CourseReview::approved()->where('rating', '>=', 4)->whereNotNull('body')
            ->with('user')->latest()->limit(3)->get()
        : collect();

    // ---- طرق الدفع: ما هو مهيّأ عند هذا المشترك، لا قائمة ثابتة ----
    $gatewayLabels = collect(config('payments.gateways', []))
        ->keyBy('key')
        ->map(fn (array $g): string => trim(explode('—', (string) ($g['label'] ?? ''))[0]));

    $payments = module_enabled('payments')
        ? collect(app(App\Modules\Commerce\Gateways\GatewayManager::class)->available())
            ->map(fn ($g): string => (string) ($gatewayLabels[$g->key()] ?? $g->key()))
            ->filter()->values()
        : collect();

    // عدّاد الاختبارات لكل كورس في استعلام واحد — لا استعلام داخل الحلقة
    if ($courses->isNotEmpty()) {
        $courses->loadCount(['items as quizzes_count' => fn ($q) => $q->where('itemable_type', Quiz::class)]);
    }

    $hoursOf = fn (int $minutes): int => (int) round($minutes / 60);
@endphp

<x-layouts.app :title="site_name()">
<x-site.header />

<main id="main">

    {{--
        اللوح: خلفية داكنة بشبكة كراسة. الدور `spot` يبقى داكناً في
        الوضعين، فلا ينقلب العنوان الأول من الموقع أبيضَ على أبيض.
    --}}
    <section class="relative overflow-hidden bg-spot text-on-spot">
        <div class="absolute inset-0 opacity-55 pointer-events-none" aria-hidden="true"
             style="background-image:linear-gradient(var(--color-spot-line) 1px,transparent 1px),linear-gradient(90deg,var(--color-spot-line) 1px,transparent 1px);background-size:44px 44px"></div>

        <div class="relative max-w-[1180px] mx-auto px-4 sm:px-6 py-14 sm:py-20
                    grid gap-10 lg:gap-12 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,.85fr)] lg:items-center">

            <div class="min-w-0">
                @if($statGroups > 0)
                    <p class="inline-flex items-center gap-2 px-3.5 py-1.5 mb-6 rounded-full bg-spot-raised text-on-spot-accent text-xs font-semibold">
                        <span class="size-1.5 rounded-full bg-live motion-safe:animate-pulse" aria-hidden="true"></span>
                        {{ trans_choice('{1} مجموعة واحدة مفتوحة الآن|{2} مجموعتان مفتوحتان الآن|[3,10] :count مجموعات مفتوحة الآن|[11,*] :count مجموعة مفتوحة الآن', $statGroups, ['count' => $statGroups]) }}
                    </p>
                @endif

                <h1 class="text-3xl sm:text-4xl lg:text-[3.25rem] font-bold leading-[1.25] mb-5 text-balance">{{ $headline }}</h1>

                @if($subheadline)
                    <p class="text-lg text-on-spot-muted leading-loose mb-8 max-w-[52ch] text-pretty">{{ $subheadline }}</p>
                @endif

                <div class="flex flex-wrap gap-3 mb-7">
                    @if($previewCourse !== null)
                        <a href="{{ url('/courses/'.$previewCourse->slug) }}"
                           class="px-6 py-3.5 rounded-md bg-accent text-accent-on font-bold text-lg shadow-md hover:brightness-110 transition-[filter]">
                            {{ __('شاهد درساً مجاناً') }}
                        </a>
                        <a href="{{ $ctaUrl }}"
                           class="px-6 py-3.5 rounded-md border border-on-spot-muted text-on-spot font-semibold text-lg hover:bg-spot-raised transition-colors">
                            {{ $ctaLabel }}
                        </a>
                    @else
                        <a href="{{ $ctaUrl }}"
                           class="px-6 py-3.5 rounded-md bg-accent text-accent-on font-bold text-lg shadow-md hover:brightness-110 transition-[filter]">
                            {{ $ctaLabel }}
                        </a>
                        @auth
                            <a href="{{ url('/me') }}"
                               class="px-6 py-3.5 rounded-md border border-on-spot-muted text-on-spot font-semibold text-lg hover:bg-spot-raised transition-colors">
                                {{ __('لوحتي') }}
                            </a>
                        @else
                            <a href="{{ url('/login') }}"
                               class="px-6 py-3.5 rounded-md border border-on-spot-muted text-on-spot font-semibold text-lg hover:bg-spot-raised transition-colors">
                                {{ __('دخول الطلاب') }}
                            </a>
                        @endauth
                    @endif
                </div>

                @if($points !== [])
                    <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-on-spot-muted">
                        @foreach($points as $point)
                            <li>{{ $point }}</li>
                        @endforeach
                    </ul>
                @endif

                @if($wa || $phone)
                    <div class="flex flex-wrap gap-x-5 gap-y-2 mt-6 text-sm">
                        @if($wa)
                            <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener"
                               class="tap-link text-on-spot-accent font-semibold hover:underline">{{ __('واتساب') }}</a>
                        @endif
                        @if($phone)
                            <a href="tel:{{ $phone }}" class="tap-link text-on-spot-muted font-mono tabular hover:text-on-spot">{{ $phone }}</a>
                        @endif
                    </div>
                @endif
            </div>

            @if($heroImage)
                <div class="relative min-w-0 order-first lg:order-last max-w-sm lg:max-w-none mx-auto w-full">
                    <div class="rounded-xl overflow-hidden border border-spot-line shadow-lg aspect-[4/5]">
                        <img src="{{ $heroImage }}" alt="" class="size-full object-cover">
                    </div>

                    @if($highlight !== null)
                        <div class="absolute bottom-4 start-3 sm:bottom-7 sm:-start-5 flex items-center gap-3.5
                                    bg-surface text-content rounded-lg shadow-lg px-4 py-3.5">
                            <span class="size-11 rounded-md bg-accent-subtle text-accent-text grid place-items-center
                                         font-bold text-lg font-mono tabular shrink-0" aria-hidden="true">{{ $highlight['value'] }}</span>
                            <span class="leading-snug min-w-0">
                                <span class="block font-bold">{{ $highlight['label'] }}</span>
                                @if($highlight['note'])
                                    <span class="block text-sm text-muted">{{ $highlight['note'] }}</span>
                                @endif
                            </span>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </section>

    @if($stats !== [])
        <section class="bg-surface border-b border-line">
            <div class="max-w-[1180px] mx-auto px-4 sm:px-6 grid grid-cols-2 lg:grid-cols-4">
                @foreach($stats as $stat)
                    <div class="py-8 px-3 border-s border-line">
                        <p class="text-3xl sm:text-4xl font-bold text-primary leading-tight font-mono tabular">{{ $stat['value'] }}</p>
                        <p class="text-muted">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if($courses->isNotEmpty())
        <section id="courses" class="max-w-[1180px] mx-auto px-4 sm:px-6 py-16 sm:py-20">
            <div class="flex flex-wrap items-end justify-between gap-4 mb-9">
                <div>
                    <p class="text-xs font-bold text-accent-text mb-2">{{ __('الكورسات المسجّلة') }}</p>
                    <h2 class="text-2xl sm:text-3xl font-bold">{{ __('راجع في أي وقت، من أي جهاز') }}</h2>
                </div>
                <a href="{{ url('/courses') }}" class="tap-link font-semibold text-primary hover:underline">{{ __('كل الكورسات') }} ←</a>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($courses as $course)
                    <article class="surface-card overflow-hidden flex flex-col shadow-sm lift">
                        <a href="{{ url('/courses/'.$course->slug) }}" class="block relative aspect-[16/10] bg-surface-sunken">
                            @if($course->cover_path)
                                <img src="{{ $course->cover_path }}" alt="" class="size-full object-cover" loading="lazy">
                            @else
                                <span class="absolute inset-0 grid place-items-center text-3xl text-subtle" aria-hidden="true">▤</span>
                            @endif

                            @if($course->level)
                                <span class="absolute top-3 start-3 px-3 py-1 rounded-full bg-spot text-on-spot text-xs font-semibold">{{ $course->level->name }}</span>
                            @elseif($course->isFree())
                                <span class="absolute top-3 start-3 px-3 py-1 rounded-full bg-accent text-accent-on text-xs font-semibold">{{ __('مجاني') }}</span>
                            @endif
                        </a>

                        <div class="p-5 flex flex-col gap-3 flex-1 min-w-0">
                            <h3 class="font-bold text-lg leading-snug min-w-0">
                                <a href="{{ url('/courses/'.$course->slug) }}" class="tap-link hover:text-primary transition-colors">{{ $course->title }}</a>
                            </h3>

                            @if($course->excerpt)
                                <p class="text-sm text-muted leading-relaxed line-clamp-2">{{ $course->excerpt }}</p>
                            @endif

                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-subtle border-t border-line pt-3 mt-auto">
                                @if((int) $course->lessons_count > 0)
                                    <span>{{ trans_choice('{1} درس واحد|{2} درسان|[3,10] :count دروس|[11,*] :count درساً', (int) $course->lessons_count, ['count' => (int) $course->lessons_count]) }}</span>
                                @endif
                                @if((int) $course->duration_minutes >= 60)
                                    <span>{{ trans_choice('{1} ساعة|{2} ساعتان|[3,10] :count ساعات|[11,*] :count ساعة', $hoursOf((int) $course->duration_minutes), ['count' => $hoursOf((int) $course->duration_minutes)]) }}</span>
                                @endif
                                @if((int) ($course->quizzes_count ?? 0) > 0)
                                    <span>{{ trans_choice('{1} اختبار|{2} اختباران|[3,10] :count اختبارات|[11,*] :count اختباراً', (int) $course->quizzes_count, ['count' => (int) $course->quizzes_count]) }}</span>
                                @endif
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xl font-bold font-mono tabular">
                                    @if($course->isFree())
                                        <span class="text-success">{{ __('مجاني') }}</span>
                                    @else
                                        {{ $course->price()->format() }}
                                    @endif
                                </p>
                                <a href="{{ url('/courses/'.$course->slug) }}"
                                   class="px-4 py-2.5 rounded-md bg-primary text-primary-on font-semibold hover:bg-primary-hover transition-colors">{{ __('التفاصيل') }}</a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if($groups->isNotEmpty())
        <section id="groups" class="bg-surface border-y border-line">
            <div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-16 sm:py-20">
                <p class="text-xs font-bold text-accent-text mb-2">{{ __('المجموعات') }}</p>
                <h2 class="text-2xl sm:text-3xl font-bold mb-3">{{ __('حضورياً أو أونلاين — نفس المنهج ونفس الامتحانات') }}</h2>
                <p class="text-lg text-muted mb-9 max-w-[62ch] text-pretty">
                    {{ __('تختار المكان المناسب لك. الحضور يُسجَّل، والأقساط والتقرير الشهري يصلان وليّ الأمر.') }}
                </p>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($groups as $group)
                        @php $left = $group->seatsLeft(); @endphp
                        <div class="border border-line rounded-lg overflow-hidden bg-bg">
                            <div class="flex items-center justify-between gap-3 px-5 py-4 bg-surface-sunken border-b border-line">
                                <p class="font-bold text-lg min-w-0 truncate">{{ $group->name }}</p>
                                <span class="px-3 py-1 rounded-full bg-primary-subtle text-primary text-xs font-bold shrink-0">
                                    {{ $group->isOnline() ? __('أونلاين') : __('حضوري') }}
                                </span>
                            </div>

                            <div class="p-5 grid gap-4">
                                <div class="grid gap-1">
                                    <p class="text-sm text-subtle">{{ __('المكان') }}</p>
                                    <p class="font-semibold">{{ $group->venueLabel() }}</p>
                                    @if($group->subject || $group->grade)
                                        <p class="text-sm text-muted">
                                            {{ $group->subject?->name }}@if($group->subject && $group->grade) · @endif{{ $group->grade?->name }}
                                        </p>
                                    @endif
                                </div>

                                @if($group->schedules->isNotEmpty())
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($group->schedules as $schedule)
                                            <span class="px-3.5 py-2 rounded-md border border-line-strong text-sm font-semibold font-mono tabular">
                                                {{ $schedule->weekdayLabel() }} {{ $schedule->timeLabel() }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="flex items-center justify-between gap-3 border-t border-line pt-4">
                                    <div class="min-w-0">
                                        @if($group->price_minor > 0)
                                            <p class="text-lg font-bold font-mono tabular">
                                                {{ $group->price()->format() }}<span class="text-sm font-medium text-muted">
                                                @switch($group->price_type)
                                                    @case('monthly') {{ __('/ شهرياً') }} @break
                                                    @case('per_session') {{ __('/ الحصة') }} @break
                                                    @default {{ __('/ الترم') }}
                                                @endswitch
                                                </span>
                                            </p>
                                        @endif
                                        <p class="text-sm text-subtle">
                                            @if($left > 0)
                                                {{ __('باقي :n من :c', ['n' => $left, 'c' => (int) $group->capacity]) }}
                                            @else
                                                {{ __('مكتملة') }}
                                            @endif
                                        </p>
                                    </div>

                                    @if($left > 0)
                                        <a href="{{ $wa ? 'https://wa.me/'.$wa : url('/register') }}"
                                           @if($wa) target="_blank" rel="noopener" @endif
                                           class="px-5 py-2.5 rounded-md bg-primary text-primary-on font-semibold shrink-0 hover:bg-primary-hover transition-colors">{{ __('احجز مكانك') }}</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if($previewCourse !== null && $previewItems->isNotEmpty())
        <section id="preview" class="bg-spot text-on-spot">
            <div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-16 sm:py-20
                        grid gap-10 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] lg:items-start">

                <div class="min-w-0">
                    <p class="text-xs font-bold text-on-spot-accent mb-2">{{ __('معاينة مجانية') }}</p>
                    <h2 class="text-2xl sm:text-3xl font-bold mb-6">{{ __('اسمع الشرح قبل أن تدفع') }}</h2>

                    <a href="{{ url('/courses/'.$previewCourse->slug) }}"
                       class="group relative block rounded-lg overflow-hidden border border-spot-line shadow-lg aspect-video bg-spot-raised">
                        @if($previewCourse->cover_path)
                            <img src="{{ $previewCourse->cover_path }}" alt="" class="size-full object-cover" loading="lazy">
                        @endif
                        <span class="absolute inset-0 grid place-items-center">
                            <span class="size-[74px] rounded-full bg-accent text-accent-on grid place-items-center text-2xl shadow-lg
                                         transition-transform group-hover:scale-105" aria-hidden="true">▶</span>
                        </span>
                        <span class="sr-only">{{ __('افتح :course', ['course' => $previewCourse->title]) }}</span>
                    </a>

                    <p class="mt-5 text-on-spot-muted leading-loose">
                        {{ __('الفيديو محميّ بعلامة مائية باسم الطالب، ويعمل على الهاتف والحاسوب بجودة تتكيّف مع سرعة الاتصال.') }}
                    </p>
                </div>

                <div class="min-w-0 bg-spot-raised border border-spot-line rounded-lg p-2">
                    <p class="px-4 py-3.5 font-bold text-lg">{{ $previewCourse->title }}</p>

                    @foreach($previewItems as $index => $item)
                        <div class="flex items-center gap-3.5 px-4 py-3.5 border-t border-spot-line">
                            <span class="size-8 rounded-md bg-spot text-on-spot-muted grid place-items-center text-sm font-bold font-mono tabular shrink-0"
                                  aria-hidden="true">{{ $index + 1 }}</span>

                            <span class="min-w-0 flex-1">
                                <span class="block leading-snug truncate">{{ $item->title() }}</span>
                                <span class="block text-xs text-on-spot-muted">{{ $item->label() }}</span>
                            </span>

                            <span class="text-xs font-bold shrink-0 {{ $item->is_preview ? 'text-on-spot-accent' : 'text-on-spot-muted' }}">
                                {{ $item->is_preview ? __('مجاني') : __('للمشتركين') }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if($about)
        <section id="about" class="max-w-[1180px] mx-auto px-4 sm:px-6 py-16 sm:py-20
                                   grid gap-10 lg:gap-12 lg:grid-cols-[minmax(0,.8fr)_minmax(0,1.2fr)] lg:items-center">
            @if(setting('homepage.about_image') ?: $heroImage)
                <div class="rounded-xl overflow-hidden border border-line shadow-md aspect-square max-w-sm lg:max-w-none mx-auto w-full">
                    <img src="{{ setting('homepage.about_image') ?: $heroImage }}" alt="" class="size-full object-cover" loading="lazy">
                </div>
            @endif

            <div class="min-w-0">
                <p class="text-xs font-bold text-accent-text mb-2">{{ __('عن المدرّس') }}</p>
                <h2 class="text-2xl sm:text-3xl font-bold mb-5">{{ setting('homepage.about_title') ?: __('طريقة واحدة، ونتيجة تتكرّر') }}</h2>
                <div class="text-lg text-muted leading-loose whitespace-pre-line text-pretty">{{ $about }}</div>

                @php
                    $credentials = array_values(array_filter([
                        filled(setting('homepage.credential_1')) ? ['title' => setting('homepage.credential_1'), 'note' => setting('homepage.credential_1_note')] : null,
                        filled(setting('homepage.credential_2')) ? ['title' => setting('homepage.credential_2'), 'note' => setting('homepage.credential_2_note')] : null,
                    ]));
                @endphp

                @if($credentials !== [])
                    <div class="grid gap-4 sm:grid-cols-2 mt-7">
                        @foreach($credentials as $credential)
                            <div class="surface-card p-4">
                                <p class="font-bold mb-1">{{ $credential['title'] }}</p>
                                @if($credential['note'])
                                    <p class="text-sm text-muted">{{ $credential['note'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if($quotes->isNotEmpty())
        <section class="bg-surface border-y border-line">
            <div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-16 sm:py-20">
                <h2 class="text-2xl sm:text-3xl font-bold mb-9">{{ __('الطلبة يقولون') }}</h2>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($quotes as $quote)
                        <figure class="m-0 p-6 border border-line rounded-lg bg-bg flex flex-col gap-4">
                            <p class="text-accent tracking-[3px]" aria-label="{{ __(':n من ٥', ['n' => $quote->rating]) }}">
                                <span aria-hidden="true">{{ str_repeat('★', (int) $quote->rating) }}<span class="text-subtle">{{ str_repeat('★', 5 - (int) $quote->rating) }}</span></span>
                            </p>

                            <blockquote class="m-0 leading-loose text-pretty">{{ $quote->body }}</blockquote>

                            <figcaption class="mt-auto flex items-center gap-3">
                                <span class="size-10 rounded-full bg-primary-subtle text-primary grid place-items-center font-bold shrink-0"
                                      aria-hidden="true">{{ mb_substr((string) ($quote->user?->name ?? '؟'), 0, 1) }}</span>
                                <span class="leading-snug min-w-0">
                                    <span class="block font-semibold truncate">{{ $quote->user?->name ?? __('طالب') }}</span>
                                    <span class="block text-sm text-subtle">{{ $quote->created_at?->translatedFormat('F Y') }}</span>
                                </span>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if(module_enabled('parent-portal'))
        <section id="parent" class="max-w-[1180px] mx-auto px-4 sm:px-6 py-16 sm:py-20
                                    grid gap-10 lg:gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,.85fr)] lg:items-center">
            <div class="min-w-0">
                <p class="text-xs font-bold text-accent-text mb-2">{{ __('لوليّ الأمر') }}</p>
                <h2 class="text-2xl sm:text-3xl font-bold mb-5">{{ __('تقرير شهري واحد يقول لك كل شيء') }}</h2>
                <p class="text-lg text-muted leading-loose mb-6 text-pretty">
                    {{ __('الحضور والدرجات والواجبات وموقف المصروفات في صفحة واحدة — بلا أن تسأل أو تتّصل.') }}
                </p>

                <ul class="grid gap-3 mb-7">
                    @foreach([
                        __('حضور كل حصة بتاريخها ووقتها'),
                        __('درجات الاختبارات وترتيب الطالب في مجموعته'),
                        __('كشف حساب بالأقساط المدفوعة والمتأخّرة'),
                    ] as $line)
                        <li class="flex gap-3 items-start">
                            <span class="text-success font-bold shrink-0" aria-hidden="true">✓</span>
                            <span>{{ $line }}</span>
                        </li>
                    @endforeach
                </ul>

                <div class="flex flex-wrap gap-3">
                    <a href="{{ url('/guardian') }}"
                       class="inline-flex items-center px-6 py-3.5 rounded-md bg-primary text-primary-on font-bold hover:bg-primary-hover transition-colors">
                        {{ __('ادخل بوابة وليّ الأمر') }}
                    </a>
                    @if($wa)
                        <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener"
                           class="inline-flex items-center px-6 py-3.5 rounded-md border border-line-strong font-semibold hover:border-primary hover:text-primary transition-colors">
                            {{ __('تواصل على واتساب') }}
                        </a>
                    @endif
                </div>
            </div>

            {{--
                نموذجٌ للشكل لا لبيانات طالب بعينه: أرقامُ طالبٍ حقيقيّ
                على صفحةٍ عامة تسريبٌ، وأرقامٌ مُختلَقة كذبٌ. فالخانات
                تُعرض فارغةً موسومةً «نموذج».
            --}}
            <div class="min-w-0 surface-card shadow-lg overflow-hidden" aria-hidden="true">
                <div class="px-5 py-4 bg-spot text-on-spot flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-bold text-lg">{{ __('التقرير الشهري') }}</p>
                        <p class="text-sm text-on-spot-muted">{{ __('اسم الطالب · الصف · المجموعة') }}</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full bg-spot-raised text-on-spot-accent text-xs font-bold shrink-0">{{ __('نموذج') }}</span>
                </div>

                <div class="grid grid-cols-2">
                    @foreach([
                        __('الحضور'), __('متوسط الدرجات'), __('الواجبات'), __('المصروفات'),
                    ] as $i => $label)
                        <div class="px-5 py-5 border-line {{ $i < 2 ? 'border-b' : '' }} {{ $i % 2 === 0 ? 'border-e' : '' }}">
                            <p class="text-sm text-subtle mb-1">{{ $label }}</p>
                            <p class="text-2xl font-bold text-subtle font-mono tabular">—</p>
                        </div>
                    @endforeach
                </div>

                <p class="px-5 py-4 border-t border-line bg-surface-sunken text-muted">
                    {{ __('الترتيب في المجموعة، والمقارنة بالشهر الماضي.') }}
                </p>
            </div>
        </section>
    @endif

    @if($services->isNotEmpty())
        <section class="bg-surface border-y border-line">
            <div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-14">
                <h2 class="text-2xl font-bold mb-7">{{ __('الخدمات') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($services as $service)
                        <a href="{{ url('/services/'.$service->slug) }}" class="surface-card p-5 lift group">
                            <p class="font-semibold group-hover:text-primary transition-colors">{{ $service->title }}</p>
                            @if($service->excerpt)
                                <p class="text-sm text-muted mt-1.5 line-clamp-2 leading-relaxed">{{ $service->excerpt }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if($posts->isNotEmpty())
        <section class="max-w-[1180px] mx-auto px-4 sm:px-6 py-14">
            <h2 class="text-2xl font-bold mb-7">{{ __('من المدوّنة') }}</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach($posts as $post)
                    <a href="{{ url('/blog/'.$post->slug) }}" class="surface-card p-5 lift group">
                        <p class="font-semibold line-clamp-2 group-hover:text-primary transition-colors">{{ $post->title }}</p>
                        @if($post->excerpt)
                            <p class="text-sm text-muted mt-1.5 line-clamp-3 leading-relaxed">{{ $post->excerpt }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($payments->isNotEmpty())
        <section class="bg-surface-sunken border-y border-line">
            <div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-10 flex flex-wrap items-center justify-between gap-6">
                <p class="font-semibold">{{ __('ادفع بالطريقة التي تناسبك') }}</p>
                <ul class="flex flex-wrap gap-2.5">
                    @foreach($payments as $payment)
                        <li class="px-4 py-2.5 rounded-md bg-surface border border-line text-sm font-semibold">{{ $payment }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{--
        دعوةٌ أخيرة على لون التمييز: من قرأ إلى هنا مهتمّ، وإعادةُ
        الزرّ إليه أرخص من أن يبحث عنه بالتمرير لأعلى.
    --}}
    <section class="bg-accent text-accent-on">
        <div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-14 flex flex-wrap items-center justify-between gap-7">
            <div class="min-w-0">
                <h2 class="text-2xl sm:text-3xl font-bold mb-2.5">{{ __('جاهز تبدأ بداية صحيحة؟') }}</h2>
                <p class="text-lg leading-relaxed opacity-85">{{ $subheadline ?: __('ابدأ من كورس مسجّل، أو احجز مكانك في مجموعة.') }}</p>
            </div>

            <div class="flex flex-wrap gap-3">
                @if($groups->isNotEmpty())
                    <a href="#groups" class="px-6 py-3.5 rounded-md bg-spot text-on-spot font-bold text-lg hover:bg-spot-raised transition-colors">{{ __('احجز في مجموعة') }}</a>
                @endif
                {{-- الحدّ والخلفية من لون النصّ نفسه: لون التمييز يقلب مع الوضع ونصُّه معه --}}
                <a href="{{ $ctaUrl }}"
                   class="px-6 py-3.5 rounded-md border border-[color-mix(in_oklab,currentColor_45%,transparent)]
                          font-bold text-lg hover:bg-[color-mix(in_oklab,currentColor_8%,transparent)] transition-colors">{{ $ctaLabel }}</a>
            </div>
        </div>
    </section>

</main>

<x-site.footer />
</x-layouts.app>
