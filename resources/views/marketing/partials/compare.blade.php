@php
    $compare = config('marketing.compare');
    $columns = $compare['columns'];
    $brand = config('app.name') === 'Laravel' ? config('marketing.brand.name') : config('app.name');
    $rowCount = collect($compare['groups'])->flatten(1)->count();
@endphp

<x-marketing.section
    id="compare"
    wide
    :eyebrow="__('دراسة منافسين على 7 فئات — عربية وأجنبية')"
    :title="__('المقارنة كاملة، بما فيها ما يتفوّقون فيه')"
    :lead="__('راجعنا بنّاة منصّات الكورسات وبنّاة منصّات المدرّسين وأنظمة السناتر ومنصّات المجتمعات وأنظمة التعلّم المؤسسية. ما وجدناه عند أحدهم وليس عندنا — بنيناه.')"
>
    <div class="rounded-2xl border border-line bg-surface overflow-hidden shadow-sm">

        {{-- المفتاح يسبق الجدول: جدول بلا مفتاح ادّعاء لا معلومة --}}
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 px-5 sm:px-6 py-4 border-b border-line text-xs text-muted">
            <span class="font-bold text-content me-auto">{{ trans_choice('[2,10] :count مقارنات|[11,*] :count نقطة مقارنة', $rowCount, ['count' => $rowCount]) }}</span>
            <span class="flex items-center gap-1.5"><x-marketing.mark state="yes" /> {{ __('متوفر') }}</span>
            <span class="flex items-center gap-1.5"><x-marketing.mark state="partial" /> {{ __('جزئي أو بباقة أعلى') }}</span>
            <span class="flex items-center gap-1.5"><x-marketing.mark state="no" /> {{ __('غير متوفر') }}</span>
            <span class="flex items-center gap-1.5"><x-marketing.mark state="soon" /> {{ __('معلن قريباً') }}</span>
        </div>

        {{-- الجدول يمرّر داخل حاويته ولا يدفع الصفحة (وثيقة 13، قاعدة 2) --}}
        <div class="overflow-x-auto">
            <table class="w-full min-w-[48rem] text-sm border-collapse">
                <caption class="sr-only">{{ __('مقارنة المميزات بيننا وبين أبرز المنافسين') }}</caption>

                <thead>
                    <tr>
                        <th scope="col"
                            class="sticky start-0 z-10 bg-surface text-start font-bold px-5 sm:px-6 py-4 min-w-[17rem] border-b border-line">
                            {{ __('الميزة') }}
                        </th>
                        @foreach($columns as $column)
                            <th scope="col" class="px-2 py-4 text-center font-semibold text-subtle text-xs whitespace-nowrap min-w-[6.5rem] border-b border-line">{{ $column }}</th>
                        @endforeach
                        <th scope="col" class="px-2 py-4 text-center whitespace-nowrap min-w-[7rem] bg-spot text-on-spot rounded-t-xl">
                            <span class="font-display font-extrabold text-base">{{ $brand }}</span>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($compare['groups'] as $groupTitle => $rows)
                        <tr>
                            <th scope="colgroup" colspan="{{ count($columns) + 1 }}"
                                class="text-start text-2xs font-bold tracking-wide text-accent-text bg-surface-sunken px-5 sm:px-6 py-2.5">
                                {{ __($groupTitle) }}
                            </th>
                            <td class="bg-spot" aria-hidden="true"></td>
                        </tr>

                        @foreach($rows as [$label, $states])
                            <tr class="border-t border-line">
                                <th scope="row"
                                    class="sticky start-0 z-10 bg-surface text-start font-normal px-5 sm:px-6 py-3">
                                    {{ __($label) }}
                                </th>
                                @foreach($states as $state)
                                    <td class="px-2 py-3 text-center">
                                        <x-marketing.mark :state="$state" />
                                    </td>
                                @endforeach
                                <td class="px-2 py-3 text-center bg-spot">
                                    <span class="inline-flex items-center justify-center text-base leading-none text-on-spot-accent">
                                        <span aria-hidden="true">●</span>
                                        <span class="sr-only">{{ __('متوفر') }}</span>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="px-5 sm:px-6 py-4 border-t border-line text-2xs text-subtle leading-relaxed">
            {{ __($compare['note']) }}
        </p>
    </div>

    {{-- الصراحة تبيع أكثر من الادّعاء --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-8">
        @foreach([
            ['q' => 'وأين يتفوّقون علينا؟', 'a' => 'سمارت سنتر وPrime E أرخص منّا وأوسع انتشاراً في مصر منذ سنين، وزمن أنضج منتجاً اليوم. ونحن أشمل بفارق كبير — والشمول يحتاج وقتاً لتثق به.'],
            ['q' => 'ولماذا لا يقلّدوننا؟', 'a' => 'من يبني منصّة كورسات لا يعرف الخزنة والأقساط والقاعات، ومن يبني نظام سنتر لا يعرف الفيديو المشفّر والقمع التسويقي. الجمع قرار معماري من اليوم الأول لا ميزة تُضاف.'],
            ['q' => 'وإن احتجت ميزة غير موجودة؟', 'a' => 'الكود ملكنا. ما يطلبه السوق نبنيه في أيام ونطرحه بنشر تدريجي — لا ننتظر خارطة طريق شركة أجنبية لا تعرف سوقك.'],
        ] as $item)
            <div class="rounded-2xl border border-line bg-surface p-6 min-w-0 lift">
                <h3 class="font-bold text-lg mb-2.5 leading-snug">{{ __($item['q']) }}</h3>
                <p class="text-muted leading-relaxed">{{ __($item['a']) }}</p>
            </div>
        @endforeach
    </div>
</x-marketing.section>
