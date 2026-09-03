@props(['blocks' => []])
@php $registry = app(App\Modules\Content\Blocks\BlockRegistry::class); @endphp

@foreach($blocks as $block)
    @php
        $type = $block['type'] ?? null;
        $content = $block['content'] ?? [];
        $settings = $block['settings'] ?? [];
    @endphp

    @if($type && $registry->has($type))
        <section @class([
            'py-10 sm:py-14',
            'bg-surface-sunken' => ($settings['background'] ?? '') === 'sunken',
            'bg-primary-subtle' => ($settings['background'] ?? '') === 'primary',
        ]) @isset($settings['anchor']) id="{{ $settings['anchor'] }}" @endisset>
            <div @class([
                'mx-auto px-4 sm:px-6',
                'max-w-[1200px]' => ($settings['width'] ?? 'wide') !== 'narrow',
                'max-w-[760px]' => ($settings['width'] ?? '') === 'narrow',
            ])>
                <x-dynamic-component :component="'blocks.'.$type" :content="$content" :settings="$settings" />
            </div>
        </section>
    @endif
@endforeach
