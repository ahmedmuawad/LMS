@props(['content' => [], 'settings' => []])
@php
    $t = fn (string $key): ?string => is_array($content[$key] ?? null)
        ? ($content[$key][app()->getLocale()] ?? $content[$key][config('locales.default', 'ar')] ?? null)
        : ($content[$key] ?? null);

    $form = filled($content['form_key'] ?? null)
        ? App\Modules\Content\Models\Form::where('key', $content['form_key'])->where('is_active', true)->first()
        : null;
@endphp

@if($form)
    <div class="max-w-[42rem] mx-auto">
        @if($t('heading'))
            <h2 class="text-2xl font-bold mb-6 text-center">{{ $t('heading') }}</h2>
        @endif

        <x-content.form :form="$form" />
    </div>
@endif
