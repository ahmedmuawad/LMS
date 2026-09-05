<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Core\Access\Ability;
use App\Core\Entitlements\Exceptions\QuotaExceededException;
use App\Modules\Ai\Actions\GenerateQuestions;
use App\Modules\Lms\Models\Taxonomy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * توليد أسئلة من مادة يلصقها المدرّس أو يرفعها.
 *
 * الأسئلة تدخل بنك الأسئلة وحده، ولا تدخل امتحاناً إلا بيده — وهو
 * يقرؤها وهو يختارها.
 */
final class AiQuestionController
{
    public function index(Request $request): View
    {
        $this->authorise($request);

        return view('admin.ai-questions', [
            'pools' => Taxonomy::where('type', 'question_category')->get(),
        ]);
    }

    public function store(Request $request, GenerateQuestions $action): RedirectResponse
    {
        $this->authorise($request);

        $input = $request->validate([
            'material' => ['required_without:file', 'nullable', 'string', 'max:40000'],
            'file' => ['nullable', 'file', 'mimes:txt,md', 'max:2048'],
            'count' => ['required', 'integer', 'min:1', 'max:30'],
            'difficulty' => ['required', 'string', 'in:easy,medium,hard'],
            'pool_id' => ['nullable', 'integer', 'exists:taxonomies,id'],
        ]);

        /*
         | النصّ من الملفّ أو من الحقل.
         |
         | والمقبول نصٌّ صِرف (txt/md): قراءة PDF وWord تحتاج مكتبة
         | تفكّ ملفّاتٍ يرفعها المستخدم — وهي أوسع سطحِ هجومٍ ممّا
         | تستحقّه راحةٌ يحلّها نسخٌ ولصق.
         */
        $material = $request->hasFile('file')
            ? (string) file_get_contents($request->file('file')->getRealPath())
            : (string) ($input['material'] ?? '');

        try {
            $result = $action->handle(
                $material,
                (int) $input['count'],
                (string) $input['difficulty'],
                $input['pool_id'] ?? null,
            );
        } catch (QuotaExceededException $e) {
            return back()->withInput()->withErrors(['material' => $e->forHumans()]);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['material' => $e->getMessage()]);
        }

        return redirect(url('/admin/questions'))->with('status', __(
            'أُضيف :created سؤالاً إلى البنك:skipped. راجعها قبل أن تمتحن بها.',
            [
                'created' => $result['created'],
                'skipped' => $result['skipped'] > 0
                    ? __(' (وتُخطّي :n لم يكتمل)', ['n' => $result['skipped']])
                    : '',
            ],
        ));
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::QUIZZES_MANAGE), 403);

        abort_unless(
            tenant()?->allows('ai_exam_from_pdf') ?? false,
            402,
            __('توليد الأسئلة غير متاح في باقتك.'),
        );
    }
}
