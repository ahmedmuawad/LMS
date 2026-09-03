<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Gateways;

use App\Core\Support\Money;
use App\Modules\Commerce\Models\Order;
use Illuminate\Http\Request;

/**
 * ADR-002 — عقد البوابة.
 *
 * كل بوابة بلجن مستقل يُنفّذ هذا العقد. النواة لا تعرف Paymob من
 * Stripe، ولا تعرف إن كانت البوابة تُحوّل المستخدم أم تُنشئ كوداً
 * أم تنتظر إيصالاً — تعرف فقط: ابدأ الدفع، ثم أخبرني بالنتيجة.
 */
interface PaymentGateway
{
    public function key(): string;

    /** هل هي مفعّلة ومهيّأة فعلاً لهذا المشترك؟ */
    public function isReady(): bool;

    public function supports(Money $amount, ?string $country = null): bool;

    /**
     * بدء الدفع.
     *
     * @return PaymentIntent ما يفعله المتصفح بعدها: تحويل · نموذج · تعليمات
     */
    public function start(Order $order): PaymentIntent;

    /**
     * معالجة ردّ البوابة (webhook أو عودة المستخدم).
     * تُعيد null إن لم يكن الردّ مفهوماً — ولا تُغيّر شيئاً عندئذٍ.
     */
    public function handleCallback(Request $request): ?PaymentResult;
}
