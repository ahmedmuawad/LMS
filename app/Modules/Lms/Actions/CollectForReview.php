<?php

declare(strict_types=1);

namespace App\Modules\Lms\Actions;

use App\Models\User;
use App\Modules\Lms\Models\QuizAttempt;
use App\Modules\Lms\Models\ReviewItem;

/**
 * يجمع ما أخطأ فيه الطالب ليعود إليه.
 *
 * يُنادى بعد التصحيح: كل إجابةٍ خاطئة تدخل قائمة مراجعته، والخطأ
 * الثاني في السؤال نفسه يرفع عدّاده ولا يُنشئ صفّاً ثانياً.
 *
 * ## وما ينتظر تصحيح المدرّس لا يدخل
 *
 * المقالي يبقى `is_correct = null` حتى يقرأه المدرّس. وإدخالُه
 * الآن يعني مراجعةً لسؤالٍ لا يُعرف بعدُ أخطأ فيه أم لا — ثم يجده
 * الطالب صحيحاً فيسأل: لماذا أراجعه؟
 *
 * ## وما أُتقن يعود إن أُخطئ فيه ثانيةً
 *
 * الإتقان ليس شهادةً دائمة: من نسي بعد شهر يُخطئ، وإعادةُ فتحه
 * أصدق من تركه مُتقَناً في السجلّ ومنسيّاً في رأسه.
 */
final class CollectForReview
{
    public function afterAttempt(QuizAttempt $attempt): int
    {
        $student = $attempt->enrollment?->user;

        if (! $student instanceof User || ! setting('lms.review_enabled', true)) {
            return 0;
        }

        $wrong = $attempt->answers()->where('is_correct', false)->pluck('question_id');

        if ($wrong->isEmpty()) {
            return 0;
        }

        $courseId = $attempt->enrollment?->course_id;

        foreach ($wrong as $questionId) {
            $item = ReviewItem::firstOrNew([
                'user_id' => $student->getKey(),
                'question_id' => $questionId,
            ]);

            $item->forceFill([
                'course_id' => $item->course_id ?? $courseId,
                // الجديد يبدأ بواحد، والموجود يرتفع — ولا يُقرأ الافتراضي من القاعدة قبل الحفظ
                'wrong_count' => $item->exists ? (int) $item->wrong_count + 1 : 1,
                'streak' => 0,
                'mastered_at' => null,
            ])->save();
        }

        return $wrong->count();
    }
}
