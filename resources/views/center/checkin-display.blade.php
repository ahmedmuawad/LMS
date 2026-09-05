<x-layouts.app :title="__('كود الحضور')">
{{--
    شاشةٌ تُفتح على تلفاز القاعة ولا تُلمس.

    فلا قائمةَ ولا شريطَ جانبي: ما يُرى من آخر الصف هو الكود وحده،
    وكلُّ ما عداه يصغّره.
--}}
<div class="min-h-screen bg-bg flex flex-col items-center justify-center p-6"
     x-data="checkinDisplay('{{ route('admin.center.attendance.token', $session->id) }}')"
     x-init="start()">

    <p class="text-sm text-muted mb-1">{{ $session->group?->subject?->name ?? __('الحصة') }}</p>
    <h1 class="text-xl font-bold mb-6 text-center">
        {{ $session->group?->name }}
        @if($session->room) · {{ $session->room->name }} @endif
    </h1>

    <div class="surface-card p-5 rounded-2xl">
        {{-- الرمز يُرسم في الخادم: مكتبةُ رسمٍ في كل قاعة ثمنٌ بلا مقابل --}}
        <div class="w-[280px] h-[280px] sm:w-[320px] sm:h-[320px] [&>svg]:w-full [&>svg]:h-full"
             x-html="svg"
             :class="stale ? 'opacity-40' : ''"></div>
    </div>

    {{--
        الكود نصّاً تحت الرمز.

        كاميرا هاتفٍ قديم قد لا تقرأه، وطالبٌ واقفٌ بلا حيلة يجعل
        المدرّس يترك النظام كلَّه ويعود إلى الورق.
    --}}
    <p class="mt-5 text-3xl font-mono tracking-[0.3em] font-bold" dir="ltr" x-text="code"></p>

    <div class="mt-4 w-[280px] sm:w-[320px] h-1.5 rounded-full bg-surface-sunken overflow-hidden">
        <div class="h-full bg-primary transition-[width] duration-1000 ease-linear"
             :style="`width: ${(seconds / total) * 100}%`"></div>
    </div>

    <p class="mt-3 text-xs text-muted text-center max-w-sm leading-relaxed">
        {{ __('يتبدّل الكود كل :n ثانية — امسحه من هاتفك وأنت في القاعة.', ['n' => App\Modules\Center\Actions\SelfCheckIn::SLOT]) }}
    </p>

    <p class="mt-2 text-2xs text-danger" x-show="offline" x-cloak>
        {{ __('انقطع الاتصال — الكود المعروض قديم.') }}
    </p>
</div>

@push('scripts')
<script>
    /**
     * الشاشة تطلب الكود التالي قبل أن ينتهي الحالي.
     *
     * والطلبُ بعد الانتهاء يترك فراغاً يقف فيه الطابور عند الباب؛
     * وثانيةٌ سابقةٌ تكفي لأن يصل الجديد قبل أن يموت القديم.
     */
    function checkinDisplay(url) {
        return {
            svg: '', code: '····', seconds: 0, total: 20,
            stale: false, offline: false,

            start() {
                this.fetchToken();

                setInterval(() => {
                    this.seconds = Math.max(0, this.seconds - 1);

                    // يُطلب الجديد قبل انتهاء القديم بثانية
                    if (this.seconds <= 1) this.fetchToken();

                    this.stale = this.seconds === 0;
                }, 1000);
            },

            async fetchToken() {
                try {
                    const response = await fetch(url, { headers: { 'Accept': 'application/json' } });

                    if (! response.ok) throw new Error(response.status);

                    const data = await response.json();

                    this.svg = data.svg;
                    this.code = data.code;
                    this.seconds = data.expires_in;
                    this.total = Math.max(data.expires_in, this.total);
                    this.offline = false;
                    this.stale = false;
                } catch (e) {
                    /*
                     | انقطاعٌ لا يُفرغ الشاشة.
                     |
                     | واي فاي السنتر يسقط ثانيتين؛ وشاشةٌ تُمسح عند
                     | كل سقوط تجعل المدرّس يتركها. فيبقى القديم
                     | معروضاً باهتاً مع قولٍ صريح بأنه قديم.
                     */
                    this.offline = true;
                }
            },
        };
    }
</script>
@endpush
</x-layouts.app>
