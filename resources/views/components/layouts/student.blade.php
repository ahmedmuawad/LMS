@props(['title' => null, 'current' => null])
@php
    $me = auth()->user();
    $groups = app(App\Core\Support\StudentNavigation::class)->groups();

    $unread = $me === null ? 0 : App\Core\Notifications\Models\Notification::where('user_id', $me->getKey())
        ->whereNull('read_at')->count();
@endphp

<x-layouts.app :title="$title">
    {{-- ترويسة الموقع تبقى فوق: الطالب يتنقّل بين لوحته والكتالوج كثيراً --}}
    <x-site.header />

    <div class="max-w-[1200px] mx-auto px-4 sm:px-6 py-6" x-data="{ nav: false }">
        <div class="grid gap-6 lg:grid-cols-[236px_minmax(0,1fr)] items-start">

            {{--
                القائمة عمودٌ ثابت على الشاشة الواسعة، ودرجٌ يُفتح على
                الضيّقة. عرضُ اثني عشر رابطاً فوق المحتوى على الهاتف
                يدفع ما جاء الطالب لأجله خارج الشاشة.
            --}}
            <button type="button" @click="nav = ! nav"
                    class="lg:hidden w-full flex items-center justify-between gap-3 min-h-12 px-4 rounded-lg
                           border border-line-strong bg-surface text-sm font-semibold"
                    :aria-expanded="nav ? 'true' : 'false'" aria-controls="student-nav">
                <span class="flex items-center gap-2">
                    <span aria-hidden="true">☰</span>{{ __('لوحتي') }}
                </span>
                <span class="text-muted" aria-hidden="true" x-text="nav ? '▴' : '▾'"></span>
            </button>

            {{--
                الإظهار بـCSS لا بـ`window.innerWidth` في x-show: تلك
                تُقرأ مرة واحدة عند التحميل، فمن فتح الصفحة ضيّقاً ثم
                وسّع نافذته تبقى قائمته مخفيّة حتى يُعيد التحميل.
            --}}
            <nav id="student-nav" :class="nav ? '!grid' : ''"
                 class="hidden lg:grid lg:sticky lg:top-20 gap-4 rounded-lg border border-line bg-surface p-3 lg:border-0 lg:bg-transparent lg:p-0"
                 aria-label="{{ __('قائمة لوحة الطالب') }}">

                @foreach($groups as $group)
                    <div>
                        <p class="text-2xs font-semibold text-subtle px-2 mb-1.5">{{ $group['label'] }}</p>
                        <ul class="grid gap-0.5">
                            @foreach($group['items'] as $item)
                                @php $active = $current === $item['key']; @endphp
                                <li>
                                    <a href="{{ $item['url'] }}"
                                       @if($active) aria-current="page" @endif
                                       @class([
                                           'flex items-center gap-2.5 min-h-11 px-2.5 rounded-md text-sm transition-colors',
                                           'bg-primary-subtle text-primary font-semibold' => $active,
                                           'text-muted hover:bg-surface-sunken hover:text-content' => ! $active,
                                       ])>
                                        <span class="w-4 text-center shrink-0" aria-hidden="true">{{ $item['icon'] }}</span>
                                        <span class="truncate">{{ $item['label'] }}</span>

                                        @if($item['key'] === 'notifications' && $unread > 0)
                                            <span class="ms-auto min-w-[20px] h-5 px-1.5 rounded-full bg-danger text-danger-on
                                                         text-[10px] font-bold grid place-items-center font-mono shrink-0">{{ min($unread, 99) }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>

            <main id="main" class="min-w-0">{{ $slot }}</main>
        </div>
    </div>

    <x-site.footer />
</x-layouts.app>
