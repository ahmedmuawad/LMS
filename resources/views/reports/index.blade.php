<x-layouts.admin :title="__('التقارير')">

<x-ui.page-header :title="__('التقارير')"
                  :subtitle="__('من :from إلى :to', [
                      'from' => $from->translatedFormat('j M Y'),
                      'to' => $to->translatedFormat('j M Y'),
                  ])">
    <x-slot:actions>
        <x-ui.button as="a" variant="secondary"
                     :href="route('admin.reports.export', request()->query())">{{ __('صدّر CSV') }}</x-ui.button>
    </x-slot:actions>
</x-ui.page-header>

<div class="flex flex-wrap items-center gap-2 mb-4">
    @foreach(['learning' => 'التعليم', 'activity' => 'النشاط', 'financial' => 'المال', 'marketing' => 'التسويق'] as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}"
           @class(['min-h-11 px-4 grid place-items-center rounded-md text-sm font-semibold border transition-colors',
                   'bg-primary text-primary-on border-transparent' => $tab === $key,
                   'bg-surface border-line-strong hover:bg-surface-sunken' => $tab !== $key])>{{ __($label) }}</a>
    @endforeach
</div>

<form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
    <input type="hidden" name="tab" value="{{ $tab }}">

    <x-ui.field :label="__('المدة')" for="preset" class="mb-0 w-full sm:w-52">
        <x-ui.select name="preset" id="preset">
            @foreach(['7' => 'آخر ٧ أيام', '30' => 'آخر ٣٠ يوماً', '90' => 'آخر ٩٠ يوماً', '365' => 'آخر سنة', 'custom' => 'مدى مخصّص'] as $value => $label)
                <option value="{{ $value }}" @selected($preset === $value)>{{ __($label) }}</option>
            @endforeach
        </x-ui.select>
    </x-ui.field>

    <x-ui.field :label="__('من')" for="from" class="mb-0 w-full sm:w-44">
        <x-ui.input type="date" name="from" id="from" value="{{ request('from', $from->toDateString()) }}" />
    </x-ui.field>

    <x-ui.field :label="__('إلى')" for="to" class="mb-0 w-full sm:w-44">
        <x-ui.input type="date" name="to" id="to" value="{{ request('to', $to->toDateString()) }}" />
    </x-ui.field>

    <x-ui.button size="sm" type="submit" class="h-11">{{ __('اعرض') }}</x-ui.button>
</form>

@if($tab === 'learning')
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-5">
        <x-ui.stat :label="__('تسجيلات جديدة')" :value="number_format($data['enrolled'])" />
        <x-ui.stat :label="__('أتمّوا')" :value="number_format($data['completed'])" />
        <x-ui.stat :label="__('نسبة الإتمام')" :value="$data['completion_rate'].'%'" />
        <x-ui.stat :label="__('شهادات')" :value="number_format($data['certificates'])" />
        <x-ui.stat :label="__('متوسّط التقدّم')" :value="$data['avg_progress'].'%'" />
        <x-ui.stat :label="__('نجاح الاختبارات')" :value="$data['quiz_pass_rate'].'%'" />
        <x-ui.stat :label="__('أسئلة بلا إجابة')" :value="number_format($data['unanswered_questions'])" />
    </div>

    <x-reports.chart :series="$data['daily']" :label="__('التسجيلات يومياً')" class="mb-5" />

    <section class="surface-card overflow-x-auto">
        <h2 class="px-4 py-3 border-b border-default font-bold text-sm">{{ __('أكثر الكورسات تسجيلاً') }}</h2>
        <table class="w-full text-sm min-w-[420px]">
            <thead class="bg-surface-sunken text-2xs text-subtle">
                <tr>
                    <th class="text-start font-semibold px-4 py-2.5">{{ __('الكورس') }}</th>
                    <th class="text-start font-semibold px-4 py-2.5">{{ __('تسجيلات') }}</th>
                    <th class="text-start font-semibold px-4 py-2.5">{{ __('التقييم') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($data['top_courses'] as $course)
                    <tr>
                        <td class="px-4 py-2.5">
                            <a href="{{ url('/courses/'.$course->slug) }}" class="tap-link hover:text-primary transition-colors">{{ $course->title }}</a>
                        </td>
                        <td class="px-4 py-2.5 font-mono tabular">{{ number_format((int) $course->enrollments_count) }}</td>
                        <td class="px-4 py-2.5 font-mono tabular">{{ $course->rating_avg > 0 ? number_format((float) $course->rating_avg, 1) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-subtle">{{ __('لا تسجيلات في هذه المدة.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

@elseif($tab === 'activity')
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-5">
        <x-ui.stat :label="__('عبارات مسجّلة')" :value="number_format($data['statements'])" />
        <x-ui.stat :label="__('طلبة نشِطون')" :value="number_format($data['learners'])" />
        <x-ui.stat :label="__('إتمامات')" :value="number_format($data['completions'])" />
        <x-ui.stat :label="__('نسبة النجاح')" :value="$data['pass_rate'].'%'" />
        <x-ui.stat :label="__('متوسّط الدرجة')" :value="$data['avg_score'].'%'" />
    </div>

    <x-reports.chart :series="$data['daily']" :label="__('النشاط يومياً')" class="mb-5" />

    <section class="surface-card overflow-x-auto">
        <h2 class="px-4 py-3 border-b border-default font-bold text-sm">{{ __('أكثر الأنشطة تكراراً') }}</h2>
        <table class="w-full text-sm min-w-[480px]">
            <thead class="bg-surface-sunken text-2xs text-subtle">
                <tr>
                    <th class="text-start font-semibold px-4 py-2.5">{{ __('النشاط') }}</th>
                    <th class="text-start font-semibold px-4 py-2.5">{{ __('محاولات') }}</th>
                    <th class="text-start font-semibold px-4 py-2.5">{{ __('متوسّط الدرجة') }}</th>
                    <th class="text-start font-semibold px-4 py-2.5">{{ __('تعثّرات') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($data['activities'] as $row)
                    <tr>
                        <td class="px-4 py-2.5">{{ $row->object_name }}</td>
                        <td class="px-4 py-2.5 font-mono tabular">{{ number_format((int) $row->attempts) }}</td>
                        <td class="px-4 py-2.5 font-mono tabular">
                            {{ $row->avg_score === null ? '—' : number_format((float) $row->avg_score, 1).'%' }}
                        </td>
                        <td class="px-4 py-2.5 font-mono tabular">{{ number_format((int) $row->failures) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-subtle">
                        {{ __('لا نشاط في هذه المدة. يظهر هنا ما يُسجّله المحتوى التفاعلي وما يصل من تطبيقات مربوطة.') }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

@elseif($tab === 'financial')
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-5">
        <x-ui.stat :label="__('المحصّل')" :value="$data['revenue']->format()" />
        <x-ui.stat :label="__('المستردّ')" :value="$data['refunds']->format()" />
        <x-ui.stat :label="__('الصافي')" :value="$data['net']->format()" />
        <x-ui.stat :label="__('متوسّط الطلب')" :value="$data['average_order']->format()" />
        <x-ui.stat :label="__('الطلبات')" :value="number_format($data['orders'])" />
        <x-ui.stat :label="__('المدفوعة')" :value="number_format($data['paid_orders'])" />
        <x-ui.stat :label="__('نسبة الإتمام')" :value="$data['conversion'].'%'" />
        <x-ui.stat :label="__('الحجوزات')" :value="number_format($data['bookings'])" />
    </div>

    <x-reports.chart :series="$data['daily']" :divide="100" :label="__('المحصّل يومياً')" class="mb-5" />

    <section class="surface-card overflow-x-auto">
        <h2 class="px-4 py-3 border-b border-default font-bold text-sm">{{ __('حسب بوابة الدفع') }}</h2>
        <table class="w-full text-sm min-w-[420px]">
            <thead class="bg-surface-sunken text-2xs text-subtle">
                <tr>
                    <th class="text-start font-semibold px-4 py-2.5">{{ __('البوابة') }}</th>
                    <th class="text-start font-semibold px-4 py-2.5">{{ __('عمليات') }}</th>
                    <th class="text-start font-semibold px-4 py-2.5">{{ __('الإجمالي') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($data['by_gateway'] as $row)
                    <tr>
                        <td class="px-4 py-2.5">{{ __(config('payments.gateways.'.$row['gateway'].'.label', $row['gateway'])) }}</td>
                        <td class="px-4 py-2.5 font-mono tabular">{{ number_format($row['orders']) }}</td>
                        <td class="px-4 py-2.5 font-mono tabular font-bold">{{ $row['total']->format() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-subtle">{{ __('لا مدفوعات في هذه المدة.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

@else
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-5">
        <x-ui.stat :label="__('مستخدمون جدد')" :value="number_format($data['new_users'])" />
        <x-ui.stat :label="__('مسوّقون نشطون')" :value="number_format($data['affiliates'])" />
        <x-ui.stat :label="__('نقرات المسوّقين')" :value="number_format($data['affiliate_clicks'])" />
        <x-ui.stat :label="__('مبيعاتهم')" :value="number_format($data['affiliate_sales'])" />
        <x-ui.stat :label="__('عمولاتهم')" :value="$data['affiliate_cost']->format()" />
    </div>

    <div class="grid gap-5 lg:grid-cols-2 items-start">
        <section class="surface-card overflow-x-auto">
            <h2 class="px-4 py-3 border-b border-default font-bold text-sm">{{ __('أعلى المسوّقين') }}</h2>
            <table class="w-full text-sm min-w-[320px]">
                <tbody class="divide-y divide-[var(--color-line)]">
                    @forelse($data['top_affiliates'] as $affiliate)
                        <tr>
                            <td class="px-4 py-2.5">{{ $affiliate->user?->name }}</td>
                            <td class="px-4 py-2.5 font-mono tabular">{{ $affiliate->conversionRate() }}%</td>
                            <td class="px-4 py-2.5 font-mono tabular font-bold">{{ $affiliate->earned()->format() }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-6 text-center text-subtle">{{ __('لا مسوّقين بعد.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="surface-card overflow-x-auto">
            <h2 class="px-4 py-3 border-b border-default font-bold text-sm">{{ __('التسلسلات التسويقية') }}</h2>
            <table class="w-full text-sm min-w-[360px]">
                <thead class="bg-surface-sunken text-2xs text-subtle">
                    <tr>
                        <th class="text-start font-semibold px-4 py-2.5">{{ __('التسلسل') }}</th>
                        <th class="text-start font-semibold px-4 py-2.5">{{ __('دخلوا') }}</th>
                        <th class="text-start font-semibold px-4 py-2.5">{{ __('تحوّلوا') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-line)]">
                    @forelse($data['campaigns'] as $campaign)
                        <tr>
                            <td class="px-4 py-2.5">{{ $campaign->name }}</td>
                            <td class="px-4 py-2.5 font-mono tabular">{{ number_format((int) $campaign->entered) }}</td>
                            <td class="px-4 py-2.5 font-mono tabular font-bold">{{ number_format((int) $campaign->converted) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-subtle">{{ __('لا تسلسلات بعد.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
@endif

</x-layouts.admin>
