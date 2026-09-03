<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Core\Notifications\ChannelRegistry;
use App\Core\Notifications\EventCatalogue;
use App\Core\Notifications\Models\Notification;
use App\Core\Notifications\Models\NotificationPreference;
use App\Core\Notifications\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** صندوق وارد المستخدم وتفضيلاته وأجهزته. */
final class InboxController
{
    public function index(Request $request): View
    {
        $notifications = Notification::where('user_id', $request->user()->getKey())
            ->when($request->input('filter') === 'unread', fn ($q) => $q->unread())
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('notifications.inbox', [
            'notifications' => $notifications,
            'unread' => Notification::where('user_id', $request->user()->getKey())->unread()->count(),
        ]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = Notification::where('user_id', $request->user()->getKey())->findOrFail($id);

        $notification->forceFill(['read_at' => $notification->read_at ?? now()])->save();

        return redirect($notification->url ?? url('/notifications'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        Notification::where('user_id', $request->user()->getKey())->unread()
            ->update(['read_at' => now()]);

        return back()->with('status', __('عُلِّمت كلها مقروءة.'));
    }

    public function preferences(Request $request, EventCatalogue $catalogue, ChannelRegistry $channels): View
    {
        return view('notifications.preferences', [
            'groups' => $catalogue->grouped(),
            'channels' => $channels->ready(),
            'groupLabels' => config('notification-groups', []),
            'overrides' => NotificationPreference::where('user_id', $request->user()->getKey())
                ->get()->groupBy('event'),
        ]);
    }

    public function savePreferences(Request $request, EventCatalogue $catalogue, ChannelRegistry $channels): RedirectResponse
    {
        abort_unless((bool) setting('notifications.user_preferences', true), 403);

        $input = $request->validate([
            'enabled' => ['nullable', 'array'],
            'enabled.*' => ['nullable', 'array'],
        ]);

        $enabled = $input['enabled'] ?? [];
        $userId = $request->user()->getKey();

        foreach ($catalogue->available() as $event) {
            // الحدث الأمني لا يُطفأ: من يغيّر كلمة مرورك يجب أن يصلك خبره
            if ($event->isMandatory()) {
                continue;
            }

            foreach ($event->channels as $channel) {
                if (! $channels->has($channel)) {
                    continue;
                }

                NotificationPreference::updateOrCreate(
                    ['user_id' => $userId, 'event' => $event->key, 'channel' => $channel],
                    ['is_enabled' => (bool) ($enabled[$event->key][$channel] ?? false)],
                );
            }
        }

        return back()->with('status', __('حُفظت تفضيلاتك.'));
    }

    /** تسجيل جهاز لإشعارات المتصفّح. */
    public function subscribe(Request $request): JsonResponse
    {
        $input = $request->validate([
            'endpoint' => ['required', 'string', 'max:500', 'url'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint' => $input['endpoint']],
            [
                'user_id' => $request->user()->getKey(),
                'public_key' => $input['keys']['p256dh'],
                'auth_token' => $input['keys']['auth'],
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'last_used_at' => now(),
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $endpoint = $request->validate(['endpoint' => ['required', 'string', 'max:500']])['endpoint'];

        PushSubscription::where('user_id', $request->user()->getKey())
            ->where('endpoint', $endpoint)->delete();

        return response()->json(['ok' => true]);
    }
}
