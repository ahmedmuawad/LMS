<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * حالة اللاعب: نقاطه ومستواه وتتابعه.
 *
 * محسوبة ومخزَّنة لا مُستنتَجة عند كل عرض: استنتاجها يعني قراءة
 * كل نشاط المستخدم في كل صفحة يفتحها.
 */
final class LearningStreak extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_active_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** المستوى يتباعد كلّما ارتفع: تكرار نفس الجهد لا يرفعه إلى ما لا نهاية. */
    public static function levelFor(int $points): int
    {
        return max(1, (int) floor(sqrt(max(0, $points) / 50)) + 1);
    }

    public function pointsToNextLevel(): int
    {
        $next = $this->level + 1;

        return max(0, (int) (50 * ($next - 1) ** 2) - (int) $this->total_points);
    }

    public function isStreakAlive(): bool
    {
        return $this->last_active_on !== null
            && $this->last_active_on->gte(now()->subDay()->startOfDay());
    }
}
