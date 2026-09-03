<x-layouts.admin :title="__('الطلاب')" current="students">
<div class="max-w-[1400px]">

    <x-ui.page-header :title="__('طلابي')"
                      :subtitle="__('من التحق بكورساتك، وأين وصل كل واحد.')" />

    <x-ui.card class="mb-4">
        <form method="GET" action="{{ url('/admin/students') }}" class="grid gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
            <x-ui.field :label="__('بحث بالاسم')" name="q">
                <x-ui.input name="q" :value="$search" :placeholder="__('اسم الطالب')" />
            </x-ui.field>

            <x-ui.field :label="__('الكورس')" name="course">
                <x-ui.select name="course">
                    <option value="">{{ __('كل الكورسات') }}</option>
                    @foreach($courses as $item)
                        <option value="{{ $item->id }}" @selected((string) $course === (string) $item->id)>{{ $item->title }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>

            <x-ui.field :label="__('الحالة')" name="status">
                <x-ui.select name="status">
                    <option value="">{{ __('كل الحالات') }}</option>
                    @foreach(App\Modules\Lms\Models\Enrollment::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected((string) $status === $key)>{{ __($label) }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>

            <x-ui.button type="submit" variant="secondary">{{ __('طبّق') }}</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card :padding="false">
        @if($enrollments->isEmpty())
            <div class="p-5">
                <x-ui.empty :title="__('لا طلاب')">
                    {{ __('لا تسجيل يطابق ما بحثت عنه.') }}
                </x-ui.empty>
            </div>
        @else
            <div class="overflow-x-auto">
                <x-ui.table>
                    <thead>
                        <tr>
                            @foreach ([__('الطالب'), __('الكورس'), __('التقدّم'), __('الدرجة'), __('الحالة'), __('التحق'), ''] as $th)
                                <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($enrollments as $row)
                            <tr class="hover:bg-surface-sunken transition-colors">
                                <td class="px-4 py-3 border-b border-line text-sm">{{ $row->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3 border-b border-line text-sm min-w-0">{{ $row->course?->title }}</td>
                                <td class="px-4 py-3 border-b border-line w-32">
                                    <x-ui.progress :value="$row->progress_percent" :label="__('التقدّم')" />
                                    <span class="text-2xs text-subtle font-mono tabular">{{ (int) $row->progress_percent }}%</span>
                                </td>
                                <td class="px-4 py-3 border-b border-line text-sm font-mono tabular">{{ $row->grade === null ? '—' : number_format((float) $row->grade, 1) }}</td>
                                <td class="px-4 py-3 border-b border-line">
                                    <x-ui.badge :tone="$row->status === 'completed' ? 'success' : ($row->status === 'active' ? 'info' : 'neutral')">
                                        {{ __(App\Modules\Lms\Models\Enrollment::STATUSES[$row->status] ?? $row->status) }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-4 py-3 border-b border-line text-xs text-subtle whitespace-nowrap">{{ $row->created_at?->diffForHumans() }}</td>
                                <td class="px-4 py-3 border-b border-line">
                                    <x-ui.button size="sm" variant="secondary" :href="url('/admin/students/'.$row->id)">{{ __('التقدّم') }}</x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </div>

            @if($enrollments->hasPages())
                <div class="p-4 border-t border-line">
                    <x-ui.pagination :current="$enrollments->currentPage()" :last="$enrollments->lastPage()"
                                     :url="request()->fullUrlWithQuery(['page' => '']).''" />
                </div>
            @endif
        @endif
    </x-ui.card>
</div>
</x-layouts.admin>
