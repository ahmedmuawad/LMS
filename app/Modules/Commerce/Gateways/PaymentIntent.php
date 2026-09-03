<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Gateways;

/** ما يجب أن يحدث في المتصفح بعد بدء الدفع. */
final readonly class PaymentIntent
{
    private function __construct(
        public string $mode,          // redirect · instructions · done
        public ?string $url = null,
        public ?string $reference = null,
        public ?string $message = null,
        public array $meta = [],
    ) {}

    public static function redirect(string $url, ?string $reference = null): self
    {
        return new self('redirect', url: $url, reference: $reference);
    }

    /** بوابات تُعطي كوداً أو بيانات تحويل ينتظر بعدها اعتماداً. */
    public static function instructions(string $message, ?string $reference = null, array $meta = []): self
    {
        return new self('instructions', reference: $reference, message: $message, meta: $meta);
    }

    /** دفع تمّ فوراً: محفظة أو طلب مجاني. */
    public static function done(?string $reference = null): self
    {
        return new self('done', reference: $reference);
    }
}
