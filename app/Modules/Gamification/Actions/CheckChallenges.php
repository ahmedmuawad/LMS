<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Actions;

use App\Models\User;
use App\Modules\Gamification\Models\Challenge;
use App\Modules\Gamification\Models\ChallengeCompletion;
use App\Modules\Gamification\Models\PointEntry;

/**
 * يفحص تحدّيات الطالب ويمنح جوائز ما أتمّه.
 *
 * ## يُنادى عند كل نقطة تُمنَح
 *
 * التحدي يقيس فعلاً وقع، وكل فعلٍ يُسجَّل نقطةً — فموضعُ الفحص
 * حيث تُسجَّل النقطة. وفحصٌ دوريٌّ بجدولٍ ليلي يجعل الطالب يُتمّ
 * التحدي فلا يرى شيئاً حتى الغد — وقد نسي.
 *
 * ## والجائزة مرّةٌ واحدة
 *
 * المفتاح الفريد على (التحدي، الطالب) يمنع التكرار في القاعدة لا
 * في الشيفرة وحدها: نداءان متزامنان يمرّان من فحصٍ بالشيفرة معاً.
 */
final class CheckChallenges
{
    public function __construct(private readonly AwardPoints $points) {}

    /** @return list<Challenge> ما أُتمّ الآن — لتُعرَض بشارةٌ للطالب */
    public function forUser(User $user, ?string $rule = null): array
    {
        if (! setting('gamification.challenges', true)) {
            return [];
        }

        $challenges = Challenge::running()
            ->when($rule !== null, fn ($q) => $q->where('rule', $rule))
            ->get();

        if ($challenges->isEmpty()) {
            return [];
        }

        $done = ChallengeCompletion::where('user_id', $user->getKey())
            ->whereIn('challenge_id', $challenges->pluck('id'))
            ->pluck('challenge_id')
            ->all();

        $earned = [];

        foreach ($challenges as $challenge) {
            if (in_array($challenge->getKey(), $done, true)) {
                continue;
            }

            if ($challenge->progressFor($user) < (int) $challenge->target) {
                continue;
            }

            $completion = ChallengeCompletion::firstOrCreate(
                ['challenge_id' => $challenge->getKey(), 'user_id' => $user->getKey()],
                ['completed_at' => now()],
            );

            // `wasRecentlyCreated` يفصل الإنجاز الآن عن قراءةٍ لما سبق
            if (! $completion->wasRecentlyCreated) {
                continue;
            }

            $this->reward($user, $challenge);
            $earned[] = $challenge;
        }

        return $earned;
    }

    private function reward(User $user, Challenge $challenge): void
    {
        if ((int) $challenge->reward_points > 0) {
            /*
             | النقاط تُسجَّل بقاعدةٍ خاصّة بالتحدّيات.
             |
             | ولو سُجّلت بقاعدة التحدي نفسها (`lesson.completed`)
             | لعدّت نفسها في تقدّمه — فيُتمّ التحدي بجائزته لا بعمله.
             */
            PointEntry::create([
                'user_id' => $user->getKey(),
                'rule' => 'challenge.completed',
                'points' => (int) $challenge->reward_points,
                'source_type' => Challenge::class,
                'source_id' => $challenge->getKey(),
                'note' => (string) $challenge->title,
            ]);
        }

        if ($challenge->badge_id !== null) {
            app(AwardBadges::class)->give($user, (int) $challenge->badge_id);
        }
    }
}
