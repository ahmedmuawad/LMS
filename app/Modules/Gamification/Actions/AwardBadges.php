<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Actions;

use App\Models\User;
use App\Modules\Gamification\Models\Badge;
use App\Modules\Gamification\Models\PointEntry;

/**
 * منح الشارات المستحقّة.
 *
 * تُفحص كل الشارات بعد كل قيد نقاط لا عند حدث بعينه: شرط شارة قد
 * يتحقّق من طريق لم نتوقّعه، وربطها بحدث واحد يجعلها تفوت صاحبها.
 */
final class AwardBadges
{
    /** @return list<Badge> ما مُنح الآن */
    public function handle(User $user): array
    {
        if (! (bool) setting('gamification.badges', true)) {
            return [];
        }

        $owned = $user->badges()->pluck('badges.id')->all();

        $candidates = Badge::where('is_active', true)
            ->whereNotNull('condition_rule')
            ->when($owned !== [], fn ($q) => $q->whereNotIn('id', $owned))
            ->get();

        if ($candidates->isEmpty()) {
            return [];
        }

        $counts = PointEntry::where('user_id', $user->getKey())
            ->selectRaw('rule, count(*) as total')
            ->groupBy('rule')
            ->pluck('total', 'rule');

        $awarded = [];

        foreach ($candidates as $badge) {
            if ((int) ($counts[$badge->condition_rule] ?? 0) < max(1, (int) $badge->condition_value)) {
                continue;
            }

            $user->badges()->attach($badge->getKey(), ['awarded_at' => now()]);
            $awarded[] = $badge;

            notify('gamification.badge_earned', $user, [
                'badge_name' => (string) $badge->name,
                'badge_description' => (string) $badge->description,
                'url' => url('/my-progress'),
            ]);
        }

        return $awarded;
    }

    /** يُنشئ الشارات الافتراضية للمشترك — تُحرَّر بعدها كما يشاء. */
    public function install(): int
    {
        $created = 0;

        foreach (config('gamification.badges', []) as $position => $definition) {
            if (Badge::where('key', $definition['key'])->exists()) {
                continue;
            }

            Badge::create([
                'key' => $definition['key'],
                'name' => $definition['name'],
                'description' => $definition['description'] ?? null,
                'icon' => $definition['icon'] ?? '★',
                'tone' => $definition['tone'] ?? 'primary',
                'condition_rule' => $definition['rule'] ?? null,
                'condition_value' => (int) ($definition['value'] ?? 1),
                'position' => $position,
                'is_active' => true,
            ]);

            $created++;
        }

        return $created;
    }
}
