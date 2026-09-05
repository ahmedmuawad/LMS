<x-layouts.admin :title="__('دفتر الدرجات')" current="courses">
<div class="max-w-[1400px]">

    <x-ui.page-header :title="__('دفتر الدرجات: :course', ['course' => $course->title])"
                      :subtitle="__('كل طالب في صفّ، وكل اختبار وواجب في عمود — وأفضل محاولة لا آخرها.')"
                      :back="url('/admin/courses/'.$course->getKey().'/curriculum')">
        <x-slot:actions>
            @if($rows->isNotEmpty())
                <x-ui.button size="sm" variant="secondary"
                             :href="url('/admin/courses/'.$course->getKey().'/gradebook/export')">
                    {{ __('صدّر CSV') }}
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if($columns->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا مُقيَّم في هذا الكورس')">
                {{ __('الدفتر يجمع درجات الاختبارات والواجبات — أضف اختباراً أو واجباً إلى المنهج أولاً.') }}
            </x-ui.empty>
        </x-ui.card>
    @elseif($rows->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا طلبة مسجّلين بعد')">
                {{ __('يظهر هنا كل طالب سجّل في الكورس ودرجاته في كل مُقيَّم.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        {{--
            الجدول يمرّر أفقياً داخل حاويته لا مع الصفحة.
            كورسٌ فيه عشرون اختباراً يُخرج الصفحة عن عرضها، فيضيع
            عمود اسم الطالب — وهو أوّل ما يُقرأ.
        --}}
        <section class="surface-card overflow-x-auto">
            <table class="w-full text-sm min-w-[720px]">
                <thead class="bg-surface-sunken text-2xs text-subtle">
                    <tr>
                        <th class="text-start font-semibold px-4 py-2.5 sticky start-0 bg-surface-sunken">
                            {{ __('الطالب') }}
                        </th>

                        @foreach($columns as $column)
                            <th class="text-start font-semibold px-3 py-2.5 whitespace-nowrap">
                                <span class="block max-w-[160px] truncate">{{ $column['title'] }}</span>
                                <span class="font-mono tabular opacity-70">
                                    {{ rtrim(rtrim(number_format($column['max'], 2), '0'), '.') }}
                                </span>
                            </th>
                        @endforeach

                        <th class="text-start font-semibold px-3 py-2.5">{{ __('المجموع') }}</th>
                        <th class="text-start font-semibold px-3 py-2.5">{{ __('النسبة') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-[var(--color-line)]">
                    @foreach($rows as $row)
                        <tr>
                            <td class="px-4 py-2.5 sticky start-0 bg-surface">
                                <span class="block max-w-[200px] truncate">{{ $row['student']?->name ?? '—' }}</span>
                            </td>

                            @foreach($columns as $column)
                                @php $score = $row['cells'][$column['key']] ?? null; @endphp
                                <td class="px-3 py-2.5 font-mono tabular {{ $score === null ? 'text-subtle' : '' }}">
                                    {{-- الشرطة تعني «لم يُسلّم»، والصفر يعني «سلّم وأخذ صفراً» --}}
                                    {{ $score === null ? '—' : rtrim(rtrim(number_format((float) $score, 2), '0'), '.') }}
                                </td>
                            @endforeach

                            <td class="px-3 py-2.5 font-mono tabular font-bold">
                                {{ rtrim(rtrim(number_format($row['total'], 2), '0'), '.') }}
                            </td>

                            <td class="px-3 py-2.5">
                                <x-ui.badge :tone="$row['percent'] >= 60 ? 'success' : ($row['percent'] > 0 ? 'warning' : 'neutral')">
                                    {{ $row['percent'] }}%
                                </x-ui.badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <p class="text-2xs text-subtle mt-3">
            {{ __('«—» تعني أن الطالب لم يُسلّم، و«0» تعني أنه سلّم ولم يُصب شيئاً. والنهاية العظمى :max درجة.', [
                'max' => rtrim(rtrim(number_format($max, 2), '0'), '.'),
            ]) }}
        </p>
    @endif

</div>
</x-layouts.admin>
