<?php

declare(strict_types=1);

namespace App\Core\Theming;

/**
 * يبني كتلة المتغيّرات التي تعيد تعريف الطبقة الدلالية وحدها.
 * لا يمسّ المشترك الطبقة الأولية ولا أسماء المرافق، فيستحيل أن
 * يكسر تخصيصه اتساق الواجهة.
 */
final class BrandCss
{
    private const RADII = [
        'none' => '0px', 'sm' => '4px', 'md' => '8px', 'lg' => '14px', 'full' => '999px',
    ];

    public function render(): string
    {
        if (! tenancy()->initialized) {
            return '';
        }

        $declarations = [];

        if (filled($primary = setting('appearance.primary'))) {
            $palette = BrandPalette::fromHex((string) $primary);
            $declarations[] = "--sem-primary: {$palette->fill}";
            $declarations[] = "--sem-primary-hover: {$palette->hover}";
            $declarations[] = "--sem-primary-on: {$palette->on}";
            $declarations[] = '--sem-primary-subtle: color-mix(in oklab, '.$palette->fill.' 12%, var(--color-surface))';
        }

        if (filled($accent = setting('appearance.accent'))) {
            $palette = BrandPalette::fromHex((string) $accent);
            $declarations[] = "--sem-accent: {$palette->fill}";
            $declarations[] = "--sem-accent-on: {$palette->on}";
            $declarations[] = "--sem-accent-text: {$palette->text}";
            $declarations[] = '--sem-accent-subtle: color-mix(in oklab, '.$palette->fill.' 12%, var(--color-surface))';
        }

        if (filled($radius = setting('appearance.radius'))) {
            $value = self::RADII[$radius] ?? self::RADII['md'];
            $declarations[] = "--radius-md: {$value}";
            $declarations[] = '--radius-lg: '.($radius === 'full' ? '999px' : $value);
        }

        return $declarations === [] ? '' : ':root{'.implode(';', $declarations).'}';
    }

    /** الوضع الافتراضي الذي يُطبَّق قبل أي اختيار من الزائر. */
    public function defaultScheme(): string
    {
        return (string) setting('appearance.dark_mode', 'toggle');
    }
}
