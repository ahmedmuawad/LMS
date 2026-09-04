@props(['name', 'label', 'hint' => null, 'value' => [], 'typeField' => 'type'])
@php
    /*
     | القيمة تصل خريطةً من القاعدة (`['a' => 'نصّ']`) وصفوفاً من
     | الجلسة بعد خطأ تحقّق. نوحّدها هنا صفوفاً حتى لا تتفرّع الواجهة.
     */
    $record = (array) ($value['options'] ?? []);
    $correct = array_map('strval', (array) ($value['correct'] ?? []));

    $rows = collect(old($name))->filter(fn ($r) => is_array($r))->values();

    if ($rows->isEmpty()) {
        $rows = collect($record)->map(fn ($text, $key): array => [
            'key' => (string) $key,
            'text' => (string) $text,
            'correct' => in_array((string) $key, $correct, true) ? '1' : '',
        ])->values();
    }

    if ($rows->isEmpty()) {
        $rows = collect([['key' => 'a', 'text' => '', 'correct' => '1'], ['key' => 'b', 'text' => '', 'correct' => '']]);
    }
@endphp

<fieldset class="mb-4"
          x-data="{
              rows: {{ Js::from($rows->values()) }},
              type: (document.querySelector('[name=&quot;{{ $typeField }}&quot;]')?.value) || 'single_choice',
              get single() { return this.type !== 'multiple_choice'; },
              get shows() { return ['single_choice','multiple_choice','true_false','dropdown'].includes(this.type); },
              key(i) { return 'abcdefghijklmnopqrstuvwxyz'[i % 26] + (i >= 26 ? Math.floor(i / 26) : ''); },
              add() { this.rows.push({ key: this.key(this.rows.length), text: '', correct: '' }); },
              remove(i) { if (this.rows.length > 2) this.rows.splice(i, 1); },
              pick(i) {
                  // الاختيار الواحد: اختيار صواب يُلغي ما قبله — وإلا حُفظ سؤال بإجابتين صحيحتين وهو مستحيل
                  if (this.single) this.rows.forEach((r, j) => r.correct = j === i ? '1' : '');
                  else this.rows[i].correct = this.rows[i].correct ? '' : '1';
              },
              init() {
                  const source = document.querySelector('[name=&quot;{{ $typeField }}&quot;]');
                  source?.addEventListener('change', () => {
                      this.type = source.value;
                      if (this.type === 'true_false') {
                          this.rows = [
                              { key: 'a', text: '{{ __('صح') }}', correct: '1' },
                              { key: 'b', text: '{{ __('خطأ') }}', correct: '' },
                          ];
                      }
                  });
              },
          }"
          x-show="shows" x-cloak>
    <legend class="text-sm font-semibold mb-2">{{ $label }}</legend>

    <div class="grid gap-2">
        <template x-for="(row, i) in rows" :key="i">
            <div class="flex items-start gap-2">
                {{-- زرّ الصواب هدف لمس كامل: علامة صغيرة تُخطئ بالإصبع --}}
                <button type="button" @click="pick(i)"
                        class="size-11 shrink-0 grid place-items-center rounded-md border transition-colors"
                        :class="row.correct
                            ? 'bg-success-subtle border-success text-success'
                            : 'border-line-strong text-subtle hover:bg-surface-sunken'"
                        :aria-pressed="row.correct ? 'true' : 'false'"
                        :aria-label="'{{ __('الإجابة الصحيحة') }} ' + (i + 1)">
                    <span aria-hidden="true" x-text="row.correct ? '✓' : ''"></span>
                </button>

                <input type="hidden" :name="`{{ $name }}[${i}][key]`" :value="row.key">
                <input type="hidden" :name="`{{ $name }}[${i}][correct]`" :value="row.correct">

                {{-- الخيار قد يكون معادلة كاملة، فيقبل لوحة الرموز كنصّ السؤال --}}
                <input type="text" x-model="row.text" :name="`{{ $name }}[${i}][text]`"
                       data-math-input :data-math-label="'{{ __('الخيار') }} ' + (i + 1)"
                       class="flex-1 min-w-0 h-11 px-3 rounded-md border border-line-strong bg-surface text-sm
                              focus:outline-none focus:ring-2 focus:ring-primary"
                       :placeholder="'{{ __('الخيار') }} ' + (i + 1)">

                <button type="button" @click="remove(i)" x-show="rows.length > 2"
                        class="size-11 shrink-0 grid place-items-center rounded-md text-muted hover:bg-danger-subtle hover:text-danger transition-colors"
                        :aria-label="'{{ __('احذف الخيار') }} ' + (i + 1)">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
        </template>
    </div>

    <button type="button" @click="add()"
            class="mt-2 inline-flex items-center gap-1.5 min-h-11 px-3 rounded-md text-sm font-medium
                   text-primary hover:bg-primary-subtle transition-colors">
        <span aria-hidden="true">＋</span> {{ __('أضف خياراً') }}
    </button>

    <p class="text-xs text-subtle mt-2">
        {{ $hint ?? __('اضغط المربّع بجانب الخيار الصحيح. والخيار قد يكون معادلة — استعمل لوحة الرموز أعلاه.') }}
    </p>
    @error($name)<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
</fieldset>
