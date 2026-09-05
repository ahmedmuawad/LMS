<?php

declare(strict_types=1);

namespace App\Core\Billing\Gateways;

use App\Core\Billing\Models\Invoice;
use App\Core\Billing\PlatformSettings;
use App\Modules\Commerce\Gateways\PaymentIntent;
use Illuminate\Http\Request;

/**
 * تحويل يدوي: إنستاباي · حساب بنكي · محفظة إلكترونية.
 *
 * ثلاث طرائق بصنف واحد لأن مسارها واحد: يحوّل العميل، ويُرسل رقم
 * العملية، ونعتمدها من اللوحة العليا. والفرق بينها بيانات تُعرض —
 * وهي في الإعدادات لا في الكود: رقم حساب يتغيّر لا يحتاج نشراً.
 *
 * ولا نُفعّل اشتراكاً على وعدٍ بتحويل: الفاتورة تبقى مفتوحة حتى
 * يعتمدها الفريق. والمنصّة تعمل مع ذلك في تجربتها، فلا ينتظر
 * المشترك يوماً ليبدأ.
 */
final class ManualTransferGateway implements PlatformGateway
{
    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly string $method = 'bank',
    ) {}

    public function key(): string
    {
        return $this->method;
    }

    public function title(): string
    {
        $custom = $this->settings->get($this->method.'.title');

        if (filled($custom)) {
            return (string) $custom;
        }

        return match ($this->method) {
            'instapay' => __('إنستاباي'),
            'wallet' => __('محفظة إلكترونية'),
            default => __('تحويل بنكي'),
        };
    }

    public function description(): string
    {
        $custom = $this->settings->get($this->method.'.description');

        if (filled($custom)) {
            return (string) $custom;
        }

        return match ($this->method) {
            'instapay' => __('حوّل على عنوان إنستاباي ثم أرسل رقم العملية — نعتمده خلال يوم عمل.'),
            'wallet' => __('حوّل على رقم المحفظة ثم أرسل رقم العملية — نعتمده خلال يوم عمل.'),
            default => __('حوّل على الحساب البنكي ثم أرسل رقم العملية — نعتمده خلال يوم عمل.'),
        };
    }

    public function isReady(): bool
    {
        return $this->settings->methodReady($this->method);
    }

    public function start(Invoice $invoice, string $returnUrl): PaymentIntent
    {
        return PaymentIntent::instructions(
            $this->instructions(),
            $invoice->number,
            $this->details($invoice),
        );
    }

    /** لا ردّ آلياً: الاعتماد بيد فريقنا بعد أن يرى التحويل. */
    public function handleCallback(Request $request): ?PlatformPaymentResult
    {
        return null;
    }

    /** @return array<string, string> ما يُعرض للعميل ليحوّل */
    public function details(Invoice $invoice): array
    {
        $rows = match ($this->method) {
            'instapay' => [
                __('عنوان إنستاباي') => $this->settings->get('instapay.address'),
                __('الاسم') => $this->settings->get('instapay.name'),
            ],
            'wallet' => [
                __('رقم المحفظة') => $this->settings->get('wallet.number'),
                __('الاسم') => $this->settings->get('wallet.name'),
                __('المشغّل') => $this->settings->get('wallet.provider'),
            ],
            default => [
                __('البنك') => $this->settings->get('bank.name'),
                __('اسم الحساب') => $this->settings->get('bank.account_name'),
                __('رقم الحساب') => $this->settings->get('bank.account_number'),
                __('الآيبان') => $this->settings->get('bank.iban'),
                __('السويفت') => $this->settings->get('bank.swift'),
            ],
        };

        // المبلغ ورقم الفاتورة مع البيانات: من يحوّل بلا مرجع يضيع تحويله
        $rows[__('المبلغ')] = $invoice->total()->format();
        $rows[__('رقم الفاتورة')] = $invoice->number;

        return array_filter($rows, fn ($value): bool => filled($value));
    }

    private function instructions(): string
    {
        $custom = $this->settings->get($this->method.'.instructions');

        return filled($custom)
            ? (string) $custom
            : __('حوّل المبلغ إلى البيانات أدناه، واذكر رقم الفاتورة في الملاحظات، ثم أرسل لنا رقم العملية.');
    }
}
