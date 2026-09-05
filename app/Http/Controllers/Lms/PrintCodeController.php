<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Core\Access\Ability;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\Lesson;
use App\Modules\Lms\Models\PrintCode;
use App\Modules\Lms\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * رموز QR للمذكرات المطبوعة.
 *
 * المدرّس يُنشئ رمزاً لكل موضع في مذكرته، ويطبع ورقة الرموز،
 * ويلصق كلّاً في مكانه. والطالب يمسح فيفتح الشرح.
 */
final class PrintCodeController
{
    public function index(Request $request): View
    {
        $this->authorise($request);

        return view('admin.print-codes', [
            'codes' => PrintCode::latest('id')->paginate(30),
            'lessons' => Lesson::orderBy('id')->limit(300)->get(),
            'quizzes' => Quiz::orderBy('id')->limit(300)->get(),
            'courses' => Course::orderBy('id')->limit(300)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorise($request);

        $input = $request->validate([
            'label' => ['required', 'string', 'max:180'],
            'target_type' => ['required', 'string', 'in:'.implode(',', array_keys(PrintCode::TARGETS))],
            'target_id' => ['nullable', 'integer'],
            'target_url' => ['nullable', 'url', 'max:2000'],
        ]);

        // الرابط الخارجي يحتاج عنواناً، وما سواه يحتاج هدفاً من المنصة
        if ($input['target_type'] === 'url') {
            abort_if(blank($input['target_url'] ?? null), 422, __('ضع عنوان الرابط.'));
        } else {
            abort_if(blank($input['target_id'] ?? null), 422, __('اختر الهدف.'));
        }

        PrintCode::create([
            'code' => PrintCode::freshCode(),
            'label' => $input['label'],
            'target_type' => $input['target_type'],
            'target_id' => $input['target_id'] ?? null,
            'target_url' => $input['target_url'] ?? null,
            'created_by' => $request->user()?->getKey(),
        ]);

        return back()->with('status', __('أُنشئ الرمز — اطبعه والصقه في مكانه من المذكرة.'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorise($request);

        $code = PrintCode::findOrFail($id);

        /*
         | الهدف يُغيَّر بعد الطباعة — وهو سبب وجود الرمز أصلاً.
         |
         | مدرّسٌ صوّر شرحاً أفضل، أو أعاد ترتيب منهجه، فيحوّل الرمز
         | إلى الجديد بلا أن يُعيد طبع ألف مذكرة.
         */
        $input = $request->validate([
            'label' => ['nullable', 'string', 'max:180'],
            'target_type' => ['nullable', 'string', 'in:'.implode(',', array_keys(PrintCode::TARGETS))],
            'target_id' => ['nullable', 'integer'],
            'target_url' => ['nullable', 'url', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $code->forceFill(array_filter([
            'label' => $input['label'] ?? null,
            'target_type' => $input['target_type'] ?? null,
            'target_id' => $input['target_id'] ?? null,
            'target_url' => $input['target_url'] ?? null,
        ], fn ($value): bool => $value !== null))->save();

        $code->forceFill(['is_active' => $request->boolean('is_active')])->save();

        return back()->with('status', __('حُدّث الرمز.'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->authorise($request);

        PrintCode::findOrFail($id)->delete();

        return back()->with('status', __('حُذف الرمز. وما طُبع منه لن يفتح شيئاً.'));
    }

    /** ورقة الطباعة: الرموز في شبكة تُقصّ وتُلصق. */
    public function sheet(Request $request): View
    {
        $this->authorise($request);

        return view('admin.print-codes-sheet', [
            'codes' => PrintCode::where('is_active', true)->orderBy('id')->get(),
        ]);
    }

    /**
     * مسحُ الرمز — النقطة العامة.
     *
     * ## بلا حراسة هنا
     *
     * الرمز يُحوّل إلى وجهته، والوجهة تحرس نفسها: من ليس مسجّلاً
     * في الكورس يُردّ عندها. وحراستُه هنا تعني أن يمسح الطالب رمزاً
     * فيرى «ممنوع» بلا أن يعرف ما وراءه — والصحيح أن يرى صفحة
     * الكورس فيسجّل.
     */
    public function scan(string $code): RedirectResponse
    {
        $entry = PrintCode::where('code', mb_strtoupper($code))->first();

        abort_if($entry === null || ! $entry->is_active, 404, __('هذا الرمز غير معروف أو أُوقف.'));

        $entry->increment('scans');
        $entry->forceFill(['last_scan_at' => now()])->save();

        $destination = $entry->destination();

        abort_if($destination === null, 404, __('هدف هذا الرمز لم يعد موجوداً.'));

        return redirect()->away($destination);
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::LESSONS_MANAGE), 403);
    }
}
