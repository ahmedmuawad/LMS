@php
    /** @var \App\Core\Admin\Resource $resource */
    $columns = $resource->columns();
    $filters = $resource->filters();
    $sort    = request('sort', $resource->defaultSort()[0]);
    $dir     = request('dir') === 'asc' ? 'asc' : 'desc';
    $hasQuery = filled(request('q')) || collect($filters)->contains(fn ($f) => filled(request($f->name)));
@endphp

<x-layouts.admin :title="$resource->label()" :current="$key">
<div class="max-w-[1400px]">

    <x-ui.page-header :title="$resource->label()"
                      :subtitle="trans_choice(':count سجل|:count سجلات', $records->total(), ['count' => $records->total()])">
        <x-slot:actions>
            <x-ui.button size="sm">{{ __('إضافة') }} {{ $resource->singularLabel() }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- البحث والفلاتر: نموذج GET واحد حتى تبقى الحالة في الرابط --}}
    <form method="GET" class="surface-card p-4 mb-4 flex flex-wrap items-end gap-3">
        @if($resource->searchableColumns() !== [])
            <label class="flex flex-col gap-1.5 flex-1 min-w-52">
                <span class="text-xs font-semibold text-muted">{{ __('بحث') }}</span>
                <x-ui.input name="q" type="search" value="{{ request('q') }}"
                            placeholder="{{ __('اكتب للبحث…') }}" />
            </label>
        @endif

        @foreach($filters as $filter)
            @php
                $filterProps = [
                    'name'  => $filter->name,
                    'label' => $filter->getLabel(),
                    'value' => request($filter->name),
                ] + $filter->props();
            @endphp
            <x-dynamic-component :component="$filter->component()"
                                 :attributes="new Illuminate\View\ComponentAttributeBag($filterProps)" />
        @endforeach

        <div class="flex gap-2">
            <x-ui.button type="submit" size="sm" variant="secondary">{{ __('تطبيق') }}</x-ui.button>
            @if($hasQuery)
                <x-ui.button size="sm" variant="ghost" :href="url()->current()">{{ __('مسح') }}</x-ui.button>
            @endif
        </div>
    </form>

    @if($records->isEmpty())
        @php $empty = $resource->emptyState(); @endphp
        <x-ui.empty :title="$hasQuery ? __('لا نتائج مطابقة') : $empty['title']" :icon="$hasQuery ? '🔍' : '＋'">
            {{ $hasQuery ? __('جرّب كلمة بحث أخرى أو امسح الفلاتر.') : $empty['body'] }}
            <x-slot:action>
                @if($hasQuery)
                    <x-ui.button size="sm" variant="secondary" :href="url()->current()">{{ __('مسح الفلاتر') }}</x-ui.button>
                @else
                    <x-ui.button size="sm">{{ __('إضافة') }} {{ $resource->singularLabel() }}</x-ui.button>
                @endif
            </x-slot:action>
        </x-ui.empty>
    @else
        <x-ui.table>
            <thead>
                <tr>
                    @foreach($columns as $column)
                        <th @class(['bg-surface-sunken font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap',
                                    'text-start' => $column->getAlign() === 'start',
                                    'text-end'   => $column->getAlign() === 'end'])
                            @if($column->getWidth()) style="width: {{ $column->getWidth() }}" @endif
                            @if($column->isSortable() && $sort === $column->name) aria-sort="{{ $dir === 'asc' ? 'ascending' : 'descending' }}" @endif>
                            @if($column->isSortable())
                                <a href="{{ request()->fullUrlWithQuery(['sort' => $column->name, 'dir' => $sort === $column->name && $dir === 'asc' ? 'desc' : 'asc']) }}"
                                   class="inline-flex items-center gap-1 hover:text-content transition-colors">
                                    {{ $column->getLabel() }}
                                    <span aria-hidden="true" class="text-2xs">{{ $sort === $column->name ? ($dir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                                </a>
                            @else
                                {{ $column->getLabel() }}
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($records as $record)
                    <tr class="hover:bg-surface-sunken transition-colors">
                        @foreach($columns as $column)
                            <td @class(['px-4 py-3 border-b border-line align-top',
                                        'text-end'      => $column->getAlign() === 'end',
                                        'whitespace-nowrap' => ! $column->shouldWrap()])>
                                @php
                                    $cellProps = ['value' => $column->value($record)] + $column->props($record);
                                @endphp
                                <x-dynamic-component :component="$column->component()"
                                                     :attributes="new Illuminate\View\ComponentAttributeBag($cellProps)" />
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>

        @if($records->hasPages())
            <div class="mt-4">
                <x-ui.pagination :current="$records->currentPage()" :last="$records->lastPage()"
                                 :url="request()->fullUrlWithQuery(['page' => '']).''" />
            </div>
        @endif
    @endif
</div>
</x-layouts.admin>
