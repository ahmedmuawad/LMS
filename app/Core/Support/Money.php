<?php

declare(strict_types=1);

namespace App\Core\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * ADR-014 — كل المبالغ في النظام أعداد صحيحة بأصغر وحدة (قروش/هللات/فلوس).
 * ممنوع استخدام float في أي عملية مالية: 0.1 + 0.2 !== 0.3.
 *
 * تُنشأ دائماً عبر fromMinor() أو fromDecimal()، وهي غير قابلة للتغيير (immutable).
 */
final readonly class Money implements JsonSerializable, Stringable
{
    private function __construct(
        public int $minor,
        public string $currency,
    ) {}

    /** المبلغ بأصغر وحدة: 12550 قرشاً = 125.50 جنيه */
    public static function fromMinor(int $minor, string $currency): self
    {
        return new self($minor, self::normalizeCurrency($currency));
    }

    /** يقبل نصاً أو رقماً عشرياً ويحوّله بدقة دون المرور بـ float */
    public static function fromDecimal(string|int|float $amount, string $currency): self
    {
        $currency = self::normalizeCurrency($currency);
        $decimals = self::decimalsFor($currency);
        $value = trim((string) $amount);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("قيمة مبلغ غير صالحة: [{$amount}]");
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        // القص لا التقريب: التقريب يتم عند العرض/الفوترة مرة واحدة فقط
        $fraction = str_pad(substr($fraction, 0, $decimals), $decimals, '0');
        $minor = (int) ($whole.$fraction);

        return new self($negative ? -$minor : $minor, $currency);
    }

    public static function zero(string $currency): self
    {
        return new self(0, self::normalizeCurrency($currency));
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    /** الضرب في كمية أو نسبة، بتقريب نصفي لأعلى على أصغر وحدة */
    public function times(int|float|string $factor): self
    {
        return new self((int) round($this->minor * (float) $factor), $this->currency);
    }

    /** نسبة مئوية: ضريبة 14% => percentage(14) */
    public function percentage(int|float|string $percent): self
    {
        return $this->times((float) $percent / 100);
    }

    /**
     * توزيع المبلغ على حصص دون ضياع قرش واحد.
     * تُستخدم في: تقسيم الطلب على بنود · عمولة المدرّس · الأقساط.
     *
     * @param  list<int>  $ratios
     * @return list<self>
     */
    public function allocate(array $ratios): array
    {
        $total = array_sum($ratios);

        if ($total <= 0) {
            throw new InvalidArgumentException('مجموع الحصص يجب أن يكون أكبر من صفر.');
        }

        $remainder = $this->minor;
        $shares = [];

        foreach ($ratios as $ratio) {
            $share = intdiv($this->minor * $ratio, $total);
            $shares[] = $share;
            $remainder -= $share;
        }

        // توزيع الباقي قرشاً قرشاً على الحصص الأولى
        for ($i = 0; $remainder > 0; $i++, $remainder--) {
            $shares[$i % count($shares)]++;
        }

        return array_map(fn (int $m): self => new self($m, $this->currency), $shares);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor > $other->minor;
    }

    /** القيمة العشرية كنص — لا تُعاد كـ float أبداً */
    public function toDecimal(): string
    {
        $decimals = self::decimalsFor($this->currency);
        $sign = $this->minor < 0 ? '-' : '';
        $abs = (string) abs($this->minor);

        if ($decimals === 0) {
            return $sign.$abs;
        }

        $abs = str_pad($abs, $decimals + 1, '0', STR_PAD_LEFT);

        return $sign.substr($abs, 0, -$decimals).'.'.substr($abs, -$decimals);
    }

    public function format(?string $locale = null): string
    {
        $symbol = self::symbolFor($this->currency);
        $number = number_format((float) $this->toDecimal(), self::decimalsFor($this->currency));

        return ($locale ?? app()->getLocale()) === 'ar'
            ? "{$number} {$symbol}"
            : "{$symbol} {$number}";
    }

    /** @return array{minor:int, currency:string, decimal:string} */
    public function jsonSerialize(): array
    {
        return ['minor' => $this->minor, 'currency' => $this->currency, 'decimal' => $this->toDecimal()];
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "لا يمكن الجمع بين عملتين مختلفتين: [{$this->currency}] و[{$other->currency}]. حوّل أولاً بسعر صرف مثبّت."
            );
        }
    }

    private static function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("رمز عملة غير صالح: [{$currency}] — المتوقع 3 أحرف ISO 4217.");
        }

        return $currency;
    }

    private static function decimalsFor(string $currency): int
    {
        return config("money.decimals.{$currency}", config('money.default_decimals', 2));
    }

    private static function symbolFor(string $currency): string
    {
        return config("money.symbols.{$currency}", $currency);
    }
}
