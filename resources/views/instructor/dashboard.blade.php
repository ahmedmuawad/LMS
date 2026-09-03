<x-layouts.admin :title="__('اللوحة')" current="dashboard">
<div class="max-w-[1400px]">

    <x-ui.page-header :title="__('أهلاً بك، :name', ['name' => auth()->user()?->name])"
                      :subtitle="__('كورساتك وطلابك وما ينتظر عملك — لا شيء عن غيرك.')" />

    {{-- ما ينتظر فعلاً أولاً: اللوحة أداة عمل لا تقرير --}}
    @php
        $todo = [
            ['count' => $pendingAttempts,    'label' => __('محاولات اختبار تنتظر تصحيحك'), 'url' => url('/admin/grading')],
            ['count' => $pendingSubmissions, 'label' => __('واجبات تنتظر تصحيحك'),         'url' => url('/admin/grading')],
            ['count' => $openQuestions,      'label' => __('أسئلة بلا إجابة'),              'url' => url('/admin/discussions')],
            ['count' => $pendingBookings,    'label' => __('حجوزات تنتظرك'),                'url' => url('/admin/bookings')],
        ];
        $todo = array_values(array_filter($todo, fn ($row) => $row['count'] > 0));
    @endphp

    @if($todo !== [])
        <x-ui.card :title="__('ينتظر عملك الآن')" class="mb-6">
            <ul class="grid gap-2 sm:grid-cols-2">
                @foreach($todo as $row)
                    <li>
                        <a href="{{ $row['url'] }}"
                           class="flex items-center gap-3 rounded-lg border border-line p-3 min-h-11 hover:bg-surface-sunken transition-colors">
                            <span class="font-mono text-lg font-medium tabular shrink-0">{{ number_format($row['count']) }}</span>
                            <span class="text-sm min-w-0">{{ $row['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <x-ui.stat :label="__('كورساتي')" :value="number_format($coursesCount)"
                   :delta="__(':n منشور', ['n' => number_format($publishedCount)])" />
        <x-ui.stat :label="__('طلابي')" :value="number_format($studentsCount)"
                   :delta="__(':n تسجيل نشط', ['n' => number_format($activeCount)])" />
        <x-ui.stat :label="__('أتمّوا الكورس')" :value="number_format($completedCount)" />
        <x-ui.stat :label="__('تقييمي')"
                   :value="$rating === null ? '—' : $rating.' ★'"
                   :delta="$reviewsCount > 0 ? trans_choice('{1}تقييم واحد|{2}تقييمان|[3,10]:count تقييمات|[11,*]:count تقييماً', $reviewsCount, ['count' => number_format($reviewsCount)]) : null" />
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">

        <x-ui.card :title="__('أحدث الملتحقين')" :padding="false">
            <x-slot:actions>
                <x-ui.button size="sm" variant="ghost" :href="url('/admin/students')">{{ __('كل الطلاب') }}</x-ui.button>
            </x-slot:actions>

            @if($latest->isEmpty())
                <div class="p-5">
                    <x-ui.empty :title="__('لا طلاب بعد')">
                        {{ __('أول ملتحق بكورسك سيظهر هنا.') }}
                        <x-slot:action>
                            <x-ui.button size="sm" :href="url('/admin/courses')">{{ __('إلى كورساتي') }}</x-ui.button>
                        </x-slot:action>
                    </x-ui.empty>
                </div>
            @else
                <div class="overflow-x-auto">
                    <x-ui.table>
                        <thead>
                            <tr>
                                @foreach ([__('الطالب'), __('الكورس'), __('التقدّم'), __('الحالة')] as $th)
                                    <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($latest as $row)
                                <tr class="hover:bg-surface-sunken transition-colors">
                                    <td class="px-4 py-3 border-b border-line text-sm">
                                        {{-- اسم الطالب رابط، فليكن هدف لمس لا سطر نصّ --}}
                                        <a class="inline-flex items-center min-h-11 hover:underline"
                                           href="{{ url('/admin/students/'.$row->id) }}">{{ $row->user?->name ?? '—' }}</a>
                                    </td>
                                    <td class="px-4 py-3 border-b border-line text-sm min-w-0">{{ $row->course?->title }}</td>
                                    <td class="px-4 py-3 border-b border-line w-32">
                                        <x-ui.progress :value="$row->progress_percent" :label="__('التقدّم')" />
                                        <span class="text-2xs text-subtle font-mono tabular">{{ (int) $row->progress_percent }}%</span>
                                    </td>
                                    <td class="px-4 py-3 border-b border-line">
                                        <x-ui.badge :tone="$row->status === 'completed' ? 'success' : ($row->status === 'active' ? 'info' : 'neutral')">
                                            {{ __(App\Modules\Lms\Models\Enrollment::STATUSES[$row->status] ?? $row->status) }}
                                        </x-ui.badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </div>
            @endif
        </x-ui.card>

        <div class="grid gap-4 content-start">
            <x-ui.card :title="__('أرباحي')">
                <x-slot:actions>
                    <x-ui.button size="sm" variant="ghost" :href="url('/admin/earnings')">{{ __('التفاصيل') }}</x-ui.button>
                </x-slot:actions>

                <div class="grid gap-3">
                    <div>
                        <div class="text-xs text-subtle mb-1">{{ __('متاح للسحب') }}</div>
                        <div class="font-mono text-2xl font-medium tabular">{{ $balance?->format() ?? '—' }}</div>
                    </div>
                    <x-ui.divider />
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="text-muted">{{ __('إجمالي ما استحققته') }}</span>
                        <span class="font-mono tabular">{{ App\Core\Support\Money::fromMinor($lifetime, $balance?->currency ?? (tenant('currency') ?? 'EGP'))->format() }}</span>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('كورساتي')" :padding="false">
                <x-slot:actions>
                    <x-ui.button size="sm" variant="ghost" :href="url('/admin/courses/create')">{{ __('كورس جديد') }}</x-ui.button>
                </x-slot:actions>

                @if($courses->isEmpty())
                    <div class="p-5"><x-ui.empty :title="__('لا كورسات بعد')">{{ __('ابدأ بكورسك الأول.') }}</x-ui.empty></div>
                @else
                    <ul class="divide-y divide-line">
                        @foreach($courses as $course)
                            <li>
                                <a href="{{ url('/admin/courses/'.$course->id.'/curriculum') }}"
                                   class="flex items-center justify-between gap-3 px-5 py-3 min-h-11 hover:bg-surface-sunken transition-colors">
                                    <span class="text-sm min-w-0 truncate">{{ $course->title }}</span>
                                    <span class="text-2xs text-subtle font-mono tabular shrink-0">
                                        {{ trans_choice('{0}لا طلاب|{1}طالب واحد|{2}طالبان|[3,10]:count طلاب|[11,*]:count طالباً', $course->enrollments_count, ['count' => number_format($course->enrollments_count)]) }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    </div>
</div>
</x-layouts.admin>
