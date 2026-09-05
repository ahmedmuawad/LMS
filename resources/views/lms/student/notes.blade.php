<x-layouts.student :title="__('ملاحظاتي')" current="notes">

    <x-ui.page-header :title="__('ملاحظاتي')"
                      :subtitle="__('ما كتبته أثناء تعلّمك — مثبَّتُها أولاً.')" />

    @if(session('status'))
        <x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>
    @endif

    {{-- الكتابة أولاً: الشاشة تُفتح غالباً لتدوين شيء لا لقراءة القديم --}}
    <form method="POST" action="{{ route('notes.store') }}" class="surface-card p-4 mb-6 grid gap-3">
        @csrf
        <x-ui.field :label="__('ملاحظة جديدة')" for="note-body" required class="mb-0">
            <x-ui.textarea id="note-body" name="body" rows="3" required
                           maxlength="5000" :placeholder="__('اكتب ما تريد تذكّره…')" />
        </x-ui.field>
        <div>
            <x-ui.button type="submit" size="sm">{{ __('حفظ') }}</x-ui.button>
        </div>
    </form>

    @if($notes->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا ملاحظات بعد')">
                {{ __('اكتب ملاحظتك الأولى أعلاه، أو دوّن أثناء مشاهدة درس فتُحفظ مع دقيقتها.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-3">
            @foreach($notes as $note)
                <div class="surface-card p-4 grid gap-3" x-data="{ editing: false }">

                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            @if($note->lesson || $note->course)
                                <p class="text-2xs text-subtle mb-1.5 truncate">
                                    @if($note->course){{ $note->course->title }}@endif
                                    @if($note->lesson) · {{ $note->lesson->title }}@endif
                                    @if($note->timestampLabel())
                                        · <span class="font-mono tabular">{{ $note->timestampLabel() }}</span>
                                    @endif
                                </p>
                            @endif

                            <p x-show="! editing" class="text-sm leading-relaxed whitespace-pre-line">{{ $note->body }}</p>

                            <form x-show="editing" x-cloak method="POST"
                                  action="{{ route('notes.update', $note->getKey()) }}" class="grid gap-2">
                                @csrf @method('PUT')
                                <x-ui.textarea name="body" rows="3" required maxlength="5000">{{ $note->body }}</x-ui.textarea>
                                <input type="hidden" name="is_pinned" value="{{ $note->is_pinned ? 1 : 0 }}">
                                <div class="flex gap-2">
                                    <x-ui.button type="submit" size="sm">{{ __('حفظ') }}</x-ui.button>
                                    <x-ui.button type="button" size="sm" variant="ghost"
                                                 @click="editing = false">{{ __('إلغاء') }}</x-ui.button>
                                </div>
                            </form>
                        </div>

                        @if($note->is_pinned)
                            <span class="text-primary shrink-0" aria-label="{{ __('مثبَّتة') }}" title="{{ __('مثبَّتة') }}">⚲</span>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-line">
                        <p class="text-2xs text-subtle font-mono tabular flex-1">
                            {{ $note->updated_at?->diffForHumans() }}
                        </p>

                        <button type="button" @click="editing = ! editing"
                                class="tap-link text-2xs text-muted hover:text-content">{{ __('تعديل') }}</button>

                        <form method="POST" action="{{ route('notes.update', $note->getKey()) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="body" value="{{ $note->body }}">
                            <input type="hidden" name="is_pinned" value="{{ $note->is_pinned ? 0 : 1 }}">
                            <button type="submit" class="tap-link text-2xs text-muted hover:text-content">
                                {{ $note->is_pinned ? __('إلغاء التثبيت') : __('تثبيت') }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('notes.destroy', $note->getKey()) }}"
                              onsubmit="return confirm('{{ __('حذف هذه الملاحظة؟') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="tap-link text-2xs text-danger hover:underline">{{ __('حذف') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">{{ $notes->links() }}</div>
    @endif

</x-layouts.student>
