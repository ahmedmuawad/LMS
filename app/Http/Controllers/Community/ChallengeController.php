<?php

declare(strict_types=1);

namespace App\Http\Controllers\Community;

use App\Models\User;
use App\Modules\Gamification\Actions\CheckChallenges;
use App\Modules\Gamification\Actions\SpinWheel;
use App\Modules\Gamification\Models\Challenge;
use App\Modules\Gamification\Models\ChallengeCompletion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * تحدّيات الطالب وعجلته اليومية.
 *
 * ## في شاشةٍ واحدة
 *
 * الاثنان يسألان السؤال نفسه: «ما الذي يُعيدني اليوم؟». والفصل
 * بينهما يجعل الطالب يزور واحدةً وينسى الأخرى.
 */
final class ChallengeController
{
    public function index(Request $request, SpinWheel $wheel, CheckChallenges $checker): View
    {
        $user = $this->user($request);

        abort_unless((bool) setting('gamification.enabled', true), 404);

        /*
         | يُفحص عند الفتح كذلك.
         |
         | الفحص يقع مع كل نقطة، لكنّ تحدّياً أُنشئ بعد أن أتمّ
         | الطالب شرطَه لا يُفحص إلا هنا — فيجده مُنجَزاً حين يزور.
         */
        $checker->forUser($user);

        $challenges = Challenge::running()->with('badge')->orderBy('ends_at')->get();

        $done = ChallengeCompletion::where('user_id', $user->getKey())
            ->pluck('completed_at', 'challenge_id');

        return view('community.challenges', [
            'rows' => $challenges->map(fn (Challenge $c): array => [
                'challenge' => $c,
                'progress' => min((int) $c->target, $c->progressFor($user)),
                'done' => $done[$c->getKey()] ?? null,
            ]),
            'canSpin' => $wheel->canSpin($user),
            'segments' => $wheel->segments(),
            'wheelOn' => (bool) setting('gamification.wheel', true),
        ]);
    }

    public function spin(Request $request, SpinWheel $wheel): RedirectResponse
    {
        $user = $this->user($request);

        try {
            $result = $wheel->handle($user);
        } catch (RuntimeException $e) {
            return back()->withErrors(['wheel' => $e->getMessage()]);
        }

        return back()->with('wheel_result', $result);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_if(! $user instanceof User, 403);

        return $user;
    }
}
