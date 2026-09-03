<x-layouts.admin :title="__('مراجعة التقييمات')">

<x-ui.page-header :title="__('التقييمات')"
                  :subtitle="__('التقييم من غير مشترٍ يُفسد المتوسط — والمراجعة هي ما يحمي رقمك.')" />

@if(session('status'))
    <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
@endif

<div class="flex items-center gap-2 mb-6 flex-wrap">
    @foreach(['pending' => 'بانتظار المراجعة', 'approved' => 'منشور', 'rejected' => 'مرفوض', 'all' => 'الكل'] as $value => $label)
        <a href="{{ request()->fullUrlWithQuery(['status' => $value, 'courses' => null, 'services' => null]) }}"
           @class(['min-h-11 px-4 grid place-items-center rounded-md text-sm font-semibold border transition-colors',
                   'bg-primary text-primary-on border-transparent' => $status === $value,
                   'bg-surface border-line-strong hover:bg-surface-sunken' => $status !== $value])>{{ __($label) }}</a>
    @endforeach
</div>

@php
    $sections = [
        ['type' => 'course', 'title' => __('تقييمات الكورسات'), 'rows' => $courseReviews, 'pageName' => 'courses'],
        ['type' => 'service', 'title' => __('تقييمات الخدمات'), 'rows' => $serviceReviews, 'pageName' => 'services'],
    ];
@endphp

@foreach($sections as $section)
    <section class="mb-8">
        <h2 class="text-lg font-bold mb-3">{{ $section['title'] }}</h2>

        @if($section['rows']->isEmpty())
            <x-ui.card>
                <x-ui.empty :title="__('لا تقييمات هنا')">{{ __('لا شيء بانتظارك في هذا التبويب.') }}</x-ui.empty>
            </x-ui.card>
        @else
            <ul class="flex flex-col gap-3">
                @foreach($section['rows'] as $review)
                    <li class="surface-card p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono text-sm" aria-label="{{ trans_choice('{1} نجمة|{2} نجمتان|[3,10] :count نجوم', $review->rating, ['count' => $review->rating]) }}">
                                        {{ str_repeat('★', $review->rating) }}<span class="text-subtle">{{ str_repeat('☆', 5 - $review->rating) }}</span>
                                    </span>
                                    <span class="font-semibold text-sm">{{ $review->user?->name }}</span>
                                    <x-ui.badge :tone="match($review->status) {
                                        'approved' => 'success', 'rejected' => 'danger', default => 'warning',
                                    }">
                                        {{ __(['pending' => 'بانتظار المراجعة', 'approved' => 'منشور', 'rejected' => 'مرفوض'][$review->status]) }}
                                    </x-ui.badge>
                                </div>

                                <p class="text-2xs text-subtle mt-1">
                                    {{ $section['type'] === 'course' ? ($review->course?->title ?? '—') : ($review->service?->title ?? '—') }}
                                    · {{ $review->created_at?->diffForHumans() }}
                                </p>

                                @if($review->body)
                                    <p class="text-sm text-muted leading-relaxed mt-2 whitespace-pre-line">{{ $review->body }}</p>
                                @endif

                                @if($review->reply)
                                    <div class="mt-3 ps-3 border-s-2 border-primary">
                                        <p class="text-2xs text-subtle mb-0.5">{{ __('ردّك') }}</p>
                                        <p class="text-sm text-muted whitespace-pre-line">{{ $review->reply }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.reviews.moderate', ['type' => $section['type'], 'id' => $review->id]) }}"
                              class="mt-4 pt-3 border-t border-default flex flex-col sm:flex-row gap-2">
                            @csrf
                            @method('PUT')

                            @if(setting('community.allow_reply', true))
                                <x-ui.input name="reply" :placeholder="__('ردّ عام على التقييم (اختياري)')"
                                            maxlength="2000" value="{{ $review->reply }}" class="flex-1" />
                            @endif

                            <div class="flex gap-2 sm:shrink-0">
                                <button type="submit" name="status" value="approved"
                                        class="flex-1 sm:flex-none min-h-11 px-4 rounded-md bg-success text-success-on font-semibold text-sm">{{ __('انشر') }}</button>
                                <button type="submit" name="status" value="rejected"
                                        class="flex-1 sm:flex-none min-h-11 px-4 rounded-md border border-line-strong font-semibold text-sm hover:bg-surface-sunken transition-colors">{{ __('ارفض') }}</button>
                            </div>
                        </form>
                    </li>
                @endforeach
            </ul>

            @if($section['rows']->hasPages())
                <div class="mt-4">
                    <x-ui.pagination :current="$section['rows']->currentPage()" :last="$section['rows']->lastPage()"
                                     :url="request()->fullUrlWithQuery([$section['pageName'] => '']).''" />
                </div>
            @endif
        @endif
    </section>
@endforeach

</x-layouts.admin>
