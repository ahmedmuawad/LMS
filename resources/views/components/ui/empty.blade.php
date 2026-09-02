@props(['icon' => '＋', 'title', 'tone' => 'primary'])
<div {{ $attributes->merge(['class' => 'text-center px-5 py-10 rounded-lg border border-dashed border-line-strong bg-surface']) }}>
    <div class="size-12 rounded-lg grid place-items-center mx-auto mb-3 text-xl bg-{{ $tone }}-subtle text-{{ $tone }}" aria-hidden="true">{{ $icon }}</div>
    <h4 class="text-[15px] font-bold mb-1">{{ $title }}</h4>
    <p class="text-muted text-sm max-w-[38ch] mx-auto mb-4">{{ $slot }}</p>
    @isset($action)<div class="flex justify-center">{{ $action }}</div>@endisset
</div>
