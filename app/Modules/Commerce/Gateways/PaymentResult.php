<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Gateways;

use App\Core\Support\Money;

/** نتيجة الدفع كما فهمتها البوابة. */
final readonly class PaymentResult
{
    public function __construct(
        public string $orderNumber,
        public bool $successful,
        public Money $amount,
        public ?string $reference = null,
        public ?string $failureReason = null,
        public array $raw = [],
    ) {}
}
