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

        $root = $declarations === [] ? '' : ':root{'.implode(';', $declarations).'}';

        return $root.$this->customCss();
    }

    /**
     * CSS المشترك — كان يُحفَظ في الإعدادات ولا يُعرض إطلاقاً.
     *
     * ميزةٌ مُعلَنة في الباقات ومكتوبةٌ في شاشة المظهر، ولا سطر
     * منها يصل الصفحة: يكتب المشترك تخصيصه ويحفظه ولا يتغيّر شيء،
     * فيظنّ الحقل معطّلاً.
     *
     * ## والتعقيم ليس اختيارياً
     *
     * الكتلة تُطبَع داخل `<style>`، فنصٌّ فيه `</style><script>`
     * يخرج من الوسم ويصير جافاسكربت يعمل عند كل زائر. وهذا خطرٌ
     * حقيقي: موظّفٌ بصلاحية المظهر يستطيع أن يسرق جلسات طلبة
     * صاحب المنصة.
     *
     * فيُمنع كل ما يفتح وسماً أو يستدعي شبكة: `<`, `</style`,
     * `javascript:`, `expression(`, `@import`, `url(` — وآخرها
     * يمنع تسريب زيارات الطلبة إلى خادمٍ خارجي بصورة خلفية.
     */
    private function customCss(): string
    {
        if (! (tenant()?->allows('custom_css') ?? false)) {
            return '';
        }

        $css = trim((string) setting('appearance.custom_css'));

        if ($css === '') {
            return '';
        }

        $forbidden = ['<', '>', 'javascript:', 'expression(', '@import', 'url(', 'behavior:'];

        foreach ($forbidden as $needle) {
            if (mb_stripos($css, $needle) !== false) {
                return '';
            }
        }

        return mb_substr($css, 0, 20_000);
    }

    /** الوضع الافتراضي الذي يُطبَّق قبل أي اختيار من الزائر. */
    public function defaultScheme(): string
    {
        return (string) setting('appearance.dark_mode', 'toggle');
    }
}
