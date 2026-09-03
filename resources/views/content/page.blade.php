@php $meta = app(App\Core\Seo\Seo::class)->forModel($page); @endphp
<x-layouts.app :title="$page->title" :meta="$meta">
<x-site.header />

<main id="main">
    @unless($page->isLive())
        <div class="max-w-[1200px] mx-auto px-4 sm:px-6 pt-6">
            <x-ui.alert tone="warning" :title="__('معاينة مسودّة')">
                {{ __('هذه الصفحة غير منشورة — لا يراها الزوّار.') }}
            </x-ui.alert>
        </div>
    @endunless

    @if(blank($page->blocks))
        <div class="max-w-[760px] mx-auto px-4 sm:px-6 py-16 text-center">
            <h1 class="text-3xl font-bold mb-3">{{ $page->title }}</h1>
            <p class="text-muted">{{ __('هذه الصفحة قيد الإعداد.') }}</p>
        </div>
    @else
        <x-blocks.renderer :blocks="$page->blocks" />
    @endif
</main>

<x-site.footer />
</x-layouts.app>
