<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Actions;

use App\Models\User;
use App\Modules\Gamification\Models\Badge;
use App\Modules\Gamification\Models\LearningStreak;
use App\Modules\Gamification\Models\PointEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * منح النقاط.
 *
 * القاعدة الوحيدة التي لا تُكسر: نقاط لا تُمنح مرتين على نفس السبب.
 * لوحة صدارة يمكن حشوها بإعادة فتح درس ليست لوحة صدارة، وأول من
 * يكتشف الثغرة يُفسدها للجميع.
 */
final class AwardPoints
{
    public function __construct(private readonly AwardBadges $badges) {}

    public function handle(User $user, string $rule, ?Model $source = null, ?string $note = null): ?PointEntry
    {
        // مفتاح القاعدة فيه نقطة (lesson.completed)، وconfig() يقسم
        // عندها إلى مستويات — فتُقرأ المصفوفة كاملة ويُفهرس منها
        $definition = config('gamification.rules', [])[$rule] ?? null;

        if ($definition === null || ! (bool) setting('gamification.enabled', true)) {
            return null;
        }

        $points = (int) setting('gamification.points.'.$rule, $definition['points']);

        if ($points === 0) {
            return null;
        }

        if (! $this->isAllowed($user, $rule, $definition, $source)) {
            return null;
        }

        $entry = DB::transaction(function () use ($user, $rule, $points, $source, $note): PointEntry {
            $created = PointEntry::create([
                'user_id' => $user->getKey(),
                'rule' => $rule,
                'points' => $points,
                'source_type' => $source === null ? null : $source::class,
                'source_id' => $source?->getKey(),
                'note' => $note,
            ]);

            $this->recalculate($user);

            return $created;
        });

        /*
         | وتُفحص التحدّيات بعد كل نقطة.
         |
         | التحدي يقيس فعلاً وقع، وكل فعلٍ يُسجَّل نقطةً — فهذا موضع
         | الفحص. وفحصٌ ليليٌّ بجدول يجعل الطالب يُتمّ التحدي فلا يرى
         | شيئاً حتى الغد، وقد نسي.
         |
         | وخارج المعاملة: منحُ الجائزة يكتب نقطةً أخرى، ومعاملةٌ
         | داخل معاملة تُعقّد ما لا يحتاج تعقيداً.
         */
        if ($rule !== 'challenge.completed') {
            app(CheckChallenges::class)->forUser($user, $rule);
        }

        return $entry;
    }

    /**
     * تسجيل نشاط اليوم: يرفع التتابع ويمنح نقطته مرة واحدة يومياً.
     *
     * التتابع أقوى محرّك عادة في التعلّم، وكسره ظلماً — بيوم لم
     * يُحتسب — يُفقد أثره كلّه.
     */
    public function touchStreak(User $user): LearningStreak
    {
        $streak = LearningStreak::firstOrCreate(['user_id' => $user->getKey()]);
        $today = now()->startOfDay();

        if ($streak->last_active_on !== null && $streak->last_active_on->isSameDay($today)) {
            return $streak;
        }

        $continues = $streak->last_active_on !== null
            && $streak->last_active_on->isSameDay($today->copy()->subDay());

        $current = $continues ? (int) $streak->current_days + 1 : 1;

        $streak->forceFill([
            'current_days' => $current,
            'longest_days' => max((int) $streak->longest_days, $current),
            'last_active_on' => $today,
        ])->save();

        $this->handle($user, 'streak.day');

        if ($current > 0 && $current % 7 === 0) {
            $this->handle($user, 'streak.week', null, __(':days يوماً متتابعاً', ['days' => $current]));
        }

        return $streak->refresh();
    }

    /** إعادة جمع النقاط والمستوى من القيود نفسها لا من عدّاد يُزاد. */
    public function recalculate(User $user): LearningStreak
    {
        $total = (int) PointEntry::where('user_id', $user->getKey())->sum('points');

        $streak = LearningStreak::firstOrCreate(['user_id' => $user->getKey()]);

        $streak->forceFill([
            'total_points' => $total,
            'level' => LearningStreak::levelFor($total),
        ])->save();

        $this->badges->handle($user);

        return $streak;
    }

    /** @param  array<string, mixed>  $definition */
    private function isAllowed(User $user, string $rule, array $definition, ?Model $source): bool
    {
        // نفس السبب مرة واحدة: إعادة فتح الدرس لا تُعيد نقاطه
        if (($definition['once'] ?? false) && $source !== null) {
            $exists = PointEntry::where('user_id', $user->getKey())
                ->where('rule', $rule)
                ->where('source_type', $source::class)
                ->where('source_id', $source->getKey())
                ->exists();

            if ($exists) {
                return false;
            }
        }

        // وقاعدة بلا مصدر (كطرح سؤال) يحدّها سقف يومي
        $daily = (int) ($definition['daily'] ?? 0);

        if ($daily > 0) {
            $today = PointEntry::where('user_id', $user->getKey())
                ->where('rule', $rule)
                ->whereDate('created_at', now()->toDateString())
                ->count();

            if ($today >= $daily) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> ملخّص يُعرض للطالب */
    public function summaryFor(User $user): array
    {
        $streak = LearningStreak::firstOrCreate(['user_id' => $user->getKey()]);

        return [
            'points' => (int) $streak->total_points,
            'level' => (int) $streak->level,
            'to_next' => $streak->pointsToNextLevel(),
            'streak' => $streak->isStreakAlive() ? (int) $streak->current_days : 0,
            'longest' => (int) $streak->longest_days,
            'badges' => Badge::whereHas('users', fn ($q) => $q->whereKey($user->getKey()))->get(),
        ];
    }
}
