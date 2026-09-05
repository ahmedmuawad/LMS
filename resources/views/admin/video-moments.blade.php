<x-layouts.admin :title="__('نقاط التفاعل')" current="lessons">
<div class="max-w-[900px]">

    <x-ui.page-header :title="__('نقاط التفاعل: :lesson', ['lesson' => $lesson->title])"
                      :subtitle="__('سؤالٌ في منتصف الفيديو يكشف الفهم في ثانيته، لا في امتحان آخر الوحدة.')"
                      :back="url('/admin/lessons/'.$lesson->getKey().'/edit')">
        <x-slot:actions>
            <x-ui.button size="sm" variant="ghost"
                         :href="url('/admin/lessons/'.$lesson->getKey().'/chapters')">
                {{ __('الفصول والنصّ') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('status'))<x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>@endif
    @error('question_id')<x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>@enderror
    @error('body')<x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>@enderror

    <x-ui.card :title="__('إضافة نقطة')" class="mb-6">
        <form method="POST" action="{{ url('/admin/lessons/'.$lesson->getKey().'/moments') }}"
              class="grid gap-4" x-data="{ kind: 'question' }">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field :label="__('الثانية')" for="at" required
                            :hint="__('الثانية التي يتوقّف عندها الفيديو.')" class="mb-0">
                    <x-ui.input id="at" name="at_second" type="number" min="0" max="86400" required value="0" />
                </x-ui.field>

                <x-ui.field :label="__('النوع')" for="kind" class="mb-0">
                    <select id="kind" name="kind" x-model="kind"
                            class="w-full min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
                        @foreach(App\Modules\Lms\Models\VideoMoment::KINDS as $value => $label)
                            <option value="{{ $value }}">{{ __($label) }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>

            <div x-show="kind === 'question'">
                <x-ui.field :label="__('السؤال')" for="q" class="mb-0"
                            :hint="__('من بنك الأسئلة — يُشار إليه ولا يُنسَخ، فتعديله هناك يظهر هنا.')">
                    <select id="q" name="question_id"
                            class="w-full min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
                        <option value="">{{ __('— اختر —') }}</option>
                        @foreach($questions as $question)
                            <option value="{{ $question->id }}">{{ \Illuminate\Support\Str::limit(strip_tags((string) $question->body), 70) }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>

            <div x-show="kind === 'note'" x-cloak>
                <x-ui.field :label="__('نصّ الملاحظة')" for="body" class="mb-0">
                    <x-ui.textarea id="body" name="body" rows="2" maxlength="2000" />
                </x-ui.field>
            </div>

            <div x-show="kind === 'link'" x-cloak>
                <x-ui.field :label="__('الرابط')" for="url" class="mb-0">
                    <x-ui.input id="url" name="url" type="url" placeholder="https://…" />
                </x-ui.field>
            </div>

            <label class="flex items-start gap-2.5 text-sm">
                <input type="checkbox" name="is_required" value="1" class="mt-1 accent-[var(--sem-primary)]">
                <span>
                    <span class="block font-semibold">{{ __('إلزامية') }}</span>
                    {{-- الرجوع لا يُمنع أبداً: من لم يفهم يعيد --}}
                    <span class="block text-xs text-muted leading-relaxed mt-0.5">
                        {{ __('يُمنع التقديم حتى يُجاب. والرجوع مسموح دائماً — منعُه يحوّل التفاعل إلى عقوبة.') }}
                    </span>
                </span>
            </label>

            <div><x-ui.button type="submit">{{ __('أضف') }}</x-ui.button></div>
        </form>
    </x-ui.card>

    @if($moments->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا نقاط تفاعل')">
                {{ __('أضف سؤالاً عند الدقيقة التي تشرح فيها أصعب فكرة — هناك يظهر من فهم ومن لم يفهم.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-2">
            @foreach($moments as $moment)
                @php
                    $answered = $moment->responses->count();
                    $right = $moment->responses->where('is_correct', true)->count();
                @endphp
                <div class="surface-card p-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <span class="font-mono text-sm tabular shrink-0 px-2.5 py-1 rounded bg-surface-sunken">{{ $moment->atLabel() }}</span>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">
                            {{ __(App\Modules\Lms\Models\VideoMoment::KINDS[$moment->kind] ?? $moment->kind) }}
                            @if($moment->is_required)
                                <span class="text-2xs text-warning font-normal">· {{ __('إلزامية') }}</span>
                            @endif
                        </p>
                        <p class="text-xs text-muted mt-0.5 line-clamp-2">
                            {{ \Illuminate\Support\Str::limit(strip_tags((string) ($moment->question?->body ?? $moment->body ?? $moment->url)), 110) }}
                        </p>

                        @if($answered > 0)
                            {{-- النسبة هي الفائدة: أين وقف الفهم --}}
                            <p class="text-2xs text-subtle font-mono tabular mt-1">
                                {{ __(':right صحيحة من :n', ['right' => $right, 'n' => $answered]) }}
                                ({{ (int) round($right / $answered * 100) }}%)
                            </p>
                        @endif
                    </div>

                    <form method="POST" action="{{ url('/admin/lessons/'.$lesson->getKey().'/moments/'.$moment->id) }}"
                          onsubmit="return confirm('{{ __('حذف هذه النقطة؟') }}')">
                        @csrf @method('DELETE')
                        <x-ui.button type="submit" size="sm" variant="danger">{{ __('حذف') }}</x-ui.button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

</div>
</x-layouts.admin>
