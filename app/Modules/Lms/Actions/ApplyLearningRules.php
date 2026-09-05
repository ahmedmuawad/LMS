<?php

declare(strict_types=1);

namespace App\Modules\Lms\Actions;

use App\Modules\Lms\Models\LearningRule;
use App\Modules\Lms\Models\QuizAttempt;
use Illuminate\Support\Facades\DB;

/**
 * يفتح ما تفتحه نتيجةُ محاولةٍ من عناصر المنهج.
 *
 * المنهج اليوم خطٌّ واحد: من رسب في اختبار الوحدة يمضي كمن أتقنها،
 * ومن أتقنها يُجبَر على مراجعةٍ لا يحتاجها. وهذه القواعد تجعل
 * المسار يتبع الطالب.
 */
final class ApplyLearningRules
{
    /** @return int عدد ما فُتح */
    public function handle(QuizAttempt $attempt): int
    {
        $item = $attempt->quiz?->items()->first();

        if ($item === null) {
            return 0;
        }

        $rules = LearningRule::where('trigger_item_id', $item->getKey())->get();

        if ($rules->isEmpty()) {
            return 0;
        }

        $percentage = (float) $attempt->percentage;
        $opened = 0;

        foreach ($rules as $rule) {
            if (! $rule->matches($percentage)) {
                continue;
            }

            /*
             | الفتح لا يُغلق ما فُتح.
             |
             | محاولةٌ ثانية أسوأ من الأولى لا تسحب ما استحقّه الطالب
             | بالأولى: السحبُ عقوبةٌ على المحاولة، والمحاولة هي ما
             | نريده منه.
             */
            $created = DB::table('unlocked_items')->insertOrIgnore([
                'enrollment_id' => $attempt->enrollment_id,
                'item_id' => $rule->target_item_id,
                'rule_id' => $rule->getKey(),
                'created_at' => now(),
            ]);

            $opened += (int) $created;
        }

        return $opened;
    }
}
