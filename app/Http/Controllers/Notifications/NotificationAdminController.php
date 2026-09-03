<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Core\Notifications\ChannelRegistry;
use App\Core\Notifications\EventCatalogue;
use App\Core\Notifications\Models\NotificationLog;
use App\Core\Notifications\Models\NotificationTemplate;
use App\Core\Notifications\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** مصفوفة الإشعارات ومحرّر القوالب وسجلّ الإرسال. */
final class NotificationAdminController
{
    public function __construct(
        private readonly EventCatalogue $catalogue,
        private readonly ChannelRegistry $channels,
    ) {}

    public function matrix(): View
    {
        return view('notifications.matrix', [
            'groups' => $this->catalogue->grouped(),
            'channels' => $this->channels->all(),
            'templates' => NotificationTemplate::get()->groupBy('event'),
            'groupLabels' => config('notification-groups', []),
        ]);
    }

    /** حفظ المصفوفة كاملة: تبديل قناة واحدة لا يستحق طلباً لكل خانة. */
    public function saveMatrix(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'enabled' => ['nullable', 'array'],
            'enabled.*' => ['nullable', 'array'],
        ]);

        $enabled = $input['enabled'] ?? [];

        foreach ($this->catalogue->available() as $event) {
            foreach ($event->channels as $channel) {
                if (! $this->channels->has($channel)) {
                    continue;
                }

                NotificationTemplate::updateOrCreate(
                    ['event' => $event->key, 'channel' => $channel],
                    ['is_enabled' => (bool) ($enabled[$event->key][$channel] ?? false)],
                );
            }
        }

        return back()->with('status', __('حُفظت مصفوفة الإشعارات.'));
    }

    public function edit(string $event): View
    {
        $definition = $this->catalogue->get($event)
            ?? throw new NotFoundHttpException("حدث غير معروف: [{$event}]");

        return view('notifications.template', [
            'event' => $definition,
            'channels' => array_filter(
                $this->channels->all(),
                fn ($channel, string $key): bool => $definition->allows($key),
                ARRAY_FILTER_USE_BOTH,
            ),
            'templates' => NotificationTemplate::where('event', $event)->get()->keyBy('channel'),
            'locales' => array_keys(config('locales.supported', ['ar' => []])),
        ]);
    }

    public function update(Request $request, string $event): RedirectResponse
    {
        $definition = $this->catalogue->get($event)
            ?? throw new NotFoundHttpException("حدث غير معروف: [{$event}]");

        $input = $request->validate([
            'templates' => ['required', 'array'],
            'templates.*.subject' => ['nullable', 'array'],
            'templates.*.subject.*' => ['nullable', 'string', 'max:200'],
            'templates.*.body' => ['nullable', 'array'],
            'templates.*.body.*' => ['nullable', 'string', 'max:5000'],
            'templates.*.provider_template' => ['nullable', 'string', 'max:120'],
            'templates.*.is_enabled' => ['nullable'],
        ]);

        foreach ($input['templates'] as $channel => $values) {
            // قناة لا يسمح بها الحدث لا تُحفظ ولو وصلت في الطلب
            if (! $definition->allows((string) $channel) || ! $this->channels->has((string) $channel)) {
                continue;
            }

            NotificationTemplate::updateOrCreate(
                ['event' => $event, 'channel' => $channel],
                [
                    'subject' => array_filter($values['subject'] ?? []),
                    'body' => array_filter($values['body'] ?? []),
                    'provider_template' => $values['provider_template'] ?? null,
                    'is_enabled' => (bool) ($values['is_enabled'] ?? false),
                ],
            );
        }

        return back()->with('status', __('حُفظت قوالب الحدث.'));
    }

    /** إرسال تجريبي إلى نفسك: القالب لا يُختبر إلا بوصوله. */
    public function test(Request $request, string $event, Notifier $notifier): RedirectResponse
    {
        $definition = $this->catalogue->get($event)
            ?? throw new NotFoundHttpException("حدث غير معروف: [{$event}]");

        $sample = [];

        foreach ($definition->variables as $variable) {
            $sample[$variable] = '['.$variable.']';
        }

        $sent = $notifier->send($event, $request->user(), $sample);

        return back()->with('status', $sent === []
            ? __('لا قناة جاهزة ومفعّلة لهذا الحدث.')
            : __('أُرسلت تجربة على: :channels', ['channels' => implode('، ', $sent)]));
    }

    public function logs(Request $request): View
    {
        return view('notifications.logs', [
            'logs' => NotificationLog::with('user')
                ->when($request->filled('event'), fn ($q) => $q->where('event', $request->string('event')))
                ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->string('channel')))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->latest('id')
                ->paginate(50)
                ->withQueryString(),
            'events' => $this->catalogue->available(),
            'channels' => $this->channels->all(),
        ]);
    }
}
