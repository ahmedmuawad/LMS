<x-layouts.student :title="__('اشتراكاتي')" current="memberships">

    <x-ui.page-header :title="__('اشتراكاتي')"
                      :subtitle="__('اشتراكٌ دوريّ يفتح المحتوى بدل شراء كل كورس على حدة.')" />

    @if(session('status'))
        <x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>
    @endif

    @if($memberships->isNotEmpty())
        <section class="mb-8">
            <h2 class="text-sm font-bold text-subtle mb-3">{{ __('اشتراكاتك') }}</h2>

            <div class="grid gap-3">
                @foreach($memberships as $membership)
                    <div class="surface-card p-4 grid gap-3">
                        <div class="flex flex-wrap items-start gap-x-4 gap-y-2">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-sm">{{ $membership->plan?->name ?? __('عضوية') }}</p>
                                <p class="text-xs text-muted font-mono tabular mt-1">
                                    @if($membership->plan)
                                        {{ $membership->plan->price()->format() }} · {{ $membership->plan->periodLabel() }}
                                    @endif
                                </p>

                                {{--
                                    الملغاة السارية تُقال بوضوح: «سارية حتى» لا
                                    «ملغاة» وحدها — فمن دفع شهره يبقى له إلى آخره،
                                    ورؤية «ملغاة» تجعله يظنّ أنه خسره.
                                --}}
                                <p class="text-2xs text-subtle font-mono tabular mt-1">
                                    @if($membership->status === 'cancelled' && $membership->isLive())
                                        {{ __('سارية حتى :date — لن تُجدَّد', ['date' => $membership->endsOn()?->translatedFormat('j F Y')]) }}
                                    @elseif($membership->isLive() && $membership->renews_at)
                                        {{ __('تُجدَّد في :date', ['date' => $membership->renews_at->translatedFormat('j F Y')]) }}
                                    @elseif($membership->endsOn())
                                        {{ __('انتهت في :date', ['date' => $membership->endsOn()->translatedFormat('j F Y')]) }}
                                    @endif
                                </p>
                            </div>

                            <x-ui.badge :tone="$membership->statusTone()">{{ $membership->statusLabel() }}</x-ui.badge>
                        </div>

                        @if($membership->isLive() && $membership->status !== 'cancelled')
                            <div>
                                <form method="POST" action="{{ route('my-memberships.cancel', $membership->id) }}"
                                      onsubmit="return confirm('{{ __('إيقاف التجديد؟ اشتراكك يبقى سارياً حتى نهاية المدة المدفوعة.') }}')">
                                    @csrf
                                    <x-ui.button type="submit" size="sm" variant="secondary">
                                        {{ __('إيقاف التجديد') }}
                                    </x-ui.button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if($plans->isEmpty())
        @if($memberships->isEmpty())
            <x-ui.card>
                <x-ui.empty :title="__('لا اشتراكات متاحة')">
                    {{ __('لم يعرض مدرّسك باقات اشتراك بعد — اشترِ الكورسات مباشرةً.') }}
                    <x-slot:action>
                        <x-ui.button size="sm" :href="url('/courses')">{{ __('تصفّح الكورسات') }}</x-ui.button>
                    </x-slot:action>
                </x-ui.empty>
            </x-ui.card>
        @endif
    @else
        <section>
            <h2 class="text-sm font-bold text-subtle mb-3">
                {{ $memberships->isEmpty() ? __('الباقات المتاحة') : __('باقات أخرى') }}
            </h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($plans as $plan)
                    @php $subscribed = $liveIds->contains($plan->id); @endphp

                    <div @class(['surface-card p-5 grid gap-3', 'border-primary' => $subscribed])>
                        <div>
                            <p class="font-semibold text-sm">{{ $plan->name }}</p>
                            <p class="font-mono text-xl font-bold tabular mt-1">{{ $plan->price()->format() }}</p>
                            <p class="text-2xs text-subtle">{{ $plan->periodLabel() }}</p>
                        </div>

                        @if($plan->description)
                            <p class="text-xs text-muted leading-relaxed">{{ $plan->description }}</p>
                        @endif

                        <p class="text-2xs text-muted">
                            {{ $plan->scope === 'all'
                                ? __('تفتح كل الكورسات')
                                : __('تفتح :n كورساً مختاراً', ['n' => count((array) $plan->course_ids)]) }}
                            @if($plan->trial_days > 0)
                                · {{ __(':n يوماً تجربة', ['n' => $plan->trial_days]) }}
                            @endif
                        </p>

                        <div class="mt-auto">
                            @if($subscribed)
                                <x-ui.badge tone="success">{{ __('مشترك فيها') }}</x-ui.badge>
                            @else
                                {{-- الشراء يمرّ بالسلة كبقيّة المشتريات: مسارُ دفعٍ ثانٍ يعني عربتين --}}
                                <form method="POST" action="{{ route('cart.add') }}">
                                    @csrf
                                    <input type="hidden" name="type" value="membership">
                                    <input type="hidden" name="id" value="{{ $plan->id }}">
                                    <x-ui.button type="submit" size="sm" class="w-full">{{ __('اشترك') }}</x-ui.button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

</x-layouts.student>
