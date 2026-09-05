<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Core\Entitlements\Exceptions\QuotaExceededException;
use App\Models\User;
use App\Modules\Ai\Actions\AnswerStudent;
use App\Modules\Lms\LessonAccess;
use App\Modules\Lms\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * المساعد الدراسي — يجيب الطالب عن درسه.
 *
 * ## والحراسة مضاعفة
 *
 * التسجيل يُفحص كأيّ شيءٍ في الدرس، والحدّ يُفحص في `AiClient` قبل
 * الطلب: نقطةٌ تنفق على مزوّدٍ خارجي بلا حدٍّ تُستنزف في ليلة.
 */
final class AiAssistantController
{
    public function ask(Request $request, LessonAccess $access, AnswerStudent $action): JsonResponse
    {
        $user = $request->user();

        abort_if(! $user instanceof User, 403);

        $input = $request->validate([
            'lesson_id' => ['required', 'integer'],
            'question' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $lesson = Lesson::find($input['lesson_id']);

        abort_unless($access->grants($user, $lesson), 403);

        try {
            $answer = $action->handle($user, $lesson, (string) $input['question']);
        } catch (QuotaExceededException $e) {
            return response()->json(['message' => $e->getMessage()], 402);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['answer' => $answer->body]);
    }
}
