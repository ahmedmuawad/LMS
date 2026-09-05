@props(['item', 'lesson', 'course', 'enrollment'])
@php
    $progress = App\Modules\Lms\Models\LessonProgress::where('enrollment_id', $enrollment->getKey())
        ->where('item_id', $item->getKey())->first();
    $resume = setting('lms.resume', true) ? (int) ($progress?->last_position_seconds ?? 0) : 0;
    $watermark = setting('security.video_watermark', true) ? auth()->user()?->email : null;
    // الرابط يُوقَّع هنا لكل طلب ولا يُخزَّن؛ الرابط الثابت يُوزَّع خلال دقائق
    $src = app(App\Modules\Lms\VideoUrl::class)->for($lesson, auth()->id());

    /*
     | نقاط التفاعل — تُحمَّل مرة واحدة إلى المتصفّح.
     |
     | وحالة «أُجيب» تُحسب هنا لا في المتصفّح: من أجاب أمس لا يُسأل
     | اليوم مرة أخرى عند كل إعادة مشاهدة.
     */
    $me = auth()->user();
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
                    <video x-ref="video" class="absolute inset-0 size-full" controls preload="metadata"
                           controlsList="{{ $lesson->is_downloadable ? '' : 'nodownload' }}"
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
                                <label class="sr-only" for="moment-answer">{{ __('إجابتك') }}</label>
                                <x-ui.input id="moment-answer" data-moment-answer
                                            :placeholder="__('إجابتك…')" autocomplete="off" />
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

                @if($watermark)
                    {{-- علامة مائية باسم الطالب: لا تمنع التسجيل لكنها تجعله يقود إلى صاحبه --}}
                    <span class="absolute bottom-3 end-3 text-[10px] font-mono px-2 py-1 rounded pointer-events-none select-none"
                          style="color: rgba(255,255,255,.55); background: rgba(0,0,0,.35)" aria-hidden="true">{{ $watermark }}</span>
                @endif
            </div>
        </div>
    @elseif($lesson->type === 'pdf' && $lesson->video_id)
        <div class="surface-card overflow-hidden">
            <iframe src="{{ $lesson->video_id }}" class="w-full h-[70vh]" title="{{ $lesson->title }}" loading="lazy"></iframe>
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

@once
    @push('scripts')
    <script>
        // نُبلّغ الخادم بموضع المشاهدة على فترات لا مع كل إطار:
        // الاستئناف يستحقّ الحفظ، لا أن يُغرق الخادم بطلبات.
        document.addEventListener('alpine:init', () => {
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
