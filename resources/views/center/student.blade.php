@php
    use App\Modules\Center\Models\Attendance;
    use App\Modules\Center\Models\Invoice;
@endphp

<x-layouts.admin :title="$student->name()" current="students">
<div class="max-w-[1200px]">

    <x-ui.page-header :title="$student->name()"
                      :subtitle="$student->code.' · '.($student->grade?->name ?? '—').' · '.($student->school ?? '—')"
                      :back="url('/admin/center-students')">
        <x-slot:actions>
            <x-ui.button size="sm" variant="secondary" :href="url('/admin/center-students/'.$student->id.'/report?period='.$period)">
                {{ __('التقرير الشهري') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif

    <div class="grid gap-4 sm:grid-cols-3 mb-6">
        <x-ui.stat :label="__('نسبة الحضور')"
                   :value="$report['overall_attendance'] === null ? '—' : $report['overall_attendance'].'%'" />
        <x-ui.stat :label="__('المستحق عليه')" :value="$report['finance']['outstanding']->format()"
                   :delta="$report['finance']['overdue_count'] > 0 ? __(':n فاتورة متأخرة', ['n' => $report['finance']['overdue_count']]) : null"
                   :trend="$report['finance']['overdue_count'] > 0 ? 'down' : null" />
        <x-ui.stat :label="__('المجموعات')" :value="number_format(count($report['groups']))" />
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px] items-start">

        <div class="grid gap-4 min-w-0">
            @foreach($report['groups'] as $row)
                <x-ui.card :title="$row['group']?->name" :subtitle="$row['group']?->subject?->name">
                    <div class="grid gap-4 sm:grid-cols-4 text-center mb-4">
                        <div>
                            <p class="text-2xs text-subtle mb-1">{{ __('حضر') }}</p>
                            <p class="font-mono text-xl tabular text-success">{{ $row['present'] }}</p>
                        </div>
                        <div>
                            <p class="text-2xs text-subtle mb-1">{{ __('غاب') }}</p>
                            <p class="font-mono text-xl tabular text-danger">{{ $row['absent'] }}</p>
                        </div>
                        <div>
                            <p class="text-2xs text-subtle mb-1">{{ __('تأخّر') }}</p>
                            <p class="font-mono text-xl tabular text-warning">{{ $row['late'] }}</p>
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
                        <p class="text-2xs text-subtle mt-1.5 font-mono">{{ $row['attendance_rate'] }}%</p>
                    @endif

                    @if($row['marks']->isNotEmpty())
                        <div class="mt-4 pt-4 border-t border-line">
                            <p class="text-xs font-semibold text-subtle mb-2">{{ __('الدرجات') }}</p>
                            <ul class="grid gap-1.5">
                                @foreach($row['marks'] as $mark)
                                    <li class="flex items-center justify-between gap-3 text-sm">
                                        <span class="min-w-0 truncate">{{ $mark->assessment?->name }}</span>
                                        <span class="font-mono text-xs tabular shrink-0">
                                            @if($mark->is_absent)
                                                <span class="text-danger">{{ __('غائب') }}</span>
                                            @else
                                                {{ rtrim(rtrim(number_format((float) $mark->marks, 2), '0'), '.') }}
                                                / {{ rtrim(rtrim(number_format((float) $mark->assessment?->max_marks, 2), '0'), '.') }}
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </x-ui.card>
            @endforeach

            @if($report['groups'] === [])
                <x-ui.card>
                    <x-ui.empty :title="__('غير مسجّل في مجموعات')">
                        {{ __('سجّله في مجموعة ليبدأ حضوره وأقساطه.') }}
                    </x-ui.empty>
                </x-ui.card>
            @endif

            {{-- التسجيل من هنا أيضاً: من فتح ملف الطالب لا يُرسَل إلى صفحة أخرى ليسجّله --}}
            @if($openGroups->isNotEmpty())
                <x-ui.card :title="__('سجّله في مجموعة')">
                    <form method="POST" action="" class="flex flex-wrap items-end gap-3"
                          x-data="{ group: '' }" x-bind:action="group ? @js(url('/admin/groups')) + '/' + group + '/enrol' : ''">
                        @csrf
                        <input type="hidden" name="student_id" value="{{ $student->id }}">
                        <x-ui.field :label="__('المجموعة')" for="enrol-group" class="mb-0 min-w-64 flex-1" :error="$errors->first('student_id')">
                            <x-ui.select id="enrol-group" name="group" x-model="group" required>
                                <option value="">{{ __('اختر…') }}</option>
                                @foreach($openGroups as $group)
                                    <option value="{{ $group->id }}">
                                        {{ $group->name }} · {{ $group->venueLabel() }} · {{ $group->enrolled_count }}/{{ $group->capacity }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.button type="submit" x-bind:disabled="! group">{{ __('سجّل') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>

        <div class="grid gap-4 min-w-0">
            <x-ui.card :title="__('بياناته')">
                <x-ui.description-list :items="array_filter([
                    __('الكود') => $student->code,
                    __('المرحلة') => $student->stage?->name,
                    __('الصف') => $student->grade?->name,
                    __('المدرسة') => $student->school,
                    __('الفرع') => $student->branch?->name,
                    __('الهاتف') => $student->user?->phone,
                    __('هاتف الطوارئ') => $student->emergency_phone,
                    __('تاريخ الانضمام') => $student->joined_at?->format('Y-m-d'),
                ])" />
            </x-ui.card>

            <x-ui.card :title="__('أولياء الأمر')">
                @if($student->guardians->isEmpty())
                    <p class="text-sm text-subtle">{{ __('لا ولي أمر مسجّل — وهو أهم من يتلقّى تنبيه الغياب.') }}</p>
                @else
                    <ul class="grid gap-2">
                        @foreach($student->guardians as $guardian)
                            <li class="text-sm">
                                <span class="font-medium">{{ $guardian->name }}</span>
                                <span class="text-2xs text-subtle">{{ $guardian->relation }}</span>
                                <span class="block font-mono text-xs text-muted">{{ $guardian->phone }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('الفواتير المستحقة')">
                @if($report['finance']['invoices']->isEmpty())
                    <p class="text-sm text-success">{{ __('لا مستحقات عليه.') }}</p>
                @else
                    <ul class="grid gap-2">
                        @foreach($report['finance']['invoices'] as $invoice)
                            <li class="flex items-center justify-between gap-2 text-sm">
                                <span class="min-w-0">
                                    <span class="block font-mono text-2xs text-subtle">{{ $invoice->period }}</span>
                                    <span class="block truncate">{{ $invoice->group?->name ?? '—' }}</span>
                                </span>
                                <span class="font-mono text-xs tabular shrink-0 {{ $invoice->isOverdue() ? 'text-danger font-semibold' : '' }}">
                                    {{ $invoice->remaining()->format() }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('آخر الإيصالات')">
                @if($payments->isEmpty())
                    <p class="text-sm text-subtle">{{ __('لا مدفوعات بعد.') }}</p>
                @else
                    <ul class="grid gap-2">
                        @foreach($payments as $payment)
                            <li class="flex items-center justify-between gap-2 text-sm">
                                <span class="font-mono text-2xs text-subtle">{{ $payment->receipt_no }}</span>
                                <span class="font-mono text-xs tabular">{{ $payment->amount()->format() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    </div>
</div>
</x-layouts.admin>
