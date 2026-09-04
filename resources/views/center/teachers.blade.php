<x-layouts.admin :title="__('مدرّسو السنتر')" current="center-teachers">
<div class="max-w-[1200px]">

    <x-ui.page-header :title="__('مدرّسو السنتر')"
                      :subtitle="__('كل مدرّس بمادته ومواعيده وطلبته — في شاشة واحدة لا ثلاث.')" />

    <div class="grid gap-4 sm:grid-cols-3 mb-6">
        <x-ui.stat :label="__('المدرّسون')" :value="number_format($teachers->count())" />
        <x-ui.stat :label="__('المواد')" :value="number_format($subjects->count())" />
        <x-ui.stat :label="__('إجمالي الطلاب')" :value="number_format($teachers->sum('students'))" />
    </div>

    @if($teachers->isEmpty())
        <x-ui.empty :title="__('لا مدرّسين مُسنَدين')">
            {{ __('أسنِد كل مدرّس إلى مادته ليظهر هنا بمواعيده وطلبته.') }}
        </x-ui.empty>
    @else
        <div class="grid gap-4">
            @foreach($teachers as $row)
                <x-ui.card :padding="false">
                    <div class="flex flex-wrap items-start justify-between gap-3 px-5 pt-4 pb-3 border-b border-line">
                        <div class="min-w-0">
                            <h3 class="text-base font-bold truncate">{{ $row['teacher']->name }}</h3>
                            <p class="text-xs text-muted mt-0.5">
                                @foreach($row['subjects'] as $subject)
                                    <x-ui.badge tone="primary" class="me-1">{{ $subject }}</x-ui.badge>
                                @endforeach
                            </p>
                        </div>

                        <div class="flex items-center gap-4 shrink-0 text-center">
                            <div>
                                <div class="font-mono text-lg font-medium tabular">{{ number_format($row['groups']->count()) }}</div>
                                <div class="text-2xs text-subtle">{{ __('مجموعة') }}</div>
                            </div>
                            <div>
                                <div class="font-mono text-lg font-medium tabular">{{ number_format($row['students']) }}</div>
                                <div class="text-2xs text-subtle">{{ __('طالب') }}</div>
                            </div>
                            <div>
                                <div class="font-mono text-lg font-medium tabular">{{ number_format($row['slots']->count()) }}</div>
                                <div class="text-2xs text-subtle">{{ __('موعد') }}</div>
                            </div>
                        </div>
                    </div>

                    @if($row['slots']->isEmpty())
                        <div class="px-5 py-4">
                            <p class="text-sm text-muted">{{ __('لا مواعيد بعد — أضف موعداً لمجموعاته ليظهر جدوله.') }}</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <x-ui.table>
                                <thead>
                                    <tr>
                                        @foreach ([__('اليوم'), __('الوقت'), __('القاعة'), __('المجموعة'), __('الصف'), __('الطلاب'), ''] as $th)
                                            <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-2.5 border-b border-line whitespace-nowrap">{{ $th }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($row['slots'] as $slot)
                                        <tr class="hover:bg-surface-sunken transition-colors">
                                            <td class="px-4 py-2.5 border-b border-line text-sm whitespace-nowrap">{{ $slot->weekdayLabel() }}</td>
                                            <td class="px-4 py-2.5 border-b border-line text-sm font-mono tabular whitespace-nowrap">{{ $slot->timeLabel() }}</td>
                                            <td class="px-4 py-2.5 border-b border-line text-sm">
                                                {{ $slot->room?->name ?? __('بلا قاعة') }}
                                            </td>
                                            <td class="px-4 py-2.5 border-b border-line text-sm min-w-0">{{ $slot->group?->subject?->name }}</td>
                                            <td class="px-4 py-2.5 border-b border-line text-sm">{{ $slot->group?->grade?->name ?? '—' }}</td>
                                            <td class="px-4 py-2.5 border-b border-line text-sm font-mono tabular">{{ $slot->group?->enrolled_count }}</td>
                                            <td class="px-4 py-2.5 border-b border-line">
                                                <x-ui.button size="sm" variant="secondary"
                                                             :href="url('/admin/groups/'.$slot->group_id.'/slots')">{{ __('المواعيد') }}</x-ui.button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.table>
                        </div>
                    @endif
                </x-ui.card>
            @endforeach
        </div>
    @endif
</div>
</x-layouts.admin>
