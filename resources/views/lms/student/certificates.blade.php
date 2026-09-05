<x-layouts.student :title="__('شهاداتي')" current="certificates">

    <x-ui.page-header :title="__('شهاداتي')"
                      :subtitle="__('شهاداتك بأكوادها — كل كود يتحقّق منه أي جهة برابط عام.')" />

    @if($certificates->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا شهادات بعد')">
                {{ __('تُصدَر الشهادة تلقائياً حين تُنهي كورساً يمنحها.') }}
                <x-slot:action>
                    <x-ui.button size="sm" :href="url('/my-courses')">{{ __('كورساتي') }}</x-ui.button>
                </x-slot:action>
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach($certificates as $certificate)
                @php $revoked = $certificate->revoked_at !== null; @endphp
                <div @class(['surface-card p-4 grid gap-3', 'opacity-70' => $revoked])>

                    <div class="flex items-start gap-3">
                        <span class="text-2xl leading-none shrink-0" aria-hidden="true">◈</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm">{{ $certificate->course?->title ?? __('شهادة') }}</p>
                            <p class="text-xs text-muted font-mono tabular mt-0.5">
                                {{ $certificate->issued_at?->translatedFormat('j F Y') ?? '—' }}
                            </p>
                        </div>

                        @if($revoked)
                            <x-ui.badge tone="danger">{{ __('ملغاة') }}</x-ui.badge>
                        @elseif($certificate->expires_at && $certificate->expires_at->isPast())
                            <x-ui.badge tone="warning">{{ __('منتهية') }}</x-ui.badge>
                        @else
                            <x-ui.badge tone="success">{{ __('سارية') }}</x-ui.badge>
                        @endif
                    </div>

                    {{-- الكود هو الشهادة: هو ما يُعطى لجهة تريد التحقّق --}}
                    <div class="rounded-md bg-surface-sunken px-3 py-2 flex items-center gap-2">
                        <span class="text-2xs text-subtle shrink-0">{{ __('الكود') }}</span>
                        <code class="font-mono text-xs tabular truncate flex-1">{{ $certificate->code }}</code>
                    </div>

                    @if($revoked && $certificate->revoke_reason)
                        <p class="text-xs text-danger">{{ $certificate->revoke_reason }}</p>
                    @endif

                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="sm" variant="secondary"
                                     :href="url('/certificate/'.$certificate->code)">{{ __('عرض الشهادة') }}</x-ui.button>

                        @if($certificate->pdf_path && ! $revoked)
                            <x-ui.button size="sm" variant="ghost"
                                         :href="url('/certificate/'.$certificate->code.'?download=1')">{{ __('تنزيل PDF') }}</x-ui.button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</x-layouts.student>
