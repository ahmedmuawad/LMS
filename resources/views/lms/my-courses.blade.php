<x-layouts.student :title="__('كورساتي')" current="my-courses">

<div>

    <x-ui.page-header :title="__('كورساتي')" :subtitle="__('ما تتعلّمه الآن، وما أنهيته، وشهاداتك.')" />

    @if($active->isEmpty() && $completed->isEmpty() && $expired->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لم تسجّل في كورس بعد')">
                {{ __('تصفّح الكورسات واختر ما يناسبك — بعضها مجاني تماماً.') }}
                <x-slot:action>
                    <x-ui.button size="sm" :href="url('/courses')">{{ __('تصفّح الكورسات') }}</x-ui.button>
                </x-slot:action>
            </x-ui.empty>
        </x-ui.card>
    @else
        @if($active->isNotEmpty())
            <section class="mb-8">
                <h2 class="text-lg font-bold mb-3">{{ __('تتعلّمه الآن') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($active as $enrollment)
                        @if($enrollment->course)
                            <x-lms.course-card :course="$enrollment->course" :progress="$enrollment->progress_percent" />
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        @if($completed->isNotEmpty())
            <section class="mb-8">
                <h2 class="text-lg font-bold mb-3">{{ __('أنهيته') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($completed as $enrollment)
                        @if($enrollment->course)
                            <x-lms.course-card :course="$enrollment->course" :progress="100" />
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        @if($expired->isNotEmpty())
            <section class="mb-8">
                <h2 class="text-lg font-bold mb-1">{{ __('انتهت مدة وصولك') }}</h2>
                <p class="text-sm text-muted mb-3">{{ __('سجلّك ودرجاتك وشهاداتك محفوظة — يمكنك التجديد في أي وقت.') }}</p>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($expired as $enrollment)
                        @if($enrollment->course)
                            <x-lms.course-card :course="$enrollment->course" :progress="$enrollment->progress_percent" />
                        @endif
                    @endforeach
                </div>
            </section>
        @endif
    @endif

    @if($certificates->isNotEmpty())
        <section>
            <h2 class="text-lg font-bold mb-3">{{ __('شهاداتك') }}</h2>
            <x-ui.card :padding="false">
                <x-ui.table>
                    <thead>
                        <tr>
                            @foreach ([__('الكورس'), __('الكود'), __('تاريخ الإصدار'), __('الحالة')] as $th)
                                <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($certificates as $certificate)
                            <tr class="hover:bg-surface-sunken transition-colors">
                                <td class="px-4 py-3 border-b border-line text-sm">{{ $certificate->course?->title }}</td>
                                <td class="px-4 py-3 border-b border-line">
                                    <a href="{{ url('/certificate/'.$certificate->code) }}"
                                       class="tap-link font-mono text-xs text-primary hover:underline">{{ $certificate->code }}</a>
                                </td>
                                <td class="px-4 py-3 border-b border-line font-mono text-xs">{{ $certificate->issued_at?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 border-b border-line">
                                    <x-ui.badge :tone="$certificate->isValid() ? 'success' : 'neutral'">{{ $certificate->statusLabel() }}</x-ui.badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </section>
    @endif
</div>

</x-layouts.student>
