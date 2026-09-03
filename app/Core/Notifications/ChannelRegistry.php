<?php

declare(strict_types=1);

namespace App\Core\Notifications;

use App\Core\Notifications\Channels\Channel;
use Illuminate\Contracts\Container\Container;

/** سجلّ القنوات — قائمة مغلقة في config. */
final class ChannelRegistry
{
    /** @var array<string, Channel>|null */
    private ?array $channels = null;

    public function __construct(private readonly Container $container) {}

    /** @return array<string, Channel> */
    public function all(): array
    {
        return $this->channels ??= collect(config('notification-channels', []))
            ->mapWithKeys(fn (string $class, string $key): array => [$key => $this->container->make($class)])
            ->all();
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    public function get(string $key): ?Channel
    {
        return $this->all()[$key] ?? null;
    }

    /** @return array<string, Channel> */
    public function ready(): array
    {
        return array_filter($this->all(), fn (Channel $channel): bool => $channel->isReady());
    }
}
