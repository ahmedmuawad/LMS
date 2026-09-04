<?php

declare(strict_types=1);

namespace App\Core\Billing\Gateways;

use App\Core\Billing\Models\Invoice;
use App\Modules\Commerce\Gateways\PaymentIntent;
use Illuminate\Http\Request;

/**
 * عقد بوابة اشتراكاتنا.
 *
 * يشبه عقد بوابات المشترك ويختلف في مدخله: تلك تبدأ من `Order` في
 * قاعدة المشترك، وهذه من `Invoice` مركزية — وقت الدفع لا مشترك بعد.
 */
interface PlatformGateway
{
    public function key(): string;

    public function title(): string;

    /** وصف يُقرأ في شاشة الدفع — كيف تصل النقود ومتى تُفعَّل المنصّة. */
    public function description(): string;

    /** هل مفاتيحها مضبوطة فعلاً؟ ما ليس مهيّأً لا يُعرض. */
    public function isReady(): bool;

    /** ما يفعله المتصفّح بعد الاختيار: تحويل إلى البوابة أو تعليمات تحويل. */
    public function start(Invoice $invoice, string $returnUrl): PaymentIntent;

    /**
     * ردّ البوابة عند عودة المستخدم أو عبر webhook.
     * تُعيد null إن لم يكن الردّ مفهوماً — فلا يُغيَّر شيء عندئذٍ.
     */
    public function handleCallback(Request $request): ?PlatformPaymentResult;
}
