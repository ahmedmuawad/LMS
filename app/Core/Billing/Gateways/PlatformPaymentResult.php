<?php

declare(strict_types=1);

namespace App\Core\Billing\Gateways;

use App\Core\Support\Money;

/** نتيجة دفعة على فاتورة اشتراك. */
final readonly class PlatformPaymentResult
{
    private function __construct(
        public bool $paid,
        public ?int $invoiceId = null,
        public ?Money $amount = null,
        public ?string $reference = null,
        public ?string $message = null,
    ) {}

    public static function paid(int $invoiceId, Money $amount, ?string $reference = null): self
    {
        return new self(true, $invoiceId, $amount, $reference);
    }

    public static function failed(?string $message = null, ?int $invoiceId = null): self
    {
        return new self(false, $invoiceId, message: $message);
    }
}
