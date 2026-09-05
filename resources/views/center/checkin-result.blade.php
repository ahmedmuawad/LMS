<x-layouts.app :title="__('تسجيل الحضور')">
{{--
    نتيجةُ المسح في هاتف الطالب.

    وهو واقفٌ عند الباب ينظر إليها ثانيتين: فالجواب كبيرٌ في وسط
    الشاشة، ولا شيء غيره.
--}}
<div class="min-h-screen bg-bg flex flex-col items-center justify-center p-6 text-center">

    <div class="w-20 h-20 rounded-full flex items-center justify-center text-4xl mb-5
                {{ $ok ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger' }}">
        {{ $ok ? '✓' : '!' }}
    </div>

    <h1 class="text-lg font-bold mb-2">{{ $message }}</h1>

    @if($session)
        <p class="text-sm text-muted">
            {{ $session->group?->subject?->name }} · {{ $session->group?->name }}
        </p>
        <p class="text-xs text-subtle mt-1">{{ display_date($session->date, 'l j F') }}</p>
    @else
        {{--
            وسببُ الرفض يُقال، لا يُترك للتخمين.

            «الكود غير صالح» بلا سببٍ تجعل الطالب يعيد المسح خمس
            مرّات ثم يذهب إلى المدرّس — والطابور خلفه.
        --}}
        <p class="text-xs text-muted mt-3 max-w-xs leading-relaxed">
            {{ __('امسح الكود الظاهر على الشاشة الآن، أو اطلب من المدرّس تسجيلك يدوياً.') }}
        </p>
    @endif

    <a href="{{ route('my-classes') }}" class="mt-8 text-sm text-primary underline">
        {{ __('حصصي') }}
    </a>
</div>
</x-layouts.app>
