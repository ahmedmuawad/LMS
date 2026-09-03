<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Gateways\Drivers;

use App\Modules\Commerce\Gateways\PaymentIntent;
use App\Modules\Commerce\Models\Order;

/**
 * تحويل بنكي.
 *
 * يعتمده كثير من أولياء الأمور والسناتر. الطلب يبقى معلّقاً حتى
 * يعتمد المشترك الإيصال — ولا يُفتح المحتوى قبل الاعتماد.
 */
final class BankTransferGateway extends BaseGateway
{
    public function key(): string
    {
        return 'bank_transfer';
    }

    protected function defaultTitle(): string
    {
        return __('تحويل بنكي');
    }

    public function start(Order $order): PaymentIntent
    {
        return PaymentIntent::instructions(
            (string) ($this->setting('instructions') ?: __('حوّل المبلغ ثم ارفع صورة الإيصال ليُعتمد طلبك.')),
            reference: $order->number,
            meta: ['requires_receipt' => (bool) $this->setting('require_receipt', true)],
        );
    }
}
