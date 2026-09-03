<?php

declare(strict_types=1);

namespace App\Core\Notifications;

use App\Core\Modules\ModuleState;

/**
 * كتالوج الأحداث. القائمة مغلقة في config: لا يصل مفتاح حدث من
 * المستخدم إلى مُرسِل ليُنفَّذ، ولا متغيّر خارج ما يعلنه الحدث.
 */
final class EventCatalogue
{
    /** @var array<string, NotificationEvent>|null */
    private ?array $events = null;

    public function __construct(private readonly ModuleState $modules) {}

    /** @return array<string, NotificationEvent> */
    public function all(): array
    {
        return $this->events ??= collect(config('notification-events', []))
            ->map(fn (array $definition, string $key): NotificationEvent => NotificationEvent::fromArray($key, $definition))
            ->all();
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    public function get(string $key): ?NotificationEvent
    {
        return $this->all()[$key] ?? null;
    }

    /** الأحداث التي يخصّها موديول مفعّل — ما عداها لا يُعرض ولا يُرسل. */
    public function available(): array
    {
        return array_filter(
            $this->all(),
            fn (NotificationEvent $event): bool => $event->module === null || $this->modules->enabled($event->module),
        );
    }

    /** @return array<string, list<NotificationEvent>> */
    public function grouped(): array
    {
        $groups = [];

        foreach ($this->available() as $event) {
            $groups[$event->group][] = $event;
        }

        return $groups;
    }
}
