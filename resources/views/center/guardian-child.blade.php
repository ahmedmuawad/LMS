<x-layouts.app :title="$student->name()">
<x-site.header />

<main id="main" class="max-w-[820px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.breadcrumb class="mb-4" :items="[
        ['label' => __('أبنائي'), 'url' => url('/guardian')],
        ['label' => $student->name()],
    ]" />

    <x-ui.page-header :title="$student->name()"
                      :subtitle="$student->code.' · '.($student->grade?->name ?? '')" />

    <div class="grid gap-4 sm:grid-cols-3 mb-6">
        <x-ui.stat :label="__('نسبة الحضور')"
                   :value="$report['overall_attendance'] === null ? '—' : $report['overall_attendance'].'%'" />
        <x-ui.stat :label="__('المستحق')" :value="$report['finance']['outstanding']->format()"
                   :delta="$report['finance']['overdue_count'] > 0 ? __('متأخر') : null"
                   :trend="$report['finance']['overdue_count'] > 0 ? 'down' : null" />
        <x-ui.stat :label="__('المجموعات')" :value="number_format(count($report['groups']))" />
    </div>

    @foreach($report['groups'] as $row)
        <x-ui.card :title="$row['group']?->name" :subtitle="$row['group']?->teacher?->name" class="mb-4">
            <div class="grid grid-cols-3 gap-4 text-center mb-4">
                <div>
                    <p class="text-2xs text-subtle mb-1">{{ __('حضر') }}</p>
                    <p class="font-mono text-xl tabular text-success">{{ $row['present'] }}</p>
                </div>
                <div>
                    <p class="text-2xs text-subtle mb-1">{{ __('غاب') }}</p>
                    <p class="font-mono text-xl tabular text-danger">{{ $row['absent'] }}</p>
                </div>
                <div>
                    <p class="text-2xs text-subtle mb-1">{{ __('المتوسط') }}</p>
                    <p class="font-mono text-xl tabular">{{ $row['average'] === null ? '—' : $row['average'].'%' }}</p>
                </div>
            </div>

            @if($row['attendance_rate'] !== null)
                <x-ui.progress :value="$row['attendance_rate']"
                               :tone="$row['attendance_rate'] >= 80 ? 'attended' : ($row['attendance_rate'] >= 60 ? 'late' : 'absent')"
                               :label="__('نسبة الحضور')" />
            @endif
        </x-ui.card>
    @endforeach

    @if($report['finance']['invoices']->isNotEmpty())
        <x-ui.card :title="__('المستحقات')">
            <ul class="grid gap-2">
                @foreach($report['finance']['invoices'] as $invoice)
                    <li class="flex items-center justify-between gap-3 text-sm">
                        <span>{{ $invoice->group?->name ?? '—' }} <span class="text-2xs text-subtle font-mono">{{ $invoice->period }}</span></span>
                        <span class="font-mono tabular {{ $invoice->isOverdue() ? 'text-danger font-semibold' : '' }}">
                            {{ $invoice->remaining()->format() }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</main>

<x-site.footer />
</x-layouts.app>
