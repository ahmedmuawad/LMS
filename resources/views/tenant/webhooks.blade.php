<x-layouts.admin :title="__('الـ Webhooks')" current="webhooks">
<div class="max-w-[980px]">

    <x-ui.page-header :title="__('الـ Webhooks')"
                      :subtitle="__('كلّما جرى شيءٌ في منصّتك، نُخبر نظامك الآخر به فوراً.')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif

    @unless(setting('integrations.webhooks_enabled', false))
        <x-ui.alert tone="warning" :title="__('الـ Webhooks مُطفأة')" class="mb-5">
            <p class="text-sm">
                {{ __('يمكنك إضافة الوجهات الآن، لكن لن يُرسَل شيءٌ حتى تُفعّلها من الإعدادات ← التكاملات.') }}
            </p>
        </x-ui.alert>
    @endunless

    {{--
        السرّ يُعرض مرة واحدة — كالمفتاح تماماً. وقولُ ذلك قبل أن
        يغادر الصفحة أرخص من طلبه بعد أن يبحث عنه.
    --}}
    @if(session('webhook_secret'))
        <x-ui.alert tone="warning" :title="__('انسخ السرّ الآن')" class="mb-5">
            <p class="text-sm mb-3">
                {{ __('يتحقّق به نظامك أنّ الطلب منّا لا من غيرنا. وهذه المرة الوحيدة التي يُعرض فيها.') }}
            </p>

            <div x-data="{ copied: false }" class="flex flex-wrap items-center gap-2">
                <code class="min-w-0 flex-1 text-xs font-mono break-all bg-surface-sunken rounded-md px-3 py-2"
                      x-ref="sec" dir="ltr">{{ session('webhook_secret') }}</code>
                <x-ui.button type="button" size="sm" variant="secondary"
                             x-on:click="navigator.clipboard.writeText($refs.sec.textContent.trim()); copied = true; setTimeout(() => copied = false, 2000)">
                    <span x-text="copied ? '{{ __('نُسخ') }}' : '{{ __('انسخ') }}'"></span>
                </x-ui.button>
            </div>
        </x-ui.alert>
    @endif

    <x-ui.card :title="__('وجهة جديدة')" class="mb-6">
        <form method="POST" action="{{ route('admin.webhooks.store') }}" class="grid gap-4"
              x-data="{ all: false }">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field :label="__('الاسم')" for="wh-name" required class="mb-0"
                            :hint="__('لتعرف أيّ نظامٍ هذا حين تريد إيقافه.')">
                    <x-ui.input id="wh-name" name="name" required maxlength="100"
                                :placeholder="__('مثال: نظام المحاسبة')" />
                </x-ui.field>

                <x-ui.field :label="__('الرابط')" for="wh-url" required class="mb-0"
                            :hint="__('لا بدّ أن يبدأ بـ https — الحمولة تحمل أسماء طلبتك ومبالغ دفعهم.')">
                    <x-ui.input id="wh-url" name="url" type="url" required dir="ltr"
                                placeholder="https://example.com/hooks/usos" />
                </x-ui.field>
            </div>

            <fieldset>
                <legend class="text-sm font-semibold mb-2">{{ __('الأحداث') }}</legend>

                <label class="flex items-center gap-2.5 min-h-11 px-3 mb-3 rounded-lg border border-line-strong text-sm cursor-pointer">
                    <input type="checkbox" name="events[]" value="*" x-model="all"
                           class="accent-[var(--sem-primary)]">
                    <span>{{ __('كل الأحداث — بما يُضاف مستقبلاً') }}</span>
                </label>

                <div class="grid gap-4" x-show="! all" x-collapse>
                    @foreach($events as $group => $groupEvents)
                        <div>
                            <p class="text-2xs font-semibold text-subtle mb-1.5">{{ __($group) }}</p>
                            <div class="grid gap-1.5 sm:grid-cols-2">
                                @foreach($groupEvents as $event)
                                    <label class="flex items-center gap-2.5 min-h-10 px-3 rounded-lg border border-line text-xs cursor-pointer">
                                        <input type="checkbox" name="events[]" value="{{ $event->key }}"
                                               class="accent-[var(--sem-primary)]">
                                        <span class="min-w-0">{{ __($event->label) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <div><x-ui.button type="submit">{{ __('أضف الوجهة') }}</x-ui.button></div>
        </form>
    </x-ui.card>

    @if($endpoints->isEmpty())
        <x-ui.card class="mb-6">
            <x-ui.empty :title="__('لا وجهات بعد')">
                {{ __('أضف وجهةً ليصلها كل ما يجري في منصّتك.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-2 mb-6">
            @foreach($endpoints as $endpoint)
                <div class="surface-card p-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold flex flex-wrap items-center gap-2">
                            {{ $endpoint->name }}

                            @if($endpoint->disabled_at)
                                <x-ui.badge tone="danger">{{ __('موقوفة') }}</x-ui.badge>
                            @elseif($endpoint->last_status === null)
                                <x-ui.badge tone="neutral">{{ __('لم تُجرَّب') }}</x-ui.badge>
                            @elseif($endpoint->last_status < 300)
                                <x-ui.badge tone="success">{{ __('تعمل') }}</x-ui.badge>
                            @else
                                <x-ui.badge tone="warning">{{ $endpoint->last_status }}</x-ui.badge>
                            @endif

                            @if($endpoint->source === 'api')
                                <x-ui.badge tone="info">Zapier</x-ui.badge>
                            @endif
                        </p>

                        <p class="text-2xs text-subtle font-mono break-all mt-0.5" dir="ltr">{{ $endpoint->url }}</p>

                        <p class="text-2xs text-muted mt-1">
                            @if(in_array('*', (array) $endpoint->events, true))
                                {{ __('كل الأحداث') }}
                            @else
                                {{ __(':n حدثاً', ['n' => count((array) $endpoint->events)]) }}
                            @endif
                            · {{ __(':n تسليماً', ['n' => $endpoint->deliveries_count]) }}
                            @if($endpoint->last_delivered_at)
                                · {{ __('آخر تسليم :when', ['when' => $endpoint->last_delivered_at->diffForHumans()]) }}
                            @endif
                        </p>

                        @if($endpoint->disabled_at)
                            {{--
                                سببُ الإيقاف يُقال، لا يُترك للتخمين: رابطٌ
                                مات عند المشترك كان يُعيد كل حدثٍ محاولاته
                                إلى الأبد فيمتلئ الطابور بما لا يصل.
                            --}}
                            <p class="text-2xs text-danger mt-1">
                                {{ __('أوقفناها بعد :n إخفاقاً متتالياً — أصلح الرابط ثم أعِد تشغيلها.', ['n' => $endpoint->consecutive_failures]) }}
                            </p>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('admin.webhooks.test', $endpoint->id) }}">
                            @csrf
                            <x-ui.button type="submit" size="sm" variant="secondary">{{ __('جرّب') }}</x-ui.button>
                        </form>

                        @if($endpoint->disabled_at)
                            <form method="POST" action="{{ route('admin.webhooks.resume', $endpoint->id) }}">
                                @csrf
                                <x-ui.button type="submit" size="sm">{{ __('أعِد التشغيل') }}</x-ui.button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('admin.webhooks.destroy', $endpoint->id) }}"
                              onsubmit="return confirm('{{ __('حذف هذه الوجهة؟ سيتوقّف ما يعتمد عليها فوراً.') }}')">
                            @csrf @method('DELETE')
                            <x-ui.button type="submit" size="sm" variant="danger">{{ __('حذف') }}</x-ui.button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{--
        سجلّ التسليم في الشاشة نفسها.

        أوّل سؤالٍ في كل تكاملٍ لا يعمل: «هل أرسلتم؟» — وشاشةٌ بلا
        سجلّ تترك السؤال بلا جواب.
    --}}
    <x-ui.card :title="__('آخر التسليمات')" class="mb-6">
        @if($deliveries->isEmpty())
            <x-ui.empty :title="__('لا تسليمات بعد')">
                {{ __('اضغط «جرّب» على إحدى الوجهات لترى أوّل تسليم.') }}
            </x-ui.empty>
        @else
            <div class="overflow-x-auto -mx-4 sm:mx-0">
                <table class="w-full text-xs min-w-[560px]">
                    <thead>
                        <tr class="text-start text-2xs text-subtle">
                            <th class="px-4 py-2 text-start font-semibold">{{ __('الحدث') }}</th>
                            <th class="px-4 py-2 text-start font-semibold">{{ __('الوجهة') }}</th>
                            <th class="px-4 py-2 text-start font-semibold">{{ __('النتيجة') }}</th>
                            <th class="px-4 py-2 text-start font-semibold">{{ __('متى') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deliveries as $delivery)
                            <tr>
                                <td class="px-4 py-2.5 border-b border-line font-mono text-2xs" dir="ltr">{{ $delivery->event }}</td>
                                <td class="px-4 py-2.5 border-b border-line">{{ $delivery->endpoint?->name ?? '—' }}</td>
                                <td class="px-4 py-2.5 border-b border-line">
                                    @if($delivery->succeeded())
                                        <span class="text-success">{{ $delivery->status_code }}</span>
                                    @elseif($delivery->status_code)
                                        <span class="text-danger">{{ $delivery->status_code }}</span>
                                    @else
                                        <span class="text-danger" title="{{ $delivery->error }}">{{ __('لم يُجب') }}</span>
                                    @endif
                                    @if($delivery->attempt > 1)
                                        <span class="text-2xs text-subtle">· {{ __('محاولة :n', ['n' => $delivery->attempt]) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 border-b border-line text-subtle whitespace-nowrap">
                                    {{ $delivery->created_at?->diffForHumans() }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card :title="__('كيف يتحقّق نظامك أنّ الطلب منّا')">
        <p class="text-sm text-muted leading-relaxed mb-3">
            {{ __('نرسل مع كل طلبٍ ثلاث ترويسات. احسب التوقيع عندك وقارنه — ولا تثق بطلبٍ مضى على طابعه أكثر من خمس دقائق.') }}
        </p>

        <pre class="text-2xs font-mono bg-surface-sunken rounded-md p-3 overflow-x-auto" dir="ltr">X-Usos-Event: lms.enrolled
X-Usos-Timestamp: 1772000000
X-Usos-Signature: sha256=…

// PHP
$expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
hash_equals($expected, $signature);</pre>

        <p class="text-sm font-semibold mt-5 mb-2">{{ __('ربط Zapier') }}</p>
        <p class="text-sm text-muted leading-relaxed mb-3">
            {{ __('أنشئ مفتاحاً من شاشة الواجهة البرمجية بصلاحية «الاشتراك في الأحداث»، ثم استعمل هذه النقاط:') }}
        </p>

        <ul class="grid gap-1 text-2xs font-mono" dir="ltr">
            <li>GET&nbsp;&nbsp;&nbsp; /api/v1/webhooks/events</li>
            <li>POST&nbsp;&nbsp; /api/v1/webhooks</li>
            <li>DELETE /api/v1/webhooks/&#123;id&#125;</li>
        </ul>
    </x-ui.card>

</div>
</x-layouts.admin>
