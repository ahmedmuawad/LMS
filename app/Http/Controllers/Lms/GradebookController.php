<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Core\Access\Scope;
use App\Models\User;
use App\Modules\Lms\Actions\BuildGradebook;
use App\Modules\Lms\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * دفتر درجات الكورس.
 *
 * ## لماذا شاشةٌ مستقلّة عن «التصحيح»
 *
 * شاشة التصحيح تسأل: «ما الذي ينتظرني؟» — قائمةُ عملٍ تُفرَغ.
 * والدفتر يسأل: «أين صفّي؟» — صورةٌ تُقرأ. والسؤالان مختلفان،
 * وشاشةٌ تخدمهما معاً لا تخدم أيّهما.
 */
final class GradebookController
{
    public function __construct(private readonly Scope $scope) {}

    public function show(Request $request, string $courseId, BuildGradebook $builder): View
    {
        $course = $this->courseFor($request, $courseId);

        return view('lms.gradebook', [
            'course' => $course,
            ...$builder->handle($course),
        ]);
    }

    /**
     * تصدير CSV — الدفتر يُطبَع ويُرسَل ويُحفَظ.
     *
     * والمدرّس يريده في إكسل لا في متصفّح: يرتّبه ويحسب عليه ويضعه
     * في كشفٍ لإدارته.
     */
    public function export(Request $request, string $courseId, BuildGradebook $builder): Response
    {
        $course = $this->courseFor($request, $courseId);
        $book = $builder->handle($course);

        $head = [__('الطالب'), __('البريد')];

        foreach ($book['columns'] as $column) {
            $head[] = $column['title'].' ('.$this->number($column['max']).')';
        }

        $head[] = __('المجموع');
        $head[] = __('النسبة');

        $rows = [$head];

        foreach ($book['rows'] as $row) {
            $line = [(string) ($row['student']?->name ?? '—'), (string) ($row['student']?->email ?? '')];

            foreach ($book['columns'] as $column) {
                $score = $row['cells'][$column['key']] ?? null;
                $line[] = $score === null ? '' : $this->number((float) $score);
            }

            $line[] = $this->number($row['total']);
            $line[] = $row['percent'].'%';

            $rows[] = $line;
        }

        $csv = "\u{FEFF}";   // BOM: بغيره تُقرأ العربية رموزاً في إكسل

        foreach ($rows as $row) {
            $csv .= implode(',', array_map(
                fn (string $cell): string => '"'.str_replace('"', '""', $cell).'"',
                $row,
            ))."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="gradebook-'.$course->slug.'.csv"',
        ]);
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }

    /** الكورس إن كان كورس صاحب الطلب — وإلا 404. */
    private function courseFor(Request $request, string $courseId): Course
    {
        $user = $request->user();

        return $this->scope
            ->byInstructor(Course::query(), $user instanceof User ? $user : null, 'instructor_id')
            ->findOrFail($courseId);
    }
}
