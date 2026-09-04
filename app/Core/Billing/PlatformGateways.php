<?php

declare(strict_types=1);

namespace App\Core\Billing;

use App\Core\Billing\Gateways\PlatformGateway;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * سجلّ بوابات اشتراكاتنا.
 *
 * القائمة مغلقة في الإعداد: لا يصل مفتاح من المستخدم إلى الحاوية
 * ليُحلّ كصنف.
 */
final class PlatformGateways
{
    public function __construct(private readonly Container $container) {}

    /** @return list<PlatformGateway> ما هو مهيّأ فعلاً */
    public function available(): array
    {
        $out = [];

        foreach (array_keys(config('platform-billing.gateways', [])) as $key) {
            $gateway = $this->resolve($key);

            if ($gateway->isReady()) {
                $out[] = $gateway;
            }
        }

        return $out;
    }

    public function resolve(string $key): PlatformGateway
    {
        $class = config('platform-billing.gateways.'.$key)
            ?? throw new RuntimeException("بوابة اشتراك غير معروفة: [{$key}]");

        return $this->container->make($class);
    }

    public function has(string $key): bool
    {
        return config('platform-billing.gateways.'.$key) !== null;
    }
}
