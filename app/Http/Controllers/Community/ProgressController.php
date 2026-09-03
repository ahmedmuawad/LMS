<?php

declare(strict_types=1);

namespace App\Http\Controllers\Community;

use App\Models\User;
use App\Modules\Gamification\Actions\AwardPoints;
use App\Modules\Gamification\Models\Badge;
use App\Modules\Gamification\Models\LearningStreak;
use App\Modules\Gamification\Models\PointEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** تقدّم الطالب: نقاطه وشاراته وموقعه في لوحة الصدارة. */
final class ProgressController
{
    public function __construct(private readonly AwardPoints $points) {}

    public function show(Request $request): View
    {
        $user = $request->user();

        return view('community.progress', [
            'summary' => $this->points->summaryFor($user),
            'allBadges' => Badge::where('is_active', true)->orderBy('position')->get(),
            'entries' => PointEntry::where('user_id', $user->getKey())
                ->latest('id')->paginate(20),
            'rules' => config('gamification.rules', []),
        ]);
    }

    public function leaderboard(Request $request): View
    {
        abort_unless((bool) setting('gamification.leaderboard', true), 404);

        $since = match ((string) setting('gamification.leaderboard_period', 'week')) {
            'week' => now()->startOfWeek(Carbon::SATURDAY),
            'month' => now()->startOfMonth(),
            default => null,
        };

        $size = (int) setting('gamification.leaderboard_size', 10);

        $rows = PointEntry::selectRaw('user_id, sum(points) as total')
            ->when($since !== null, fn ($q) => $q->where('created_at', '>=', $since))
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit($size)
            ->get();

        $users = User::whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        /*
         | ترتيبك أنت يُحسب دائماً ولو كنت خارج القائمة: من في القاع
         | يحتاج أن يرى تقدّمه لا أن يُخفى عنه.
         */
        $mine = (int) PointEntry::where('user_id', $request->user()?->getKey())
            ->when($since !== null, fn ($q) => $q->where('created_at', '>=', $since))
            ->sum('points');

        $ahead = PointEntry::selectRaw('user_id, sum(points) as total')
            ->when($since !== null, fn ($q) => $q->where('created_at', '>=', $since))
            ->groupBy('user_id')
            ->havingRaw('sum(points) > ?', [$mine])
            ->get()
            ->count();

        return view('community.leaderboard', [
            'rows' => $rows,
            'users' => $users,
            'since' => $since,
            'myPoints' => $mine,
            'myRank' => $ahead + 1,
            'streak' => LearningStreak::firstOrCreate(['user_id' => $request->user()->getKey()]),
        ]);
    }
}
