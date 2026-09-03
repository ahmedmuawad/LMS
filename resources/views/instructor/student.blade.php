@php
    $course = $enrollment->course;
    $items  = $course?->items->sortBy('position') ?? collect();
@endphp

<x-layouts.admin :title="__('تقدّم الطالب')" current="students">
<div class="max-w-[1100px]">

    <x-ui.page-header :title="$enrollment->user?->name ?? __('طالب')"
                      :subtitle="$course?->title"
                      :back="url('/admin/students')" />

    <div class="grid gap-4 sm:grid-cols-3 mb-6">
        <x-ui.stat :label="__('التقدّم')" :value="((int) $enrollment->progress_percent).'%'" />
        <x-ui.stat :label="__('الدرجة')" :value="$enrollment->grade === null ? '—' : number_format((float) $enrollment->grade, 1)" />
        <x-ui.stat :label="__('الحالة')" :value="__(App\Modules\Lms\Models\Enrollment::STATUSES[$enrollment->status] ?? $enrollment->status)"
                   :delta="$enrollment->completed_at?->translatedFormat('j M Y')" />
    </div>

    <div class="grid gap-4">

        <x-ui.card :title="__('المنهج')" :padding="false">
            @if($items->isEmpty())
                <div class="p-5"><x-ui.empty :title="__('لا عناصر في المنهج')">{{ __('أضف دروساً ليظهر تقدّم الطالب فيها.') }}</x-ui.empty></div>
            @else
                <ul class="divide-y divide-line">
                    @foreach($items as $item)
                        @php $row = $progress[$item->id] ?? null; @endphp
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <span class="text-sm min-w-0 truncate">{{ $item->itemable?->title ?? '—' }}</span>
                            <x-ui.badge :tone="$row?->status === 'completed' ? 'success' : ($row === null ? 'neutral' : 'info')" class="shrink-0">
                                {{ $row?->status === 'completed' ? __('أُنجز') : ($row === null ? __('لم يُفتح') : __('قيد التقدّم')) }}
                            </x-ui.badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('محاولات الاختبارات')" :padding="false">
            @if($attempts->isEmpty())
                <div class="p-5"><x-ui.empty :title="__('لا محاولات')">{{ __('لم يبدأ هذا الطالب أي اختبار بعد.') }}</x-ui.empty></div>
            @else
                <div class="overflow-x-auto">
                    <x-ui.table>
                        <thead>
                            <tr>
                                @foreach ([__('الاختبار'), __('الدرجة'), __('الحالة'), __('سُلّم'), ''] as $th)
                                    <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($attempts as $attempt)
                                <tr class="hover:bg-surface-sunken transition-colors">
                                    <td class="px-4 py-3 border-b border-line text-sm">{{ $attempt->quiz?->title ?? '—' }}</td>
                                    <td class="px-4 py-3 border-b border-line text-sm font-mono tabular">{{ $attempt->score === null ? '—' : number_format((float) $attempt->score, 1) }}</td>
                                    <td class="px-4 py-3 border-b border-line"><x-ui.badge tone="neutral">{{ $attempt->status }}</x-ui.badge></td>
                                    <td class="px-4 py-3 border-b border-line text-xs text-subtle whitespace-nowrap">{{ $attempt->submitted_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="px-4 py-3 border-b border-line">
                                        @if($attempt->status === 'submitted')
                                            <x-ui.button size="sm" variant="secondary" :href="url('/admin/grading/attempts/'.$attempt->id)">{{ __('صحّح') }}</x-ui.button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </div>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('الواجبات')" :padding="false">
            @if($submissions->isEmpty())
                <div class="p-5"><x-ui.empty :title="__('لا تسليمات')">{{ __('لم يسلّم هذا الطالب أي واجب بعد.') }}</x-ui.empty></div>
            @else
                <div class="overflow-x-auto">
                    <x-ui.table>
                        <thead>
                            <tr>
                                @foreach ([__('الواجب'), __('الدرجة'), __('الحالة'), __('سُلّم'), ''] as $th)
                                    <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($submissions as $submission)
                                <tr class="hover:bg-surface-sunken transition-colors">
                                    <td class="px-4 py-3 border-b border-line text-sm">{{ $submission->assignment?->title ?? '—' }}</td>
                                    <td class="px-4 py-3 border-b border-line text-sm font-mono tabular">{{ $submission->marks === null ? '—' : number_format((float) $submission->marks, 1) }}</td>
                                    <td class="px-4 py-3 border-b border-line"><x-ui.badge tone="neutral">{{ $submission->status }}</x-ui.badge></td>
                                    <td class="px-4 py-3 border-b border-line text-xs text-subtle whitespace-nowrap">{{ $submission->submitted_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="px-4 py-3 border-b border-line">
                                        @if($submission->status === 'pending')
                                            <x-ui.button size="sm" variant="secondary" :href="url('/admin/grading/submissions/'.$submission->id)">{{ __('صحّح') }}</x-ui.button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </div>
            @endif
        </x-ui.card>
    </div>
</div>
</x-layouts.admin>
