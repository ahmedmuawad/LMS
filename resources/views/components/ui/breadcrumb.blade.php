@props(['items' => []])
<nav aria-label="{{ __('مسار التنقّل') }}" {{ $attributes }}>
    <ol class="flex items-center flex-wrap gap-1.5 text-xs text-muted">
        @foreach($items as $i => $item)
            <li class="flex items-center gap-1.5">
                @if(!empty($item['url']) && $i < count($items) - 1)
                    <a href="{{ $item['url'] }}" class="hover:text-content transition-colors">{{ $item['label'] }}</a>
                @else
                    <span class="text-content font-medium" @if($i === count($items) - 1) aria-current="page" @endif>{{ $item['label'] }}</span>
                @endif
                @if($i < count($items) - 1)<span class="text-subtle" aria-hidden="true">/</span>@endif
            </li>
        @endforeach
    </ol>
</nav>
