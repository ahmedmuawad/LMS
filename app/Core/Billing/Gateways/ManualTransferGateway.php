<?php

declare(strict_types=1);

namespace App\Core\Billing\Gateways;

use App\Core\Billing\Models\Invoice;
use App\Modules\Commerce\Gateways\PaymentIntent;
use Illuminate\Http\Request;

/**
 * تحويل بنكي أو محفظة — يُراجَع بيد فريقنا.
 *
 * الفاتورة تبقى مفتوحة حتى يعتمدها الفريق من اللوحة العليا: لا
 * نُفعّل اشتراكاً على وعدٍ بتحويل. والمنصّة تُجهَّز مع ذلك، فيدخل
 * المشترك ويعمل في فترة تجربته ريثما يصل التحويل.
 */
final class ManualTransferGateway implements PlatformGateway
{
    public function key(): string
    {
        return 'manual';
    }

    public function title(): string
    {
        return __('تحويل بنكي أو محفظة');
    }

    public function description(): string
    {
        return __('حوّل المبلغ ثم أرسل الإيصال — نعتمده خلال يوم عمل، وتعمل منصّتك من الآن.');
    }

    public function isReady(): bool
    {
        $config = config('platform-billing.manual', []);

        // لا نعرض تحويلاً بلا حساب يُحوَّل إليه
        return ($config['enabled'] ?? false)
            && (filled($config['account_number'] ?? null) || filled($config['iban'] ?? null) || filled($config['wallet'] ?? null));
    }

    public function start(Invoice $invoice, string $returnUrl): PaymentIntent
    {
        $config = config('platform-billing.manual', []);

        return PaymentIntent::instructions(
            $config['instructions'] ?: __('حوّل المبلغ إلى الحساب أدناه واذكر رقم الفاتورة في خانة الملاحظات.'),
            $invoice->number,
            array_filter([
                __('البنك') => $config['bank'] ?? null,
                __('اسم الحساب') => $config['account_name'] ?? null,
                __('رقم الحساب') => $config['account_number'] ?? null,
                __('الآيبان') => $config['iban'] ?? null,
                __('محفظة') => $config['wallet'] ?? null,
                __('رقم الفاتورة') => $invoice->number,
            ]),
        );
    }

    /** لا ردّ آلياً: الاعتماد يدويّ من اللوحة العليا. */
    public function handleCallback(Request $request): ?PlatformPaymentResult
    {
        return null;
    }
}
