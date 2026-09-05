<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use App\Models\User;
use App\Modules\Content\Models\Event;
use App\Modules\Content\Models\EventRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * تقويم الفعاليات — للزائر والطالب.
 */
final class EventController
{
    public function index(Request $request): View
    {
        $query = Event::published()->with('course');

        // الخاصّة لطلبة المنصّة، والعامّة للزائر كذلك
        if ($request->user() === null) {
            $query->where('is_public', true);
        }

        $upcoming = (clone $query)->upcoming()->orderBy('starts_at')->limit(60)->get();

        return view('content.events', [
            'upcoming' => $upcoming,

            /*
             | الماضية تُعرض قليلةً لا تُخفى.
             |
             | من فاته لقاءٌ يريد أن يعرف أنه وقع — وربّما وُضع
             | تسجيله. وإخفاؤها يجعل التقويم يبدو فارغاً في أول
             | الشهر.
             */
            'past' => (clone $query)
                ->where(fn ($q) => $q->where('starts_at', '<', now()))
                ->orderByDesc('starts_at')
                ->limit(6)
                ->get(),

            'mine' => $request->user() === null ? collect() : EventRegistration::query()
                ->where('user_id', $request->user()->getKey())
                ->where('status', '!=', 'cancelled')
                ->pluck('event_id'),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $event = Event::published()->with('course')->where('slug', $slug)->firstOrFail();

        abort_if(! $event->is_public && $request->user() === null, 403);

        return view('content.event', [
            'event' => $event,
            'registered' => $event->isRegistered($request->user()),
        ]);
    }

    public function register(Request $request, string $slug): RedirectResponse
    {
        $user = $request->user();

        abort_if(! $user instanceof User, 403);

        $event = Event::published()->where('slug', $slug)->firstOrFail();

        abort_unless($event->takesRegistrations(), 404, __('هذه الفعالية للإعلان فقط.'));

        if ($event->hasPassed()) {
            return back()->withErrors(['event' => __('انتهت هذه الفعالية.')]);
        }

        /*
         | السعة تُفحص داخل معاملةٍ بقفل.
         |
         | عشرون مقعداً وثلاثون يضغطون في اللحظة نفسها: فحصٌ بلا قفل
         | يمرّ منه الثلاثون، فتمتلئ القاعة بعشرة زائدين لا مكان لهم.
         */
        $seated = DB::transaction(function () use ($event, $user): bool {
            $fresh = Event::lockForUpdate()->find($event->getKey());

            if ($fresh === null || $fresh->isFull()) {
                return false;
            }

            $registration = EventRegistration::firstOrNew([
                'event_id' => $fresh->getKey(),
                'user_id' => $user->getKey(),
            ]);

            // من ألغى ثم عاد يُحسب مرّةً واحدة لا مرّتين
            $wasCounted = $registration->exists && $registration->status !== 'cancelled';

            $registration->forceFill([
                'status' => 'registered',
                'registered_at' => now(),
            ])->save();

            if (! $wasCounted) {
                $fresh->increment('registered_count');
            }

            return true;
        });

        return $seated
            ? back()->with('status', __('سُجّلت. سنراك هناك.'))
            : back()->withErrors(['event' => __('اكتملت المقاعد.')]);
    }

    public function cancel(Request $request, string $slug): RedirectResponse
    {
        $user = $request->user();

        abort_if(! $user instanceof User, 403);

        $event = Event::where('slug', $slug)->firstOrFail();

        DB::transaction(function () use ($event, $user): void {
            $registration = EventRegistration::where('event_id', $event->getKey())
                ->where('user_id', $user->getKey())
                ->where('status', '!=', 'cancelled')
                ->lockForUpdate()
                ->first();

            if ($registration === null) {
                return;
            }

            $registration->forceFill(['status' => 'cancelled'])->save();

            // المقعد يعود إلى غيره فوراً
            Event::where('id', $event->getKey())->where('registered_count', '>', 0)
                ->decrement('registered_count');
        });

        return back()->with('status', __('أُلغي تسجيلك، وعاد المقعد لغيرك.'));
    }
}
