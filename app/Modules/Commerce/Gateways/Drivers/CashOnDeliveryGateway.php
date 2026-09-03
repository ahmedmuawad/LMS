<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Gateways\Drivers;

use App\Core\Support\Money;
use App\Modules\Commerce\Gateways\PaymentIntent;
use App\Modules\Commerce\Models\Order;

final class CashOnDeliveryGateway extends BaseGateway
{
    public function key(): string
    {
        return 'cash_on_delivery';
    }

    protected function defaultTitle(): string
    {
        return __('الدفع عند الاستلام');
    }

    /** لا يصلح لما يُسلَّم فوراً: كورس يُفتح قبل الدفع لا يُحصَّل بعده. */
    public function supports(Money $amount, ?string $country = null): bool
    {
        return parent::supports($amount, $country);
    }

    public function start(Order $order): PaymentIntent
    {
        return PaymentIntent::instructions(
            (string) ($this->setting('instructions') ?: __('ستدفع نقداً عند استلام طلبك.')),
            reference: $order->number,
        );
    }
}
