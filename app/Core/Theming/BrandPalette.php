<?php

declare(strict_types=1);

namespace App\Core\Theming;

/**
 * ADR-013 — تخصيص المشترك لا يجوز أن يكسر التباين.
 *
 * المشترك يختار لوناً واحداً، ونحن نشتقّ منه بقية الدرجات ونضبطه
 * حتى يبلغ 4.5:1 مع النص فوقه. لون علامة تجارية جميل بنصّ لا يُقرأ
 * ليس خياراً نتركه لأحد.
 */
final class BrandPalette
{
    private const AA = 4.5;

    public function __construct(
        public readonly string $fill,
        public readonly string $hover,
        public readonly string $on,
        public readonly string $text,
    ) {}

    public static function fromHex(string $hex): self
    {
        $rgb = self::toRgb($hex) ?? [18, 112, 126];

        // يُغمَّق حتى يقرأ النص الأبيض فوقه — الأبيض هو نصّ الأزرار عندنا
        $fill = $rgb;
        for ($i = 0; $i < 24 && self::ratio($fill, [255, 255, 255]) < self::AA; $i++) {
            $fill = self::scale($fill, 0.92);
        }

        // ولنصّ العلامة فوق خلفية فاتحة نحتاج درجة أغمق بعد
        $text = $fill;
        for ($i = 0; $i < 24 && self::ratio($text, [255, 255, 255]) < 7.0; $i++) {
            $text = self::scale($text, 0.92);
        }

        return new self(
            fill: self::toHex($fill),
            hover: self::toHex(self::scale($fill, 0.86)),
            on: '#FFFFFF',
            text: self::toHex($text),
        );
    }

    /** @return array{0:int,1:int,2:int}|null */
    private static function toRgb(string $hex): ?array
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return null;
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** @param  array{0:int,1:int,2:int}  $rgb */
    private static function toHex(array $rgb): string
    {
        return sprintf('#%02X%02X%02X', ...array_map(fn (int $c): int => max(0, min(255, $c)), $rgb));
    }

    /** @param  array{0:int,1:int,2:int}  $rgb */
    private static function scale(array $rgb, float $factor): array
    {
        return array_map(fn (int $c): int => (int) round($c * $factor), $rgb);
    }

    /** @param  array{0:int,1:int,2:int}  $rgb */
    private static function luminance(array $rgb): float
    {
        $channels = array_map(function (int $c): float {
            $v = $c / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * @param  array{0:int,1:int,2:int}  $a
     * @param  array{0:int,1:int,2:int}  $b
     */
    public static function ratio(array $a, array $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    public static function contrast(string $a, string $b): float
    {
        return self::ratio(self::toRgb($a) ?? [0, 0, 0], self::toRgb($b) ?? [255, 255, 255]);
    }
}
