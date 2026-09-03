<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Gateways;

use App\Core\Support\Money;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * سجلّ البوابات.
 *
 * القائمة مغلقة في config: لا يصل اسم بوابة من المستخدم إلى
 * الحاوية ليُحلّ كصنف.
 */
final class GatewayManager
{
    public function __construct(private readonly Container $container) {}

    /** @return list<PaymentGateway> ما هو مهيّأ فعلاً لهذا المشترك */
    public function available(?Money $amount = null, ?string $country = null): array
    {
        $gateways = [];

        foreach (config('payments.drivers', []) as $key => $class) {
            $gateway = $this->container->make($class);

            if (! $gateway->isReady()) {
                continue;
            }

            if ($amount !== null && ! $gateway->supports($amount, $country)) {
                continue;
            }

            $gateways[] = $gateway;
        }

        usort($gateways, fn (PaymentGateway $a, PaymentGateway $b): int => $this->position($a) <=> $this->position($b));

        return $gateways;
    }

    public function resolve(string $key): PaymentGateway
    {
        $class = config("payments.drivers.{$key}")
            ?? throw new RuntimeException("بوابة غير معروفة: [{$key}]");

        return $this->container->make($class);
    }

    public function has(string $key): bool
    {
        return config("payments.drivers.{$key}") !== null;
    }

    private function position(PaymentGateway $gateway): int
    {
        return (int) setting('payments.'.$gateway->key().'_position', 0);
    }
}
