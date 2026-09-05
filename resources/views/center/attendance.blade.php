@php
    use App\Modules\Center\Models\Attendance;
    $tones = ['present' => 'success', 'absent' => 'danger', 'late' => 'warning', 'excused' => 'info', 'online' => 'primary'];

    /*
     | أصناف الحالة المحدَّدة مكتوبةً بحروفها كاملة.
     |
     | كانت تُركَّب: `'peer-checked:bg-'.$tone.'-subtle'`. وTailwind
     | لا يولّد إلا ما يجده نصّاً حرفياً في الملفات، فلم تُولَّد أيٌّ
     | منها — والراديو `sr-only` مخفيّ. فكان المدرّس ينقر «غائب»
     | فلا يتغيّر شيء أمامه، ويظنّ الشاشة معطّلة وهي تسجّل اختياره.
     |
     | ولذلك تُكتب هنا صريحةً، ولا تُركَّب من متغيّر أبداً.
     */
    $checkedClasses = [
        'present' => 'peer-checked:bg-success-subtle peer-checked:text-success peer-checked:border-success',
        'absent' => 'peer-checked:bg-danger-subtle peer-checked:text-danger peer-checked:border-danger',
        'late' => 'peer-checked:bg-warning-subtle peer-checked:text-warning peer-checked:border-warning',
        'excused' => 'peer-checked:bg-info-subtle peer-checked:text-info peer-checked:border-info',
        'online' => 'peer-checked:bg-primary-subtle peer-checked:text-primary peer-checked:border-primary',
    ];
@endphp

<x-layouts.admin :title="__('حضور :group', ['group' => $session->group?->name])" current="attendance">
<div class="max-w-[1000px]"
     x-data="attendanceSheet({
        url: @js(url('/admin/attendance/'.$session->id.'/mark')),
        token: @js(csrf_token()),
     })">

    <x-ui.page-header :title="$session->group?->name"
                      :subtitle="$session->date?->translatedFormat('l j F').' · '.$session->timeLabel().' · '.($session->room?->name ?? '—')"
                      :back="url('/admin/attendance?date='.$session->date?->toDateString())">
        <x-slot:actions>
            @if($session->attendanceTaken())
                <x-ui.badge tone="success">{{ __('سُجّل') }}</x-ui.badge>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{--
        الكشف أولاً: المدرّس يعرف طلابه بأسمائهم، ويعلّم الغائب بنقرة.
        أمّا قارئ الكارنيه فلمن عنده كارنيهات وبوابة — يُطوى تحت الكشف
        ولا يتصدّره، فمن لا يستعمله لا يراه.
    --}}
    <form method="POST" action="{{ url('/admin/attendance/'.$session->id) }}">
        @csrf

        <x-ui.card :title="__('كشف الحضور')"
                   :subtitle="trans_choice('{0} لا طلاب|{1} طالب واحد|{2} طالبان|[3,10] :count طلاب|[11,*] :count طالباً', $students->count(), ['count' => $students->count()]).' · '.__('الافتراضي حاضر — علّم الغائبين وحدهم ثم احفظ.')"
                   :padding="false">
            @if($students->isEmpty())
                <div class="p-5">
                    <x-ui.empty :title="__('لا طلاب في هذه المجموعة')">
                        {{ __('سجّل طلاباً في المجموعة أولاً.') }}
                    </x-ui.empty>
                </div>
            @else
                @php $open = App\Modules\Center\Actions\TakeAttendance::isOpenFor($session); @endphp

                {{--
                    الكشف مقفول قبل موعده.
                    عرضُ أزرارٍ تُرفض عند الحفظ يجعل المدرّس يعلّم كشفاً
                    كاملاً ثم يخسره — والمنع يُقال قبل العمل لا بعده.
                --}}
                @unless($open)
                    <div class="p-4">
                        <x-ui.alert tone="info" :title="__('لم تبدأ هذه الحصة بعد')">
                            {{ __('يُفتح كشف الحضور قبل الموعد بنصف ساعة. موعدها: :when.', [
                                'when' => $session->date?->copy()->setTimeFromTimeString((string) $session->starts_at)?->translatedFormat('l j F · g:i a'),
                            ]) }}
                        </x-ui.alert>
                    </div>
                @endunless

                <ul @class(['divide-y divide-[var(--color-line)]', 'opacity-50 pointer-events-none' => ! $open])>
                    @foreach($students as $student)
                        @php $current = $existing[$student->id] ?? 'present'; @endphp
                        <li class="p-3 sm:p-4 flex flex-wrap items-center gap-3"
                            :class="marked[{{ $student->id }}] ? 'bg-success-subtle' : ''">
                            <x-ui.avatar :name="$student->name()" size="sm" class="shrink-0" />

                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium truncate">{{ $student->name() }}</span>
                                <span class="block text-2xs text-subtle font-mono">{{ $student->code }}</span>
                            </span>

                            {{-- خمسة أزرار لا تسع 320px بجوار الاسم؛ تنزل سطراً كاملاً --}}
                            <div class="flex flex-wrap gap-1 w-full sm:w-auto sm:shrink-0 min-w-0" role="radiogroup"
                                 aria-label="{{ __('حالة :name', ['name' => $student->name()]) }}">
                                @foreach(Attendance::STATUSES as $value => $label)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="status[{{ $student->id }}]" value="{{ $value }}"
                                               @checked($current === $value) class="peer sr-only">
                                        <span class="block min-h-11 px-3 py-2 rounded-md text-xs font-semibold
                                                     border border-line-strong text-muted select-none
                                                     transition-colors hover:bg-surface-sunken
                                                     grid place-items-center {{ $checkedClasses[$value] }}">{{ __($label) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <div class="sticky bottom-0 -mx-4 sm:-mx-6 mt-4 px-4 sm:px-6 py-3 bg-surface/95 backdrop-blur border-t border-line
                    flex flex-wrap items-center gap-3">
            <x-ui.button type="submit" size="lg" :disabled="! $open">{{ __('حفظ الكشف') }}</x-ui.button>
            <p class="text-2xs text-subtle">
                {{ __('سيُخطَر أولياء أمور الغائبين إن كان الإشعار مفعّلاً.') }}
            </p>
        </div>
    </form>

    @if(in_array('code', (array) setting('center.attendance_methods', []), true) || in_array('qr', (array) setting('center.attendance_methods', []), true))
        <details class="mt-4 group">
            <summary class="cursor-pointer text-sm font-semibold text-muted hover:text-content py-2 min-h-11 flex items-center gap-2 select-none">
                <span class="transition-transform group-open:rotate-90" aria-hidden="true">›</span>
                {{ __('تسجيل بالكارنيه أو بالكود') }}
            </summary>
            <x-ui.card :subtitle="__('امسح الكارنيه أو اكتب كود الطالب واضغط Enter — يُعلَّم في الكشف أعلاه.')" class="mt-2">
                <form @submit.prevent="scan()" class="flex items-end gap-2">
                    <x-ui.field :label="__('كود الطالب')" for="scan" class="mb-0 flex-1">
                        <x-ui.input id="scan" x-model="code" x-ref="scan" autocomplete="off"
                                    class="font-mono text-lg tracking-wider" placeholder="ST00001" />
                    </x-ui.field>
                    <x-ui.button type="submit" size="lg" class="shrink-0">{{ __('تسجيل') }}</x-ui.button>
                </form>

                <p x-show="message" x-cloak class="mt-3 text-sm rounded-md px-3 py-2"
                   :class="ok ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'"
                   x-text="message" role="status" aria-live="polite"></p>
            </x-ui.card>
        </details>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('attendanceSheet', (config) => ({
            code: '',
            message: '',
            ok: false,
            marked: {},
            async scan() {
                if (! this.code.trim()) return;
                try {
                    const response = await fetch(config.url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.token, 'Accept': 'application/json' },
                        body: JSON.stringify({ code: this.code, method: 'qr' }),
                    });
                    const data = await response.json();
                    this.ok = data.ok === true;
                    this.message = this.ok
                        ? `${data.name} — ${data.status === 'late' ? 'متأخر ' + data.late + ' دقيقة' : 'حاضر'}`
                        : data.message;
                    if (this.ok) this.marked[data.student_id] = true;
                } catch (e) {
                    this.ok = false;
                    this.message = 'تعذّر الاتصال — أعد المحاولة.';
                }
                this.code = '';
                this.$refs.scan.focus();
            },
        }));
    });
</script>
@endpush
</x-layouts.admin>
