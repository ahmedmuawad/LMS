@props(['item', 'lesson', 'course', 'enrollment'])
@php
    $progress = App\Modules\Lms\Models\LessonProgress::where('enrollment_id', $enrollment->getKey())
        ->where('item_id', $item->getKey())->first();
    $resume = setting('lms.resume', true) ? (int) ($progress?->last_position_seconds ?? 0) : 0;
    $watermark = setting('security.video_watermark', true) ? auth()->user()?->email : null;
    // الرابط يُوقَّع هنا لكل طلب ولا يُخزَّن؛ الرابط الثابت يُوزَّع خلال دقائق
    $src = app(App\Modules\Lms\VideoUrl::class)->for($lesson, auth()->id());
@endphp

<div class="grid gap-4">
    @if($lesson->type === 'video')
        <div class="surface-card overflow-hidden relative"
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

    @if(filled($lesson->attachments))
        <x-ui.card :title="__('المرفقات')">
            <ul class="grid gap-2">
                @foreach($lesson->attachments as $attachment)
                    <li>
                        <a href="{{ $attachment['url'] ?? '#' }}" class="tap-link text-sm text-primary hover:underline inline-flex items-center gap-2"
                           @if($lesson->is_downloadable) download @endif>
                            <span aria-hidden="true">◫</span>{{ $attachment['name'] ?? __('مرفق') }}
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
