<?php

declare(strict_types=1);

namespace App\Core\Notifications;

use App\Models\User;

/** رسالة واحدة جاهزة للإرسال على قناة بعينها. */
final class Delivery
{
    /** @param  array<string, mixed>  $data */
    public function __construct(
        public readonly NotificationEvent $event,
        public readonly User $user,
        public readonly string $locale,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $data = [],
        public readonly ?string $providerTemplate = null,
    ) {}

    public function url(): ?string
    {
        $url = $this->data['url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }
}
