<?php

declare(strict_types=1);

namespace App\Modules\Lms\Actions;

use App\Modules\Lms\Models\ReviewItem;

/**
 * يصحّح إجابة مراجعة ويحرّك عدّاد الإتقان.
 *
 * ## ولا درجة هنا
 *
 * المراجعة تدريبٌ لا امتحان: لا تُحتسب في سجلّ الطالب، ولا تظهر في
 * دفتر درجاته، ولا يراها مدرّسه درجةً. ولو حُسبت لتردّد الطالب في
 * المحاولة خوفاً من أن يُسجَّل خطؤه — وهو نقيض المقصود.
 */
final class AnswerReviewItem
{
    /**
     * @return array{correct:?bool, mastered:bool, remaining:int}
     */
    public function handle(ReviewItem $item, mixed $answer): array
    {
        $question = $item->question;

        if ($question === null) {
            $item->delete();   // سؤالٌ حُذف من البنك: يخرج من المراجعة بلا أثر

            return ['correct' => null, 'mastered' => false, 'remaining' => $this->remaining($item)];
        }

        $correct = $question->grade($answer);

        /*
         | الصواب يرفع السلسلة، والخطأ يُصفّرها ويرفع عدّاد الخطأ.
         |
         | والتصفير لا الإنقاص: من أخطأ بعد صوابين لم يكن قد أتقن،
         | وإنقاصُ واحدٍ يجعله يُتقن بصوابٍ واحد بعد الخطأ مباشرةً.
         */
        $streak = $correct === true ? (int) $item->streak + 1 : 0;
        $needed = max(1, (int) setting('lms.review_mastery', 2));

        $item->forceFill([
            'streak' => $streak,
            'seen_count' => (int) $item->seen_count + 1,
            'wrong_count' => $correct === false ? (int) $item->wrong_count + 1 : (int) $item->wrong_count,
            'last_seen_at' => now(),
            'mastered_at' => $streak >= $needed ? now() : null,
        ])->save();

        return [
            'correct' => $correct,
            'mastered' => $item->mastered_at !== null,
            'remaining' => $this->remaining($item),
        ];
    }

    private function remaining(ReviewItem $item): int
    {
        return ReviewItem::where('user_id', $item->user_id)->pending()->count();
    }
}
