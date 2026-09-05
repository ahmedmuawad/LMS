<x-layouts.admin :title="__('فصول الفيديو')" current="lessons">
<div class="max-w-[820px]">

    <x-ui.page-header :title="__('فصول ونصّ: :lesson', ['lesson' => $lesson->title])"
                      :subtitle="__('محاضرةُ ساعةٍ بلا فصول شريطٌ أسود — والفصول تجعلها قائمةً يضغط فيها الطالب سطراً.')"
                      :back="url('/admin/lessons/'.$lesson->getKey().'/edit')">
        <x-slot:actions>
            <x-ui.button size="sm" variant="ghost"
                         :href="url('/admin/lessons/'.$lesson->getKey().'/moments')">
                {{ __('نقاط التفاعل') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('status'))<x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>@endif
    @error('at')<x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>@enderror

    <x-ui.card :title="__('أضف فصلاً')" class="mb-6">
        <form method="POST" action="{{ url('/admin/lessons/'.$lesson->getKey().'/chapters') }}"
              class="grid gap-3 sm:grid-cols-[120px_minmax(0,1fr)_auto] sm:items-end">
            @csrf

            <x-ui.field :label="__('التوقيت')" for="at" required class="mb-0">
                <x-ui.input id="at" name="at" required dir="ltr" placeholder="5:30" maxlength="12" />
            </x-ui.field>

            <x-ui.field :label="__('عنوان الفصل')" for="title" required class="mb-0">
                <x-ui.input id="title" name="title" required maxlength="180"
                            :placeholder="__('حلّ المسألة الثالثة')" />
            </x-ui.field>

            <x-ui.button type="submit">{{ __('أضف') }}</x-ui.button>
        </form>

        <p class="text-2xs text-subtle mt-3">
            {{ __('اكتب التوقيت كما تراه في مشغّلك: 5:30 أو 1:05:30 — أو بالثواني إن شئت.') }}
        </p>
    </x-ui.card>

    <x-ui.card :title="__('الفصول')" class="mb-6">
        @if($chapters->isEmpty())
            <x-ui.empty :title="__('لا فصول بعد')">
                {{ __('أضف فصلاً عند كل انتقالٍ في الشرح — أنفع ما يكون في المراجعة قبل الامتحان.') }}
            </x-ui.empty>
        @else
            <div class="grid gap-1.5">
                @foreach($chapters as $chapter)
                    <div class="flex flex-wrap items-center gap-3 py-2 border-b border-line last:border-0">
                        <span class="font-mono text-xs tabular text-primary shrink-0">{{ $chapter->timeLabel() }}</span>
                        <span class="min-w-0 flex-1 text-sm truncate">{{ $chapter->title }}</span>

                        <form method="POST" action="{{ url('/admin/lessons/'.$lesson->getKey().'/chapters/'.$chapter->id) }}">
                            @csrf @method('DELETE')
                            <x-ui.button size="sm" variant="danger" type="submit">{{ __('حذف') }}</x-ui.button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    <x-ui.card :title="__('النصّ المكتوب')">
        <p class="text-muted text-sm leading-relaxed mb-4">
            {{ __('يقرؤه من لا يستطيع تشغيل الصوت، ويبحث فيه من يريد موضعاً بعينه. والسطر الذي يبدأ بتوقيت يصير قابلاً للضغط — يقفز بالفيديو إليه.') }}
        </p>

        <form method="POST" action="{{ url('/admin/lessons/'.$lesson->getKey().'/transcript') }}" class="grid gap-4">
            @csrf @method('PUT')

            <x-ui.field :label="__('النصّ')" for="transcript" class="mb-0"
                        :hint="__('مثال: «0:45 نبدأ بالمعادلة الأولى» — أو فقراتٍ عادية بلا توقيتات.')">
                <x-ui.textarea id="transcript" name="transcript" rows="14">{{ old('transcript', $transcript) }}</x-ui.textarea>
            </x-ui.field>

            <div><x-ui.button type="submit">{{ __('احفظ النصّ') }}</x-ui.button></div>
        </form>
    </x-ui.card>

</div>
</x-layouts.admin>
