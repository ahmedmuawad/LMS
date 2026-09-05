<x-layouts.app :title="__('المسارات')">
<x-site.header />

<main id="main" class="max-w-[1000px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.page-header :title="__('المسارات')"
                      :subtitle="__('رحلةٌ مرتّبة عبر كورسات — تعرف من أين تبدأ وإلى أين تصل.')" />

    @if($paths->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا مسارات بعد')">
                {{ __('يظهر هنا كل مسارٍ يجمع كورسات بترتيبٍ يوصلك إلى هدف.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($paths as $path)
                <a href="{{ url('/paths/'.$path->slug) }}"
                   class="surface-card p-5 block hover:border-primary transition-colors tap-link">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <h3 class="font-bold min-w-0 truncate">{{ $path->title }}</h3>

                        @if($mine->has($path->id))
                            <x-ui.badge tone="success" class="shrink-0">{{ __('ملتحق') }}</x-ui.badge>
                        @endif
                    </div>

                    @if($path->description)
                        <p class="text-sm text-muted leading-relaxed line-clamp-2 mb-3">{{ $path->description }}</p>
                    @endif

                    <p class="text-2xs text-subtle font-mono tabular">
                        {{ trans_choice('كورس|كورسان|:count كورسات|:count كورساً', $path->items_count, ['count' => $path->items_count]) }}
                        @if($path->is_sequential) · {{ __('بترتيب') }} @endif
                    </p>
                </a>
            @endforeach
        </div>
    @endif

</main>

<x-site.footer />
</x-layouts.app>
