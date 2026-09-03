<x-layouts.admin :title="__('الأسئلة والردود')" current="discussions">
<div class="max-w-[1100px]">

    <x-ui.page-header :title="__('الأسئلة والردود')"
                      :subtitle="__('سؤال بلا إجابة يوقف طالباً — المعلّق أولاً لا الأحدث.')" />

    <div class="flex flex-wrap items-center gap-2 mb-4">
        @foreach(['open' => __('مفتوح'), 'answered' => __('أُجيب'), 'closed' => __('مغلق'), 'all' => __('الكل')] as $key => $label)
            <a href="{{ url('/admin/discussions').'?status='.$key }}"
               @class([
                   'inline-flex items-center min-h-11 px-3 rounded-md text-sm border transition-colors',
                   'bg-primary text-primary-on border-primary' => $status === $key,
                   'border-line-strong hover:bg-surface-sunken' => $status !== $key,
               ])>
                {{ $label }}
                @if($key === 'open' && $openCount > 0)
                    <span class="ms-2 font-mono text-2xs tabular">{{ number_format($openCount) }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <x-ui.card :padding="false">
        @if($discussions->isEmpty())
            <div class="p-5">
                <x-ui.empty :title="__('لا شيء هنا')" tone="success" icon="✓">
                    {{ __('لا أسئلة في هذه الحالة.') }}
                </x-ui.empty>
            </div>
        @else
            <ul class="divide-y divide-line">
                @foreach($discussions as $discussion)
                    <li>
                        <a href="{{ url('/admin/discussions/'.$discussion->id) }}"
                           class="block px-5 py-4 hover:bg-surface-sunken transition-colors">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold truncate">{{ $discussion->title }}</p>
                                    <p class="text-xs text-subtle mt-1">
                                        {{ $discussion->user?->name ?? '—' }}
                                        · {{ $discussion->course?->title }}
                                        · {{ ($discussion->last_reply_at ?? $discussion->created_at)?->diffForHumans() }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    @if($discussion->replies_count > 0)
                                        <span class="text-2xs text-subtle font-mono tabular">{{ number_format($discussion->replies_count) }} ↩</span>
                                    @endif
                                    <x-ui.badge :tone="$discussion->status === 'open' ? 'warning' : ($discussion->status === 'answered' ? 'success' : 'neutral')">
                                        {{ __(App\Modules\Community\Models\Discussion::STATUSES[$discussion->status] ?? $discussion->status) }}
                                    </x-ui.badge>
                                </div>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>

            @if($discussions->hasPages())
                <div class="p-4 border-t border-line">
                    <x-ui.pagination :current="$discussions->currentPage()" :last="$discussions->lastPage()"
                                     :url="request()->fullUrlWithQuery(['page' => '']).''" />
                </div>
            @endif
        @endif
    </x-ui.card>
</div>
</x-layouts.admin>
