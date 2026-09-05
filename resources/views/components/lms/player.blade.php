@props(['item', 'lesson', 'course', 'enrollment'])
@php
    $progress = App\Modules\Lms\Models\LessonProgress::where('enrollment_id', $enrollment->getKey())
        ->where('item_id', $item->getKey())->first();
    $resume = setting('lms.resume', true) ? (int) ($progress?->last_position_seconds ?? 0) : 0;
    /*
     | الوسم: الاسم والبريد معاً.
     |
     | البريد وحده صغيرٌ يُقرأ بصعوبة في تسجيلٍ للشاشة، والاسم وحده
     | يتكرّر بين الطلبة. واجتماعهما يجعل الوسم دالّاً ومقروءاً معاً.
     */
    $watermark = setting('security.video_watermark', true) && auth()->check()
        ? trim(auth()->user()->name.' · '.auth()->user()->email)
        : null;
    // الرابط يُوقَّع هنا لكل طلب ولا يُخزَّن؛ الرابط الثابت يُوزَّع خلال دقائق
    $src = app(App\Modules\Lms\VideoUrl::class)->for($lesson, auth()->id());

    /*
     | الدرس المتاح بلا اتصال يُشغَّل من عنوانٍ ثابت.
     |
     | الرابط الموقَّع ينتهي، والنسخة المحفوظة تحته تُرفَض غداً —
     | والطالب لا يفهم لماذا «اختفى» ما حفظه. فالعنوان الثابت هو
     | المصدر، وحراستُه في الخادم عند كل طلب.
     */
    $offline = (bool) ($lesson->is_offline ?? false)
        && $lesson->video_provider === 'file'
        && (tenant()?->allows('offline_download') ?? false);

    if ($offline) {
        $src = url('/lessons/'.$lesson->getKey().'/offline');
    }

    /*
     | نقاط التفاعل — تُحمَّل مرة واحدة إلى المتصفّح.
     |
     | وحالة «أُجيب» تُحسب هنا لا في المتصفّح: من أجاب أمس لا يُسأل
     | اليوم مرة أخرى عند كل إعادة مشاهدة.
     */
    $me = auth()->user();

    /*
     | الفصول والنصّ — يُقرآن مرّةً هنا لا في كل موضع.
     */
    $chapters = $lesson->type === 'video'
        ? App\Modules\Lms\Models\LessonChapter::where('lesson_id', $lesson->getKey())
            ->orderBy('at_second')->get()
        : collect();

    $transcript = is_array($lesson->transcript)
        ? trim((string) ($lesson->transcript[app()->getLocale()] ?? $lesson->transcript['ar'] ?? ''))
        : trim((string) ($lesson->transcript ?? ''));
    $moments = $me === null ? collect() : App\Modules\Lms\Models\VideoMoment::where('lesson_id', $lesson->getKey())
        ->with(['question', 'responses' => fn ($q) => $q->where('user_id', $me->getKey())])
        ->orderBy('at_second')
        ->get()
        ->map(fn (App\Modules\Lms\Models\VideoMoment $m): array => [
            'id' => $m->getKey(),
            'at' => (int) $m->at_second,
            'kind' => $m->kind,
            'required' => (bool) $m->is_required,
            'answered' => $m->responses->isNotEmpty(),
            'html' => $m->kind === 'question'
                ? (string) ($m->question?->body ?? '')
                : e((string) ($m->body ?? $m->url ?? '')),

            /*
             | الخيارات تُرسَل مع النقطة.
             |
             | كانت اللوحة تعرض نصّ السؤال وحده وتحته خانةُ كتابة —
             | فسؤال «اختيار واحد» يظهر بلا خياراته، ولا سبيل للطالب
             | أن يعرف أن عليه كتابة حرف الخيار. جرّبناه فرأيناه.
             */
            'type' => (string) ($m->question?->type ?? 'short_text'),
            'options' => $m->kind === 'question' && $m->question?->type === 'true_false'
                ? ['1' => __('صح'), '0' => __('خطأ')]
                : (array) ($m->question?->options ?? []),
        ]);
@endphp

<div class="grid gap-4">
    @if($lesson->type === 'video')
        <div class="surface-card overflow-hidden relative"
             @if($moments->isNotEmpty())
                 data-moments-root
                 data-moments="{{ $moments->toJson() }}"
                 data-moment-token="{{ csrf_token() }}"
                 data-moment-url="{{ url('/moments/__ID__/respond') }}"
             @endif
             x-data="lessonPlayer({
                 url: @js(url('/learn/'.$course->slug.'/'.$item->getKey().'/heartbeat')),
                 token: @js(csrf_token()),
                 resume: {{ $resume }},
             })">
            {{-- الأسود للفيديو وحده؛ نصّ فوق أسود بلون خافت لا يُقرأ --}}
            <div class="aspect-video relative {{ $src ? 'bg-[#000]' : 'bg-surface-sunken' }}">
                @if($lesson->video_provider === 'youtube' && $lesson->video_id)
                    <iframe class="absolute inset-0 size-full" title="{{ $lesson->title }}"
                            src="https://www.youtube-nocookie.com/embed/{{ $lesson->video_id }}"
                            allow="accelerometer; autoplay; encrypted-media; picture-in-picture"
                            allowfullscreen loading="lazy"></iframe>
                @elseif($src)
                    {{--
                        `nodownload` يُخفي الزرّ ولا يمنع «حفظ باسم»
                        من زرّ الفأرة الأيمن — وذاك يُمنع في
                        content-guard.js. وكلاهما يمنع العابر لا
                        المصمِّم: الحماية الحقيقية في الرابط الموقَّع
                        والعلامة المائية.
                    --}}
                    <video x-ref="video" class="absolute inset-0 size-full" controls preload="metadata"
                           disablepictureinpicture
                           controlsList="{{ $lesson->is_downloadable ? '' : 'nodownload noplaybackrate noremoteplayback' }}"
                           @loadedmetadata="restore()" @timeupdate.throttle.10s="report()" @pause="report()"
                           @ended="report()">
                        <source src="{{ $src }}">
                        {{ __('متصفّحك لا يدعم تشغيل الفيديو.') }}
                    </video>

                    {{--
                        لوحة النقطة فوق الفيديو لا تحته.
                        الطالب ينظر إلى الفيديو، ولوحةٌ أسفل الشاشة
                        تظهر بلا أن يراها فيظنّ الفيديو تعطّل.
                    --}}
                    <div data-moment-panel hidden
                         class="absolute inset-0 z-10 grid place-items-center p-4 sm:p-8
                                bg-[color-mix(in_oklab,var(--sem-surface)_92%,transparent)]">
                        <div class="w-full max-w-[520px] surface-card p-5 grid gap-4">
                            <div data-moment-body
                                 class="text-sm leading-relaxed [&_p]:mb-2 [&_img]:max-w-full"></div>

                            <form data-moment-form class="grid gap-3">
                                {{-- الخيارات حيث توجد، وخانة الكتابة لما لا خيارات له --}}
                                <div data-moment-choices class="grid gap-1" hidden></div>

                                <div data-moment-text>
                                    <label class="sr-only" for="moment-answer">{{ __('إجابتك') }}</label>
                                    <x-ui.input id="moment-answer" data-moment-answer
                                                :placeholder="__('إجابتك…')" autocomplete="off" />
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <x-ui.button type="submit" size="sm">{{ __('تحقّق') }}</x-ui.button>
                                    <x-ui.button type="button" size="sm" variant="ghost"
                                                 data-moment-skip>{{ __('تخطَّ') }}</x-ui.button>
                                </div>
                            </form>

                            <p data-moment-result hidden class="text-sm"></p>
                        </div>
                    </div>
                @else
                    <p class="absolute inset-0 grid place-items-center text-muted text-sm px-4 text-center">{{ __('لم يُرفع فيديو هذا الدرس بعد.') }}</p>
                @endif

                @if($offline)
                    {{--
                        الزرّ داخل إطار الفيديو لا تحته: من يبحث عن
                        الحفظ ينظر إلى ما يريد حفظه.
                    --}}
                    <div class="absolute top-3 end-3 z-10" x-data="offlineLesson({ url: @js($src) })">
                        <button type="button" @click="save()" ::disabled="busy || done"
                                class="text-2xs font-semibold rounded px-2.5 py-1.5 transition-colors
                                       bg-[rgba(0,0,0,.55)] text-[#fff] hover:bg-[rgba(0,0,0,.75)]
                                       disabled:opacity-70">
                            <span x-text="label"></span>
                        </button>
                    </div>
                @endif

                @if($watermark)
                    {{--
                        علامة مائية باسم الطالب — تتنقّل ولا تثبت.

                        الوسم الثابت في زاوية يُقصّ في ثانية، فيصير
                        زينةً لا رادعاً. والمتنقّل يجعل القصّ يأكل
                        الصورة نفسها.

                        وهي لا تمنع التسجيل — تجعله يقود إلى صاحبه.
                        وذلك أقوى رادعٍ عملي: الطالب يعرف أن اسمه على
                        الشاشة.
                    --}}
                    <span data-watermark
                          class="absolute text-[11px] font-mono px-2 py-1 rounded pointer-events-none select-none
                                 transition-[inset] duration-1000"
                          style="color: rgba(255,255,255,.5); background: rgba(0,0,0,.3); bottom: 12px; inset-inline-end: 12px;"
                          aria-hidden="true">{{ $watermark }}</span>
                @endif
            </div>
        </div>
    @elseif($lesson->type === 'scorm')
        @php
            $package = App\Modules\Lms\Models\ScormPackage::where('lesson_id', $lesson->getKey())->first();
            $state = $package !== null && $me !== null ? $package->stateFor($me) : null;
        @endphp

        @if($package === null)
            <x-ui.card>
                <x-ui.empty :title="__('لم تُرفع الحزمة بعد')">
                    {{ __('هذا الدرس من نوع SCORM ولم يرفع مدرّسك حزمته.') }}
                </x-ui.empty>
            </x-ui.card>
        @else
            {{--
                الجسر على النافذة لا على الإطار: الحزمة تصعد في
                `window.parent` حتى تجد `API`، فوضعه هنا يجعلها تجده
                مهما عمُق تداخل إطاراتها.
            --}}
            <div class="surface-card overflow-hidden"
                 data-scorm-root
                 data-scorm-version="{{ $package->version }}"
                 data-scorm-url="{{ url('/scorm/'.$package->getKey().'/state') }}"
                 data-scorm-token="{{ csrf_token() }}"
                 data-scorm-state="{{ json_encode($state?->cmi ?? [], JSON_UNESCAPED_UNICODE) }}">

                <iframe src="{{ $package->entryUrl() }}"
                        class="w-full h-[75vh] min-h-[480px] bg-white"
                        title="{{ $package->title ?: $lesson->title }}"
                        allow="autoplay; fullscreen"></iframe>
            </div>

            @if($state !== null && $state->lesson_status !== 'not attempted')
                <p class="text-2xs text-subtle font-mono tabular">
                    {{ $state->statusLabel() }}
                    @if($state->score_raw !== null) · {{ rtrim(rtrim(number_format($state->score_raw, 1), '0'), '.') }}% @endif
                    · {{ $state->timeLabel() }}
                </p>
            @endif
        @endif

    @elseif($lesson->type === 'h5p')
        @php
            $h5p = App\Modules\Lms\Models\H5pPackage::where('lesson_id', $lesson->getKey())->first();
        @endphp

        @if($h5p === null)
            <x-ui.card>
                <x-ui.empty :title="__('لم تُرفع الحزمة بعد')">
                    {{ __('هذا الدرس محتوى تفاعلي ولم يرفع مدرّسك حزمته.') }}
                </x-ui.empty>
            </x-ui.card>
        @else
            {{--
                المشغّل يقرأ h5p.json من مجلّد الحزمة، ومكتباته من
                public/vendor/h5p — فلا خادم H5P ولا مكتبة مركزية
                تُرقّى فتكسر محتوى مشترِكٍ آخر.
            --}}
            <div class="surface-card overflow-hidden p-2 sm:p-4 bg-white"
                 data-h5p-folder="{{ $h5p->folderUrl() }}"
                 data-h5p-base="{{ url('/vendor/h5p') }}"
                 data-h5p-url="{{ url('/h5p/'.$h5p->getKey().'/xapi') }}"
                 data-h5p-token="{{ csrf_token() }}">

                <div data-h5p-frame></div>

                <p data-h5p-error hidden class="text-sm text-danger p-4">
                    {{ __('تعذّر تشغيل المحتوى التفاعلي. حدّث الصفحة، وإن تكرّر فأبلغ مدرّسك.') }}
                </p>
            </div>
        @endif

    @elseif($lesson->type === 'pdf' && $lesson->video_id)
        <div class="surface-card overflow-hidden">
            <iframe src="{{ $lesson->video_id }}" class="w-full h-[70vh]" title="{{ $lesson->title }}" loading="lazy"></iframe>
        </div>
    @endif

    @if($chapters->isNotEmpty() || $transcript !== '')
        <div x-data="{ tab: '{{ $chapters->isNotEmpty() ? 'chapters' : 'text' }}', q: '' }">
            <x-ui.card :padding="false">
                {{--
                    الفصول والنصّ في بطاقةٍ واحدة بلسانين.
                    كلاهما يجيب سؤالاً واحداً: «أين ذلك الموضع؟» —
                    وبطاقتان تجعلان الطالب يقرأ إحداهما.
                --}}
                <div class="flex gap-1 p-2 border-b border-line">
                    @if($chapters->isNotEmpty())
                        <button type="button" @click="tab = 'chapters'"
                                class="px-3 py-2 rounded-md text-sm font-semibold transition-colors"
                                :class="tab === 'chapters' ? 'bg-primary text-primary-on' : 'hover:bg-surface-sunken'">
                            {{ __('الفصول') }}
                            <span class="font-mono text-2xs opacity-70">{{ $chapters->count() }}</span>
                        </button>
                    @endif

                    @if($transcript !== '')
                        <button type="button" @click="tab = 'text'"
                                class="px-3 py-2 rounded-md text-sm font-semibold transition-colors"
                                :class="tab === 'text' ? 'bg-primary text-primary-on' : 'hover:bg-surface-sunken'">
                            {{ __('النصّ المكتوب') }}
                        </button>
                    @endif
                </div>

                @if($chapters->isNotEmpty())
                    <div x-show="tab === 'chapters'" class="p-2 max-h-[320px] overflow-y-auto">
                        @foreach($chapters as $chapter)
                            <button type="button" data-seek="{{ $chapter->at_second }}"
                                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-md text-start
                                           hover:bg-surface-sunken transition-colors min-h-11">
                                <span class="font-mono text-xs tabular text-primary shrink-0">{{ $chapter->timeLabel() }}</span>
                                <span class="min-w-0 flex-1 text-sm truncate">{{ $chapter->title }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                @if($transcript !== '')
                    <div x-show="tab === 'text'" x-cloak class="p-4">
                        <label class="sr-only" for="transcript-search">{{ __('ابحث في النصّ') }}</label>
                        <x-ui.input id="transcript-search" x-model="q" class="mb-3"
                                    :placeholder="__('ابحث في النصّ…')" autocomplete="off" />

                        <div class="max-h-[360px] overflow-y-auto grid gap-1.5 leading-relaxed text-sm">
                            @foreach(preg_split('/

|
|
/', $transcript) as $line)
                                @php
                                    $line = trim($line);
                                    $seek = preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{1,2})\s+(.*)$/u', $line, $m)
                                        ? ((int) ($m[1] ?? 0)) * 3600 + ((int) $m[2]) * 60 + (int) $m[3]
                                        : null;
                                @endphp

                                @continue($line === '')

                                @if($seek !== null)
                                    {{-- السطر الموقّت يقفز بالفيديو إليه --}}
                                    <button type="button" data-seek="{{ $seek }}"
                                            x-show="q === '' || @js($m[4]).includes(q)"
                                            class="flex items-start gap-3 text-start rounded-md px-2 py-1.5
                                                   hover:bg-surface-sunken transition-colors">
                                        <span class="font-mono text-2xs tabular text-primary shrink-0 pt-0.5">
                                            {{ preg_replace('/\s.*$/u', '', $line) }}
                                        </span>
                                        <span class="min-w-0">{{ $m[4] }}</span>
                                    </button>
                                @else
                                    <p class="px-2 text-muted" x-show="q === '' || @js($line).includes(q)">{{ $line }}</p>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-ui.card>
        </div>
    @endif

    @if($lesson->content)
        <x-ui.card :title="$lesson->title">
            <div class="leading-relaxed whitespace-pre-line text-muted">{{ $lesson->content }}</div>
        </x-ui.card>
    @endif

    @php
        // المرفقات المحروسة — لا العمود القديم بروابطه العامة
        $files = App\Modules\Lms\Models\LessonAttachment::where('lesson_id', $lesson->getKey())
            ->with('media')->orderBy('position')->orderBy('id')->get();
    @endphp

    @if($files->isNotEmpty())
        <x-ui.card :title="__('المرفقات')">
            <ul class="grid gap-2">
                @foreach($files as $file)
                    <li>
                        {{--
                            الرابط يقود إلى عارضنا لا إلى الملفّ.
                            رابط الملفّ المباشر يُنسَخ فيقرؤه من لم يدفع.
                        --}}
                        <a href="{{ url('/attachments/'.$file->getKey()) }}"
                           class="tap-link flex items-center gap-2.5 text-sm hover:text-primary transition-colors">
                            <span class="shrink-0" aria-hidden="true">◫</span>
                            <span class="min-w-0 truncate">{{ $file->name() }}</span>
                            <span class="text-2xs text-subtle font-mono tabular shrink-0 ms-auto">
                                {{ $file->kindLabel() }} · {{ $file->sizeLabel() }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</div>

{{--
    المساعد الدراسي.

    تحت الدرس لا في زاوية عائمة: الطالب يسأل عمّا قرأه للتوّ، ولوحةٌ
    تطفو فوق المحتوى تحجب ما يسأل عنه.
--}}
@if(tenant()?->allows('ai_assistant') && filled($lesson->content) && $me !== null)
    {{--
        الحالة على عنصرٍ عاديّ لا على مكوّن Blade.

        مُصرّف المكوّنات يلتقط سمات `<x-…>` نصّاً قبل تصريف
        التوجيهات، فيبقى `@js(...)` داخلها كما كُتب ولا يُنفَّذ —
        فلا تعمل اللوحة، بلا خطأ في أي مكان. جرّبناه فرأيناه.
    --}}
    <div class="mt-4"
         x-data="lessonAssistant({
             url: @js(url('/ai/ask')),
             token: @js(csrf_token()),
             lesson: {{ $lesson->getKey() }},
             thread: @js(App\Modules\Ai\Actions\AnswerStudent::threadFor($me, $lesson)
                 ->map(fn ($m) => ['role' => $m->role, 'body' => $m->body])
                 ->values()),
         })">

        <x-ui.card :title="__('اسأل عن الدرس')">
            <p class="text-2xs text-subtle mb-3">
                {{ __('يجيب من مادة هذا الدرس وحدها. وما ليس فيها يحيلك إلى مدرّسك — ولا يحلّ لك واجباً.') }}
            </p>

            <div class="grid gap-3 mb-3" x-show="thread.length" x-cloak>
                <template x-for="(message, index) in thread" :key="index">
                    <div class="text-sm leading-relaxed rounded-md px-3 py-2.5"
                         :class="message.role === 'student'
                             ? 'bg-surface-sunken'
                             : 'bg-info-subtle text-info'">
                        <span class="block text-2xs font-semibold mb-1 opacity-70"
                              x-text="message.role === 'student' ? @js(__('سؤالك')) : @js(__('المساعد'))"></span>
                        <span class="whitespace-pre-line" x-text="message.body"></span>
                    </div>
                </template>
            </div>

            <form class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]" @submit.prevent="send()">
                <label class="sr-only" for="assistant-question">{{ __('سؤالك') }}</label>
                <x-ui.input id="assistant-question" x-model="question" autocomplete="off"
                            ::disabled="busy" :placeholder="__('اسأل عمّا لم تفهمه…')" />
                <x-ui.button type="submit" ::disabled="busy || question.trim().length < 2">
                    <span x-text="busy ? @js(__('يفكّر…')) : @js(__('اسأل'))"></span>
                </x-ui.button>
            </form>

            <p x-show="error" x-cloak x-text="error" class="text-sm text-danger mt-2"></p>
        </x-ui.card>
    </div>

@endif

@once
    @push('scripts')
    <script>
        /* القفز إلى فصلٍ أو سطرٍ موقّت.
           والتفويض على المستند: الألسنة تُبنى وتُخفى، ومستمعٌ لكل
           زرٍّ عند التحميل يفوته ما ظهر بعده. */
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-seek]');

            if (!button) return;

            const video = document.querySelector('video');

            if (!video) return;

            video.currentTime = Number(button.dataset.seek) || 0;
            video.play().catch(() => {});
            video.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });

        /* الوسم يتنقّل بين تسع مواضع كل عشرين ثانية.
           الثابت في زاوية يُقصّ، والمتنقّل يأكل قصُّه الصورة. */
        document.querySelectorAll('[data-watermark]').forEach((mark) => {
            const spots = [
                { top: '12px', insetInlineStart: '12px' },
                { top: '12px', insetInlineEnd: '12px' },
                { top: '12px', insetInlineStart: '42%' },
                { top: '45%', insetInlineStart: '12px' },
                { top: '45%', insetInlineEnd: '12px' },
                { bottom: '12px', insetInlineStart: '12px' },
                { bottom: '12px', insetInlineEnd: '12px' },
                { bottom: '12px', insetInlineStart: '42%' },
                { top: '45%', insetInlineStart: '42%' },
            ];

            let last = -1;

            const move = () => {
                let next = last;

                // لا يبقى في موضعه مرّتين: القفزة هي المقصودة
                while (next === last) next = Math.floor(Math.random() * spots.length);

                last = next;

                mark.style.top = mark.style.bottom = '';
                mark.style.insetInlineStart = mark.style.insetInlineEnd = '';

                Object.assign(mark.style, spots[next]);
            };

            move();
            setInterval(move, 20000);
        });

        // نُبلّغ الخادم بموضع المشاهدة على فترات لا مع كل إطار:
        // الاستئناف يستحقّ الحفظ، لا أن يُغرق الخادم بطلبات.
        document.addEventListener('alpine:init', () => {
            /* المساعد الدراسي: سؤالٌ واحد في الطريق، فالضغط مرّتين
               لا يُنفق طلبين على مزوّدٍ يُحاسَب بالطلب. */
            /* الحفظ للمشاهدة بلا اتصال: يُجلَب الملفّ مرّةً ويوضع في
               مخزن المتصفّح، فيقرؤه عامل الخدمة بعدها بلا شبكة. */
            Alpine.data('offlineLesson', (config) => ({
                busy: false,
                done: false,
                label: @js(__('احفظ للمشاهدة بلا إنترنت')),
                async init() {
                    if (!('caches' in window)) { this.done = true; this.label = @js(__('متصفّحك لا يدعم الحفظ')); return; }
                    try {
                        const cache = await caches.open('lessons');
                        if (await cache.match(config.url)) {
                            this.done = true;
                            this.label = @js(__('محفوظ في جهازك'));
                        }
                    } catch (e) { /* مخزنٌ مغلق في تصفّحٍ خاص — لا شيء يُعطَّل */ }
                },
                async save() {
                    if (this.busy || this.done) return;
                    this.busy = true;
                    this.label = @js(__('يُحفظ…'));
                    try {
                        const cache = await caches.open('lessons');
                        await cache.add(config.url);
                        this.done = true;
                        this.label = @js(__('محفوظ في جهازك'));
                    } catch (e) {
                        this.label = @js(__('تعذّر الحفظ — جرّب لاحقاً'));
                    }
                    this.busy = false;
                },
            }));

            Alpine.data('lessonAssistant', (config) => ({
                question: '',
                busy: false,
                error: '',
                thread: config.thread || [],
                async send() {
                    const asked = this.question.trim();
                    if (this.busy || asked.length < 2) return;

                    this.busy = true;
                    this.error = '';
                    this.thread.push({ role: 'student', body: asked });
                    this.question = '';

                    try {
                        const response = await fetch(config.url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': config.token,
                            },
                            body: JSON.stringify({ lesson_id: config.lesson, question: asked }),
                        });

                        const data = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            this.error = data.message || 'تعذّر إرسال السؤال. أعد المحاولة.';
                        } else {
                            this.thread.push({ role: 'assistant', body: data.answer });
                        }
                    } catch (e) {
                        this.error = 'تعذّر الاتصال. تحقّق من الإنترنت.';
                    }

                    this.busy = false;
                },
            }));

            Alpine.data('lessonPlayer', (config) => ({
                watched: 0,
                last: 0,
                restore() {
                    if (config.resume > 0 && this.$refs.video) {
                        this.$refs.video.currentTime = config.resume;
                    }
                },
                report() {
                    const video = this.$refs.video;
                    if (!video) return;
                    const position = Math.floor(video.currentTime);
                    if (Math.abs(position - this.last) < 5) return;
                    this.watched += Math.max(0, position - this.last);
                    this.last = position;
                    fetch(config.url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.token },
                        body: JSON.stringify({ position, watched: this.watched }),
                        keepalive: true,
                    }).catch(() => {});
                },
            }));
        });
    </script>
    @endpush
@endonce
