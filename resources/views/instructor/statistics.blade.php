@php
    $max = max(1, max($enrollmentsByDay ?: [0]));
@endphp

<x-layouts.admin :title="__('الإحصاءات')" current="statistics">
<div class="max-w-[1200px]">

    <x-ui.page-header :title="__('إحصاءاتي')"
                      :subtitle="__('كورساً كورساً — والأضعف إتماماً أولاً، فهو الذي يحتاج عملاً.')" />

    <div class="grid gap-4 sm:grid-cols-3 mb-6">
        <x-ui.stat :label="__('إيراد آخر :n يوماً', ['n' => $days])" :value="$revenue->format()" />
        <x-ui.stat :label="__('عمليات بيع')" :value="number_format($sales)" />

        @if($feesCollected)
            <x-ui.stat :label="__('محصَّل الأقساط (:days يوماً)', ['days' => $days])"
                       :value="$feesCollected->format()" />
        @endif
        <x-ui.stat :label="__('تقييمات جديدة')" :value="number_format($newReviews)" />
    </div>

    <x-ui.card :title="__('التسجيلات اليومية')" class="mb-4">
        {{-- رسم بأعمدة CSS: لا مكتبة، ولا طلب شبكة، ولا سكربت يُنتظر --}}
        <div class="flex items-end gap-[3px] h-32" role="img"
             aria-label="{{ __('تسجيلات آخر :n يوماً', ['n' => $days]) }}">
            @foreach($enrollmentsByDay as $day => $count)
                <div class="flex-1 min-w-0 h-full flex items-end" title="{{ $day }} — {{ $count }}">
                    <span class="block w-full rounded-t-sm bg-primary"
                          style="height: {{ max(2, (int) round($count / $max * 100)) }}%"></span>
                </div>
            @endforeach
        </div>
        <p class="text-2xs text-subtle mt-2 flex justify-between">
            <span>{{ array_key_first($enrollmentsByDay) }}</span>
            <span>{{ array_key_last($enrollmentsByDay) }}</span>
        </p>
    </x-ui.card>

    <x-ui.card :title="__('أداء الكورسات')" :padding="false">
        @if($rows->isEmpty())
            <div class="p-5"><x-ui.empty :title="__('لا كورسات')">{{ __('أنشئ كورساً لتظهر إحصاءاته هنا.') }}</x-ui.empty></div>
        @else
            <div class="overflow-x-auto">
                <x-ui.table>
                    <thead>
                        <tr>
                            @foreach ([__('الكورس'), __('ملتحق'), __('أتمّ'), __('نسبة الإتمام'), __('التقييم'), ''] as $th)
                                <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr class="hover:bg-surface-sunken transition-colors">
                                <td class="px-4 py-3 border-b border-line text-sm min-w-0">{{ $row['course']->title }}</td>
                                <td class="px-4 py-3 border-b border-line text-sm font-mono tabular">{{ number_format($row['enrolled']) }}</td>
                                <td class="px-4 py-3 border-b border-line text-sm font-mono tabular">{{ number_format($row['completed']) }}</td>
                                <td class="px-4 py-3 border-b border-line w-40">
                                    @if($row['rate'] === null)
                                        <span class="text-xs text-subtle">{{ __('لا طلاب بعد') }}</span>
                                    @else
                                        <x-ui.progress :value="$row['rate']"
                                                       :tone="$row['rate'] >= 60 ? 'success' : ($row['rate'] >= 30 ? 'progress' : 'late')"
                                                       :label="__('نسبة الإتمام')" />
                                        <span class="text-2xs text-subtle font-mono tabular">{{ $row['rate'] }}%</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 border-b border-line text-sm whitespace-nowrap">
                                    @if($row['rating'] === null)
                                        <span class="text-subtle">—</span>
                                    @else
                                        <span class="font-mono tabular">{{ $row['rating'] }}</span> ★
                                        <span class="text-2xs text-subtle">({{ number_format($row['reviews']) }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 border-b border-line">
                                    <x-ui.button size="sm" variant="secondary" :href="url('/admin/students').'?course='.$row['course']->id">{{ __('طلابه') }}</x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </div>
        @endif
    </x-ui.card>
</div>
</x-layouts.admin>
