<?php

declare(strict_types=1);

namespace App\Core\Notifications;

/** حدث إشعار كما يعرّفه الكتالوج — قيمة ثابتة لا تُبنى من مُدخل. */
final class NotificationEvent
{
    /**
     * @param  list<string>  $channels
     * @param  list<string>  $defaults
     * @param  list<string>  $variables
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $group,
        public readonly string $audience,
        public readonly array $channels,
        public readonly array $defaults,
        public readonly array $variables,
        public readonly ?string $module = null,
    ) {}

    /** @param  array<string, mixed>  $definition */
    public static function fromArray(string $key, array $definition): self
    {
        return new self(
            key: $key,
            label: (string) ($definition['label'] ?? $key),
            group: (string) ($definition['group'] ?? 'general'),
            audience: (string) ($definition['audience'] ?? 'student'),
            channels: array_values((array) ($definition['channels'] ?? ['database'])),
            defaults: array_values((array) ($definition['default'] ?? [])),
            variables: array_values((array) ($definition['variables'] ?? [])),
            module: isset($definition['module']) ? (string) $definition['module'] : null,
        );
    }

    public function allows(string $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }

    public function isOnByDefault(string $channel): bool
    {
        return in_array($channel, $this->defaults, true);
    }

    /** الحدث الأمني لا يُطفأ: من يغيّر كلمة مرورك يجب أن يصلك خبره. */
    public function isMandatory(): bool
    {
        return in_array($this->key, [
            'account.verify_email', 'account.reset_password', 'account.password_changed',
        ], true);
    }
}
