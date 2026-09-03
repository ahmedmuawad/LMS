@php
    $student = $report['student'];
    $finance = $report['finance'];
@endphp

<x-layouts.app :title="__('التقرير الشهري — :name', ['name' => $student->name()])">
<main id="main" class="max-w-[820px] mx-auto px-4 sm:px-6 py-8">

    <header class="text-center mb-8 pb-6 border-b border-line">
        <p class="text-xs text-subtle mb-1">{{ setting()->translated('general.site_name') ?: tenant('name') }}</p>
        <h1 class="text-2xl font-bold">{{ __('التقرير الشهري') }}</h1>
        <p class="text-sm text-muted mt-1">
            {{ $student->name() }} · {{ $student->grade?->name }} ·
            {{ \Illuminate\Support\Carbon::parse($report['period'].'-01')->translatedFormat('F Y') }}
        </p>
    </header>

    <div class="grid gap-4 sm:grid-cols-3 mb-8">
        <x-ui.stat :label="__('نسبة الحضور')"
                   :value="$report['overall_attendance'] === null ? '—' : $report['overall_attendance'].'%'" />
        <x-ui.stat :label="__('المستحق')" :value="$finance['outstanding']->format()" />
        <x-ui.stat :label="__('المجموعات')" :value="number_format(count($report['groups']))" />
    </div>

    @foreach($report['groups'] as $row)
        <x-ui.card :title="$row['group']?->name"
                   :subtitle="($row['group']?->subject?->name ?? '').' · '.($row['group']?->teacher?->name ?? '')"
                   class="mb-4">
            <x-ui.description-list :items="array_filter([
                __('عدد الحصص') => $row['sessions'],
                __('حضر') => $row['present'],
                __('غاب') => $row['absent'],
                __('تأخّر') => $row['late'],
                __('بعذر') => $row['excused'] ?: null,
                __('نسبة الحضور') => $row['attendance_rate'] === null ? '—' : $row['attendance_rate'].'%',
                __('متوسط الدرجات') => $row['average'] === null ? '—' : $row['average'].'%',
            ])" />

            @if($row['marks']->isNotEmpty())
                <div class="mt-4 pt-4 border-t border-line">
                    <p class="text-xs font-semibold text-subtle mb-2">{{ __('تفصيل الدرجات') }}</p>
                    <x-ui.table>
                        <thead>
                            <tr>
                                @foreach ([__('التقييم'), __('الدرجة'), __('من'), __('النسبة')] as $th)
                                    <th class="bg-surface-sunken text-start font-semibold text-2xs text-muted px-3 py-2 border-b border-line">{{ $th }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($row['marks'] as $mark)
                                <tr>
                                    <td class="px-3 py-2 border-b border-line text-sm">{{ $mark->assessment?->name }}</td>
                                    <td class="px-3 py-2 border-b border-line font-mono text-xs tabular">
                                        {{ $mark->is_absent ? __('غائب') : rtrim(rtrim(number_format((float) $mark->marks, 2), '0'), '.') }}
                                    </td>
                                    <td class="px-3 py-2 border-b border-line font-mono text-xs tabular text-muted">
                                        {{ rtrim(rtrim(number_format((float) $mark->assessment?->max_marks, 2), '0'), '.') }}
                                    </td>
                                    <td class="px-3 py-2 border-b border-line font-mono text-xs tabular">
                                        {{ $mark->percentage() === null ? '—' : $mark->percentage().'%' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </div>
            @endif
        </x-ui.card>
    @endforeach

    @if($finance['invoices']->isNotEmpty())
        <x-ui.card :title="__('المستحقات')" class="mb-4">
            <ul class="grid gap-2">
                @foreach($finance['invoices'] as $invoice)
                    <li class="flex items-center justify-between gap-3 text-sm">
                        <span>
                            {{ $invoice->group?->name ?? '—' }}
                            <span class="text-2xs text-subtle font-mono">{{ $invoice->period }}</span>
                        </span>
                        <span class="font-mono tabular {{ $invoice->isOverdue() ? 'text-danger font-semibold' : '' }}">
                            {{ $invoice->remaining()->format() }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    <p class="text-2xs text-subtle text-center mt-8">
        {{ __('صدر في :date', ['date' => now()->translatedFormat('j F Y')]) }}
    </p>
</main>
</x-layouts.app>
