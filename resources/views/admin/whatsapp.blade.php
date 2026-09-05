<x-layouts.admin :title="__('ربط واتساب')" current="whatsapp">
<div class="max-w-[720px]"
     x-data="whatsappLink({
         connectUrl: @js(url('/admin/whatsapp/connect')),
         stateUrl: @js(url('/admin/whatsapp/state')),
         token: @js(csrf_token()),
         state: @js($state),
         number: @js($number),
     })">

    <x-ui.page-header :title="__('ربط واتساب')"
                      :subtitle="__('اربط رقمك الذي يعرفه طلابك، فتخرج رسائل منصّتك منه — التذكيرات وأكواد التحقّق وإشعارات الدفع.')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>@endif
    @error('number')<x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>@enderror

    @unless($ready)
        <x-ui.alert tone="warning" :title="__('بوّابة واتساب غير مضبوطة')">
            {{ __('إدارة المنصّة لم تضبط خادم واتساب بعد. تواصل معهم — ولا شيء تفعله من هنا حتى يُضبط.') }}
        </x-ui.alert>
    @else
        <x-ui.card :title="__('حالة الربط')" class="mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-0">
                    <template x-if="state === 'open'">
                        <div>
                            <x-ui.badge tone="success">{{ __('موصول') }}</x-ui.badge>
                            <p class="text-sm text-muted mt-2">
                                {{ __('الرقم المرتبط:') }}
                                <span class="font-mono" x-text="number || '—'"></span>
                            </p>
                        </div>
                    </template>

                    <template x-if="state !== 'open'">
                        <div>
                            <x-ui.badge tone="neutral" x-text="label()"></x-ui.badge>
                            <p class="text-sm text-muted mt-2">
                                {{ __('افتح واتساب في هاتفك ← الأجهزة المرتبطة ← ربط جهاز، ثم امسح الرمز.') }}
                            </p>
                        </div>
                    </template>
                </div>

                <div class="flex flex-wrap gap-2">
                    <template x-if="state !== 'open'">
                        <x-ui.button type="button" @click="connect()" ::disabled="busy">
                            <span x-text="busy ? @js(__('لحظة…')) : @js(__('أظهر الرمز'))"></span>
                        </x-ui.button>
                    </template>

                    <template x-if="state === 'open'">
                        <form method="POST" action="{{ url('/admin/whatsapp/disconnect') }}"
                              onsubmit="return confirm('{{ __('فصل الرقم يوقف رسائل منصّتك. متابعة؟') }}')">
                            @csrf
                            <x-ui.button variant="ghost" type="submit">{{ __('افصل الرقم') }}</x-ui.button>
                        </form>
                    </template>
                </div>
            </div>

            <p x-show="error" x-cloak x-text="error" class="text-sm text-danger mt-3"></p>

            {{--
                الرمز على خلفيةٍ بيضاء دائماً.
                قارئ واتساب يقرأ الأسود على الأبيض، ورمزٌ معكوس في
                الوضع الداكن لا يُقرأ.
            --}}
            <div x-show="qr" x-cloak class="mt-5 text-center">
                <div class="inline-block bg-white p-3 rounded-lg">
                    <img :src="qr" alt="{{ __('رمز الربط') }}" class="w-[260px] h-[260px]">
                </div>

                <p class="text-2xs text-subtle mt-3">
                    {{ __('الرمز ينتهي بعد دقيقة — اضغط «أظهر الرمز» لواحدٍ جديد.') }}
                </p>

                <template x-if="pairing">
                    <p class="text-sm mt-2">
                        {{ __('أو أدخل هذا الرمز في هاتفك:') }}
                        <span class="font-mono font-bold tracking-widest" x-text="pairing"></span>
                    </p>
                </template>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('جرّب الربط')">
            <p class="text-muted text-sm mb-4">
                {{ __('أرسل رسالةً إلى رقمك أو رقم أحد مساعديك — التجربة أوثق من «تمّ الحفظ».') }}
            </p>

            <form method="POST" action="{{ url('/admin/whatsapp/test') }}"
                  class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                @csrf
                <label class="sr-only" for="wa-number">{{ __('رقم الواتساب') }}</label>
                <x-ui.input id="wa-number" name="number" placeholder="01xxxxxxxxx" required />
                <x-ui.button type="submit">{{ __('أرسل تجربة') }}</x-ui.button>
            </form>
        </x-ui.card>
    @endunless

</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('whatsappLink', (config) => ({
            state: config.state,
            number: config.number,
            qr: null,
            pairing: null,
            busy: false,
            error: '',
            timer: null,

            label() {
                return {
                    unset: @js(__('غير مربوط')),
                    close: @js(__('غير مربوط')),
                    connecting: @js(__('بانتظار المسح')),
                    unknown: @js(__('تعذّر قراءة الحالة')),
                }[this.state] || @js(__('غير مربوط'));
            },

            async connect() {
                if (this.busy) return;

                this.busy = true;
                this.error = '';

                try {
                    const response = await fetch(config.connectUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': config.token,
                        },
                    });

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        this.error = data.message || @js(__('تعذّر إنشاء الربط.'));
                    } else {
                        this.qr = data.qr;
                        this.pairing = data.pairing;
                        this.state = data.state || this.state;

                        /* المتابعة تبدأ مع الرمز وتقف عند الاتصال:
                           استطلاعٌ دائم يُتعب الخادم بلا سبب. */
                        this.watch();
                    }
                } catch (e) {
                    this.error = @js(__('تعذّر الاتصال.'));
                }

                this.busy = false;
            },

            watch() {
                clearInterval(this.timer);

                let tries = 0;

                this.timer = setInterval(async () => {
                    if (++tries > 60) {
                        clearInterval(this.timer);
                        return;
                    }

                    try {
                        const response = await fetch(config.stateUrl, {
                            headers: { 'Accept': 'application/json' },
                        });

                        const data = await response.json();
                        this.state = data.state;
                        this.number = data.number;

                        if (data.state === 'open') {
                            clearInterval(this.timer);
                            this.qr = null;
                            this.pairing = null;
                        }
                    } catch (e) { /* انقطاعٌ عابر — المحاولة التالية تكفي */ }
                }, 3000);
            },
        }));
    });
</script>
@endpush
</x-layouts.admin>
