@props(['content' => [], 'settings' => []])
@php
    $t = fn (string $key): ?string => is_array($content[$key] ?? null)
        ? ($content[$key][app()->getLocale()] ?? $content[$key][config('locales.default', 'ar')] ?? null)
        : ($content[$key] ?? null);

    $services = App\Modules\Services\Models\Service::published()
        ->limit((int) ($content['limit'] ?? 6))->get();
    $columns = (int) ($content['columns'] ?? 3);
@endphp

@if($t('heading'))
    <h2 class="text-2xl font-bold mb-6">{{ $t('heading') }}</h2>
@endif

@if($services->isEmpty())
    <x-ui.empty :title="__('لا خدمات منشورة بعد')">{{ __('ستظهر هنا فور نشر أول خدمة.') }}</x-ui.empty>
@else
    <div @class([
        'grid gap-4 sm:grid-cols-2',
        'lg:grid-cols-3' => $columns === 3,
        'lg:grid-cols-4' => $columns === 4,
    ])>
        @foreach($services as $service)
            <x-services.card :service="$service" />
        @endforeach
    </div>
@endif
