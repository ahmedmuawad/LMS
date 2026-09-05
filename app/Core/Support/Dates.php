<?php

declare(strict_types=1);

namespace App\Core\Support;

use Illuminate\Support\Carbon;
use IntlDateFormatter;
use Throwable;

/**
 * التاريخ بتقويم المشترك، والأرقام بنظامه.
 *
 * ## لماذا الهجري ليس ترفاً
 *
 * السوق السعودي يعمل به: الإجازات والفصول الدراسية ومواعيد
 * الاختبارات تُعلَن هجرياً. ومنصّةٌ تعرض «١٥ مارس» لمن يفكّر بـ«٢٥
 * رمضان» تُجبره على الحساب في رأسه عند كل موعد.
 *
 * ## والاثنان معاً خيارٌ ثالث
 *
 * كثيرٌ من السعوديين يقرأ الهجري ويكتب الميلادي — فيُعرَض الاثنان:
 * «٢٥ رمضان ١٤٤٧ (١٥ مارس)». وإجبارُه على أحدهما يُفقده الآخر.
 *
 * ## والأرقام الهندية عرضٌ لا تخزين
 *
 * ١٢٣ و123 رقمٌ واحد؛ والتحويل عند العرض وحده. ولو خُزّنت هندية
 * لما صحّ فرزٌ ولا حساب — ولا قرأها نظامٌ آخر.
 */
final class Dates
{
    /** ما يُترك بالأرقام العربية دائماً: ما يُنسخ ويُقارَن ويُبحث به */
    private const KEEP_WESTERN = ['code', 'serial', 'number', 'id'];

    /**
     * تاريخٌ مقروء بتقويم المشترك.
     *
     * @param  string  $pattern  نمط ICU — مثل `d MMMM y`
     */
    public function format(?Carbon $date, string $pattern = 'd MMMM y'): string
    {
        if ($date === null) {
            return '';
        }

        $calendar = (string) (setting('locale.calendar', 'gregorian') ?: 'gregorian');

        $gregorian = $this->render($date, $pattern, false);

        if ($calendar === 'gregorian') {
            return $this->digits($gregorian);
        }

        $hijri = $this->render($date, $pattern, true);

        return $this->digits($calendar === 'hijri'
            ? $hijri
            : $hijri.' ('.$gregorian.')');
    }

    /**
     * التصيير بنمط PHP لا ICU.
     *
     * مواضع التواريخ كلّها مكتوبةٌ بنمط PHP (`j F Y`) لأنها كانت
     * تنادي `translatedFormat`. وترجمةُ النمط هنا أرخص من إعادة
     * كتابة اثنين وأربعين موضعاً — وأقلّ خطأً.
     */
    public function fromPhpPattern(?Carbon $date, string $pattern = 'j F Y'): string
    {
        return $this->format($date, $this->icuPattern($pattern));
    }

    /**
     * PHP → ICU، حرفاً حرفاً.
     *
     * والحروف التي لا نعرفها تُقتبَس لا تُمرَّر: حرفٌ لاتيني غير
     * مقتبَس في ICU رمزُ نمطٍ صامت — فـ«Q» تصير رقم الربع، وتظهر
     * في التاريخ أرقامٌ لم يطلبها أحد.
     */
    private function icuPattern(string $pattern): string
    {
        $map = [
            'j' => 'd', 'd' => 'dd', 'D' => 'EEE', 'l' => 'EEEE', 'N' => 'e',
            'F' => 'MMMM', 'M' => 'MMM', 'm' => 'MM', 'n' => 'M',
            'Y' => 'y', 'y' => 'yy',
            'H' => 'HH', 'G' => 'H', 'h' => 'hh', 'g' => 'h',
            'i' => 'mm', 's' => 'ss', 'a' => 'a', 'A' => 'a',
            'T' => 'zzz', 'e' => 'VV',
        ];

        $out = '';
        $literal = '';

        foreach (mb_str_split($pattern) as $char) {
            if (isset($map[$char])) {
                if ($literal !== '') {
                    $out .= "'".str_replace("'", "''", $literal)."'";
                    $literal = '';
                }

                $out .= $map[$char];

                continue;
            }

            // حرفٌ لاتيني غير معروف: يُقتبَس كي لا يفسّره ICU نمطاً
            $literal .= $char;
        }

        if ($literal !== '') {
            $out .= "'".str_replace("'", "''", $literal)."'";
        }

        return $out;
    }

    /** التاريخ والوقت معاً */
    public function formatWithTime(?Carbon $date, string $pattern = 'd MMMM y · HH:mm'): string
    {
        return $this->format($date, $pattern);
    }

    /**
     * يحوّل الأرقام إلى الهندية إن اختارها المشترك.
     *
     * ولا يمسّ ما بين حروفٍ لاتينية: `A1` رمزُ قاعةٍ لا رقم، وتحويلُه
     * يجعله لا يُقرأ ولا يُبحث عنه.
     */
    public function digits(string $text): string
    {
        if (setting('locale.numerals', 'western') !== 'eastern') {
            return $text;
        }

        return strtr($text, [
            '0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
            '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩',
        ]);
    }

    /** ما لا يُحوَّل: الرموز والمعرّفات */
    public function isIdentifier(string $field): bool
    {
        foreach (self::KEEP_WESTERN as $keep) {
            if (str_contains(mb_strtolower($field), $keep)) {
                return true;
            }
        }

        return false;
    }

    /**
     * التصيير بـICU — وبالاحتياط إلى Carbon إن غابت الإضافة.
     *
     * `intl` مثبّتة عندنا، لكنّ المشترك قد يستضيف بنفسه يوماً؛
     * وسقوطُ كل تاريخ في المنصّة لأجل إضافةٍ غائبة ثمنٌ لا يُحتمل.
     */
    private function render(Carbon $date, string $pattern, bool $hijri): string
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';

        if (! class_exists(IntlDateFormatter::class)) {
            return $date->locale($locale)->translatedFormat($this->carbonPattern($pattern));
        }

        try {
            $formatter = new IntlDateFormatter(
                $locale.'@calendar='.($hijri ? 'islamic-umalqura' : 'gregorian'),
                IntlDateFormatter::FULL,
                IntlDateFormatter::NONE,
                $date->getTimezone()->getName(),
                $hijri ? IntlDateFormatter::TRADITIONAL : IntlDateFormatter::GREGORIAN,
                $pattern,
            );

            return (string) $formatter->format($date);
        } catch (Throwable) {
            return $date->locale($locale)->translatedFormat($this->carbonPattern($pattern));
        }
    }

    /** ترجمةٌ تقريبية من نمط ICU إلى نمط PHP — للاحتياط وحده */
    private function carbonPattern(string $pattern): string
    {
        return strtr($pattern, [
            'MMMM' => 'F', 'MMM' => 'M', 'MM' => 'm',
            'dd' => 'd', 'd' => 'j', 'y' => 'Y',
            'HH' => 'H', 'mm' => 'i', 'EEEE' => 'l',
        ]);
    }
}
