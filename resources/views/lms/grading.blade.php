<x-layouts.admin :title="__('التصحيح')" current="grading">
<div class="max-w-[1200px]">

    <x-ui.page-header :title="__('طاولة التصحيح')"
                      :subtitle="__('ما ينتظر عينك أنت — مرتّباً بالأقدم، فمن سلّم أولاً أحقّ بأن يُصحَّح أولاً.')" />

    <div class="grid gap-4 sm:grid-cols-2 mb-6">
        <x-ui.stat :label="__('أسئلة مقالية تنتظر')" :value="number_format($attempts->count())" />
        <x-ui.stat :label="__('واجبات تنتظر')" :value="number_format($submissions->count())" />
    </div>

    <div class="grid gap-4">
        <x-ui.card :title="__('محاولات الاختبارات')" :padding="false">
            @if($attempts->isEmpty())
                <div class="p-5"><x-ui.empty :title="__('لا شيء ينتظر')" tone="success" icon="✓">{{ __('كل المحاولات مصحّحة.') }}</x-ui.empty></div>
            @else
                <div class="overflow-x-auto">
                    <x-ui.table>
                        <thead>
                            <tr>
                                @foreach ([__('الطالب'), __('الكورس'), __('الاختبار'), __('سُلّم'), ''] as $th)
                                    <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($attempts as $attempt)
                                <tr class="hover:bg-surface-sunken transition-colors">
                                    <td class="px-4 py-3 border-b border-line text-sm">{{ $attempt->enrollment?->user?->name }}</td>
                                    <td class="px-4 py-3 border-b border-line text-sm">{{ $attempt->enrollment?->course?->title }}</td>
                                    <td class="px-4 py-3 border-b border-line text-sm">{{ $attempt->quiz?->title }}</td>
                                    <td class="px-4 py-3 border-b border-line text-xs text-subtle whitespace-nowrap">{{ $attempt->submitted_at?->diffForHumans() }}</td>
                                    <td class="px-4 py-3 border-b border-line">
                                        <x-ui.button size="sm" variant="secondary" :href="url('/admin/grading/attempts/'.$attempt->id)">
                                            {{ __('صحّح') }}
                                        </x-ui.button>
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
                <div class="p-5"><x-ui.empty :title="__('لا واجبات تنتظر')" tone="success" icon="✓">{{ __('كل ما سُلّم صُحّح.') }}</x-ui.empty></div>
            @else
                <div class="overflow-x-auto">
                    <x-ui.table>
                        <thead>
                            <tr>
                                @foreach ([__('الطالب'), __('الكورس'), __('الواجب'), __('سُلّم'), ''] as $th)
                                    <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($submissions as $submission)
                                <tr class="hover:bg-surface-sunken transition-colors">
                                    <td class="px-4 py-3 border-b border-line text-sm">{{ $submission->enrollment?->user?->name }}</td>
                                    <td class="px-4 py-3 border-b border-line text-sm">{{ $submission->enrollment?->course?->title }}</td>
                                    <td class="px-4 py-3 border-b border-line text-sm">
                                        {{ $submission->assignment?->title }}
                                        @if($submission->is_late)<x-ui.badge tone="warning" class="ms-1">{{ __('متأخر') }}</x-ui.badge>@endif
                                    </td>
                                    <td class="px-4 py-3 border-b border-line text-xs text-subtle whitespace-nowrap">{{ $submission->submitted_at?->diffForHumans() }}</td>
                                    <td class="px-4 py-3 border-b border-line">
                                        <x-ui.button size="sm" variant="secondary" :href="url('/admin/grading/submissions/'.$submission->id)">
                                            {{ __('صحّح') }}
                                        </x-ui.button>
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
