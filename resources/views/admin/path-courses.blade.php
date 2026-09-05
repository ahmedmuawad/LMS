<x-layouts.admin :title="__('كورسات المسار')" current="paths">
<div class="max-w-[900px]">

    <x-ui.page-header :title="__('كورسات: :path', ['path' => $path->title])"
                      :subtitle="__('الترتيب هو المسار — والطالب يمشي فيه كما رتّبتَه.')"
                      :back="url('/admin/paths')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>@endif

    <x-ui.card :title="__('أضف كورساً')" class="mb-6">
        @if($available->isEmpty())
            <p class="text-muted text-sm">{{ __('كل كورساتك في هذا المسار.') }}</p>
        @else
            <form method="POST" action="{{ url('/admin/paths/'.$path->getKey().'/courses') }}"
                  class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-end">
                @csrf

                <x-ui.field :label="__('الكورس')" for="course_id" class="mb-0">
                    <select id="course_id" name="course_id" required
                            class="w-full min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
                        @foreach($available as $course)
                            <option value="{{ $course->id }}">{{ $course->title }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <label class="flex items-center gap-2 text-sm min-h-11">
                    <input type="checkbox" name="is_required" value="1" checked
                           class="size-5 accent-[var(--color-primary)] rounded-sm">
                    {{ __('إجباري') }}
                </label>

                <x-ui.button type="submit">{{ __('أضف') }}</x-ui.button>
            </form>

            <p class="text-2xs text-subtle mt-3">
                {{ __('«إجباري» يعني أنه يُحسب في التقدّم ويُوقف ما بعده في المسار المتسلسل. وغير الإجباري إثراءٌ لا يُعطّل.') }}
            </p>
        @endif
    </x-ui.card>

    <x-ui.card :title="__('ترتيب المسار')">
        @if($path->items->isEmpty())
            <x-ui.empty :title="__('لا كورسات بعد')">
                {{ __('المسار بلا كورسات عنوانٌ فارغ — أضف أوّل كورسٍ يبدأ منه الطالب.') }}
            </x-ui.empty>
        @else
            <div class="grid gap-1.5">
                @foreach($path->items as $index => $item)
                    <div class="flex flex-wrap items-center gap-3 py-2 border-b border-line last:border-0">
                        <span class="shrink-0 w-7 h-7 grid place-items-center rounded-full bg-surface-sunken
                                     font-mono text-xs tabular">{{ $index + 1 }}</span>

                        <span class="min-w-0 flex-1 text-sm truncate">
                            {{ $item->course?->title ?? __('كورس محذوف') }}
                            @unless($item->is_required)
                                <span class="text-2xs text-subtle">· {{ __('اختياري') }}</span>
                            @endunless
                        </span>

                        <div class="flex gap-1">
                            @if(! $loop->first)
                                <form method="POST" action="{{ url('/admin/paths/'.$path->getKey().'/courses/'.$item->id.'/move') }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="up">
                                    <x-ui.button size="sm" variant="ghost" type="submit"
                                                 :aria-label="__('أعلى')">↑</x-ui.button>
                                </form>
                            @endif

                            @if(! $loop->last)
                                <form method="POST" action="{{ url('/admin/paths/'.$path->getKey().'/courses/'.$item->id.'/move') }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="down">
                                    <x-ui.button size="sm" variant="ghost" type="submit"
                                                 :aria-label="__('أسفل')">↓</x-ui.button>
                                </form>
                            @endif

                            <form method="POST" action="{{ url('/admin/paths/'.$path->getKey().'/courses/'.$item->id) }}">
                                @csrf @method('DELETE')
                                <x-ui.button size="sm" variant="danger" type="submit">{{ __('أزل') }}</x-ui.button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>

</div>
</x-layouts.admin>
