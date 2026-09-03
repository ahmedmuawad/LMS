<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Modules\Lms\Actions\SubmitAssignment;
use App\Modules\Lms\Models\Assignment;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseItem;
use App\Modules\Lms\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class AssignmentController
{
    public function submit(Request $request, string $slug, string $itemId, SubmitAssignment $action): RedirectResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        $enrollment = Enrollment::where('user_id', $request->user()?->getKey())
            ->where('course_id', $course->getKey())
            ->first();

        abort_if($enrollment === null, 403, __('لست مسجّلاً في هذا الكورس.'));
        abort_unless($enrollment->hasAccess(), 403, __('انتهت مدة وصولك إلى هذا الكورس.'));

        $item = CourseItem::where('course_id', $course->getKey())
            ->where('itemable_type', Assignment::class)
            ->findOrFail($itemId);

        /** @var Assignment $assignment */
        $assignment = $item->itemable;

        $input = $request->validate([
            'content' => ['nullable', 'string', 'max:20000'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => [
                'file',
                'max:'.((int) $assignment->max_marks > 0 ? (int) $assignment->max_file_mb * 1024 : 25600),
                ...(filled($assignment->allowed_extensions)
                    ? ['mimes:'.implode(',', $assignment->allowed_extensions)]
                    : []),
            ],
        ]);

        $files = [];

        foreach ($request->file('files', []) as $file) {
            $files[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $file->store('assignments', 'local'),
                'size' => $file->getSize(),
            ];
        }

        try {
            $action->handle($enrollment, $assignment, $input['content'] ?? null, $files);
        } catch (RuntimeException $e) {
            return back()->withErrors(['assignment' => $e->getMessage()])->withInput();
        }

        return back()->with('status', __('سُلّم واجبك. ستصلك النتيجة بعد التصحيح.'));
    }
}
