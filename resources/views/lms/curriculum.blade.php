@php
    use App\Modules\Lms\Models\CourseItem;
    $library = ['lesson' => $lessons, 'quiz' => $quizzes, 'assignment' => $assignments];
@endphp

<x-layouts.admin :title="__('منهج :course', ['course' => $course->title])" current="courses">
<div class="max-w-[1200px]">

    <x-ui.page-header :title="$course->title" :subtitle="__('رتّب المنهج: أقسام، وفيها دروس واختبارات وواجبات.')"
                      :back="url('/admin/courses')">
        <x-slot:actions>
            <x-ui.button size="sm" variant="ghost" :href="url('/admin/courses/'.$course->id.'/edit')">{{ __('بيانات الكورس') }}</x-ui.button>
            <x-ui.button size="sm" variant="ghost" :href="url('/admin/courses/'.$course->id.'/gradebook')">
                {{ __('دفتر الدرجات') }}
            </x-ui.button>

            {{--
                بناء الهيكل يُدَلّ عليه من هنا: الصفحة البيضاء هي ما
                يُعطّل، ومن يفتح منهجاً فارغاً هو من يحتاجه.
            --}}
            @if(tenant()?->allows('ai_course_builder'))
                <x-ui.button size="sm" variant="ghost" :href="url('/admin/courses/'.$course->id.'/ai-outline')">
                    {{ __('ابنِ الهيكل') }}
                </x-ui.button>
            @endif
            <x-ui.button size="sm" variant="secondary" :href="url('/courses/'.$course->slug)" target="_blank" rel="noopener">
                {{ __('معاينة') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if($errors->any())
        <x-ui.alert tone="danger" :title="__('راجع ما يلي')" class="mb-4">
            <ul class="list-disc list-inside grid gap-1 mt-1">
                @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </x-ui.alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px] items-start">

        <div class="grid gap-4 min-w-0">
            @forelse($course->sections as $section)
                <x-ui.card :title="$section->title" :padding="false">
                    <x-slot:actions>
                        <form method="POST" action="{{ url('/admin/courses/'.$course->id.'/sections/'.$section->id) }}"
                              x-data @submit="if (! confirm(@js(__('سيُحذف القسم وتبقى عناصره بلا قسم. متابعة؟')))) $event.preventDefault()">
                            @csrf @method('DELETE')
                            <x-ui.button size="sm" variant="ghost" type="submit">{{ __('حذف القسم') }}</x-ui.button>
                        </form>
                    </x-slot:actions>

                    @if($section->items->isEmpty())
                        <div class="p-5">
                            <x-ui.empty :title="__('قسم فارغ')">{{ __('أضف إليه درساً أو اختباراً من مكتبتك على اليسار.') }}</x-ui.empty>
                        </div>
                    @else
                        <ul class="divide-y divide-[var(--color-line)]">
                            @foreach($section->items as $item)
                                <x-lms.curriculum-row :item="$item" :course="$course" />
                            @endforeach
                        </ul>
                    @endif

                    <x-slot:footer>
                        <form method="POST" action="{{ url('/admin/courses/'.$course->id.'/items') }}"
                              class="flex flex-wrap items-end gap-2">
                            @csrf
                            <input type="hidden" name="section_id" value="{{ $section->id }}">
                            <label class="grid gap-1 flex-1 min-w-[140px]">
                                <span class="text-2xs text-subtle">{{ __('النوع') }}</span>
                                <x-ui.select name="kind" class="!py-1.5">
                                    @foreach(CourseItem::LABELS as $key => $label)
                                        <option value="{{ $key }}">{{ __($label) }}</option>
                                    @endforeach
                                </x-ui.select>
                            </label>
                            <label class="grid gap-1 flex-[2] min-w-[180px]">
                                <span class="text-2xs text-subtle">{{ __('العنصر') }}</span>
                                <x-ui.select name="itemable_id" class="!py-1.5">
                                    @foreach($library as $kind => $collection)
                                        <optgroup label="{{ __(CourseItem::LABELS[$kind]) }}">
                                            @foreach($collection as $entry)
                                                <option value="{{ $entry->id }}">{{ $entry->title }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </x-ui.select>
                            </label>
                            <x-ui.button size="sm" type="submit">{{ __('أضف') }}</x-ui.button>
                        </form>
                    </x-slot:footer>
                </x-ui.card>
            @empty
                <x-ui.card>
                    <x-ui.empty :title="__('المنهج فارغ')">
                        {{ __('ابدأ بقسم واحد — «المقدمة» مثلاً — ثم ضع فيه أول درس.') }}
                    </x-ui.empty>
                </x-ui.card>
            @endforelse

            @if($orphans->isNotEmpty())
                <x-ui.card :title="__('عناصر بلا قسم')"
                           :subtitle="__('تظهر للطالب في نهاية المنهج — انقلها إلى قسم لترتيبها.')" :padding="false">
                    <ul class="divide-y divide-[var(--color-line)]">
                        @foreach($orphans as $item)
                            <x-lms.curriculum-row :item="$item" :course="$course" />
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>

        <div class="grid gap-4 min-w-0">
            <x-ui.card :title="__('قسم جديد')">
                <form method="POST" action="{{ url('/admin/courses/'.$course->id.'/sections') }}">
                    @csrf
                    <x-admin.fields.translatable name="title" :label="__('عنوان القسم')" :required="true" />
                    <x-ui.button size="sm" type="submit" class="w-full">{{ __('أضف القسم') }}</x-ui.button>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('المكتبة')"
                       :subtitle="__('الدرس كيان مستقل: يظهر في أكثر من كورس بلا تكرار.')">
                <x-ui.description-list :items="[
                    __('دروس') => number_format($lessons->count()),
                    __('اختبارات') => number_format($quizzes->count()),
                    __('واجبات') => number_format($assignments->count()),
                ]" />
                <div class="grid gap-2 mt-3">
                    <x-ui.button size="sm" variant="secondary" :href="url('/admin/lessons/create')">{{ __('درس جديد') }}</x-ui.button>
                    <x-ui.button size="sm" variant="secondary" :href="url('/admin/quizzes/create')">{{ __('اختبار جديد') }}</x-ui.button>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('حالة الكورس')">
                <x-ui.description-list :items="[
                    __('الحالة') => __(App\Modules\Lms\Models\Course::STATUSES[$course->status] ?? $course->status),
                    __('العناصر') => number_format($course->lessons_count),
                    __('الطلاب') => number_format($course->students_count),
                    __('التسلسل') => $course->sequential ? __('إجباري') : __('حر'),
                    __('الفتح التدريجي') => $course->drip_enabled ? __('مفعّل') : __('معطّل'),
                ]" />
            </x-ui.card>
        </div>
    </div>
</div>
</x-layouts.admin>
