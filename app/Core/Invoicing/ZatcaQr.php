<?php

declare(strict_types=1);

namespace App\Core\Invoicing;

use App\Core\Support\Money;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Carbon;

/**
 * رمز الفاتورة المبسّطة — هيئة الزكاة والضريبة السعودية.
 *
 * ## ما هذا بالضبط
 *
 * «المرحلة الأولى» من الفوترة الإلكترونية السعودية تشترط رمزاً على
 * كل فاتورةٍ مبسّطة يحمل خمسة حقول بترميز TLV مُشفَّرٍ بـBase64.
 * ولا واجهةَ فيها ولا مفاتيح: تُحسَب عندنا وتُطبَع، ويقرؤها مفتّشُ
 * الهيئة بتطبيقه.
 *
 * ## ولهذا تعمل اليوم
 *
 * المرحلة الثانية تشترط ربطاً بالهيئة وشهادةَ توقيع يستخرجها
 * المشترك باسمه — بابٌ لا نملك فتحه له. أمّا الأولى فلا تشترط
 * شيئاً منه سوى رقمه الضريبي، وهو في إعداداته أصلاً.
 *
 * ## وTLV لا JSON
 *
 * الهيئة تقرأ بايتاتٍ بترتيبٍ محدّد: رقم الحقل، ثم طوله، ثم قيمته.
 * وأيّ ترميزٍ آخر يُنتج رمزاً يُمسَح ولا يُقرأ — ولا يظهر الخطأ إلا
 * عند المفتّش.
 */
final class ZatcaQr
{
    /** ترتيب الحقول ملزَم: الهيئة تقرأ بالرقم لا بالاسم */
    private const SELLER = 1;

    private const VAT_NUMBER = 2;

    private const TIMESTAMP = 3;

    private const TOTAL = 4;

    private const VAT_AMOUNT = 5;

    /** هل تُطبع الفاتورة برمز الهيئة أصلاً؟ */
    public function enabled(): bool
    {
        return (bool) setting('currency.zatca_qr', false)
            && filled(setting('currency.tax_number'));
    }

    /**
     * الحمولة المُرمَّزة — Base64 لسلسلة TLV.
     *
     * @param  Money  $total  الإجمالي شاملاً الضريبة
     * @param  Money  $vat  مقدار الضريبة وحده
     */
    public function payload(Money $total, Money $vat, ?Carbon $issuedAt = null): string
    {
        $seller = (string) (setting()->translated('currency.company_name') ?: site_name());

        $tlv = $this->tag(self::SELLER, $seller)
            .$this->tag(self::VAT_NUMBER, (string) setting('currency.tax_number'))

            /*
             | الوقت بصيغة ISO-8601 بتوقيت المشترك.
             |
             | والهيئة تقبل الإزاحة؛ لكنّ فاتورةً بتوقيت UTC تظهر
             | للمفتّش بثلاث ساعاتٍ قبل بيعها في الرياض.
             */
            .$this->tag(self::TIMESTAMP, ($issuedAt ?? now())->toIso8601String())

            .$this->tag(self::TOTAL, $total->toDecimal())
            .$this->tag(self::VAT_AMOUNT, $vat->toDecimal());

        return base64_encode($tlv);
    }

    /** الرمز نفسه — SVG يُطبع بلا طلبٍ خارجي */
    public function svg(Money $total, Money $vat, ?Carbon $issuedAt = null): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(160, 0), new SvgImageBackEnd));

        return $writer->writeString($this->payload($total, $vat, $issuedAt));
    }

    /**
     * حقلٌ واحد: رقمه، ثم طوله بالبايت، ثم قيمته.
     *
     * والطول بالبايت لا بالحرف: اسمُ شركةٍ عربي يزن ضعف حروفه،
     * وطولٌ محسوبٌ بالحروف يجعل القارئ يقف في منتصف الاسم فيقرأ
     * الحقول التالية خطأً — والرمز يبدو سليماً حتى يُمسَح.
     */
    private function tag(int $tag, string $value): string
    {
        return chr($tag).chr(strlen($value)).$value;
    }
}
