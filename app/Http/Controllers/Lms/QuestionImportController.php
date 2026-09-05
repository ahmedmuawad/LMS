<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Core\Access\Ability;
use App\Modules\Lms\Actions\ImportQuestions;
use App\Modules\Lms\Models\Taxonomy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

/** استيراد أسئلة جماعياً من ملفّ CSV. */
final class QuestionImportController
{
    public function index(Request $request): View
    {
        $this->authorise($request);

        return view('admin.questions-import', [
            'pools' => Taxonomy::where('type', 'question_category')->get(),
            'headers' => ImportQuestions::HEADERS,
        ]);
    }

    /** القالب يُنزَّل فيُملأ — أسرع من قراءة شرحٍ عن الأعمدة. */
    public function template(Request $request, ImportQuestions $action): Response
    {
        $this->authorise($request);

        return response($action->template(), 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="questions-template.csv"',
        ]);
    }

    public function store(Request $request, ImportQuestions $action): RedirectResponse
    {
        $this->authorise($request);

        $input = $request->validate([
            'file' => ['required_without:csv', 'nullable', 'file', 'extensions:csv,txt', 'max:4096'],
            'csv' => ['required_without:file', 'nullable', 'string', 'max:2000000'],
            'pool_id' => ['nullable', 'integer', 'exists:taxonomies,id'],
        ], [], ['file' => __('الملفّ'), 'csv' => __('النصّ')]);

        $csv = $request->hasFile('file')
            ? (string) file_get_contents($request->file('file')->getRealPath())
            : (string) ($input['csv'] ?? '');

        try {
            $result = $action->handle($csv, isset($input['pool_id']) ? (int) $input['pool_id'] : null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        /*
         | ما نجح يُقال أولاً، وما سقط بعده مفصّلاً.
         |
         | «استُورد ٩٧ وسقط ٣» أوضح من «تمّ الاستيراد» — والمدرّس
         | يحتاج أن يعرف أنّ عليه إصلاح ثلاثة أسطر لا أن يظنّ الملفّ
         | دخل كاملاً.
         */
        return redirect(url('/admin/questions'))
            ->with('status', __('استُورد :created سؤالاً:skipped.', [
                'created' => $result['created'],
                'skipped' => $result['skipped'] > 0
                    ? __('، وسقط :count', ['count' => $result['skipped']])
                    : '',
            ]))
            ->with('import_errors', $result['errors']);
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::QUIZZES_MANAGE), 403);
    }
}
