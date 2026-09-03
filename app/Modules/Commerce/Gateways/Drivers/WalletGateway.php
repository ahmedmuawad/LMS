<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Gateways\Drivers;

use App\Core\Support\Money;
use App\Modules\Commerce\Actions\RecordOrderPayment;
use App\Modules\Commerce\Gateways\PaymentIntent;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * محفظة الموقع.
 *
 * الخصم يقرأ الرصيد بقفل داخل معاملة: طلبان متزامنان بنفس الرصيد
 * كانا سيمرّان معاً فيصبح الرصيد سالباً.
 */
final class WalletGateway extends BaseGateway
{
    public function __construct(private readonly RecordOrderPayment $payments) {}

    public function key(): string
    {
        return 'wallet';
    }

    protected function defaultTitle(): string
    {
        return __('رصيد المحفظة');
    }

    public function start(Order $order): PaymentIntent
    {
        if ($order->user_id === null) {
            throw new RuntimeException(__('المحفظة تحتاج حساباً مسجّلاً.'));
        }

        $total = $order->total();

        DB::transaction(function () use ($order, $total): void {
            $balance = WalletTransaction::where('user_id', $order->user_id)
                ->where('currency', $order->currency)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('balance_after_minor');

            $balance = Money::fromMinor((int) $balance, $order->currency);

            if ($balance->minor < $total->minor) {
                throw new RuntimeException(__('رصيدك (:balance) لا يكفي لهذا الطلب.', [
                    'balance' => $balance->format(),
                ]));
            }

            WalletTransaction::create([
                'user_id' => $order->user_id,
                'type' => 'debit',
                'currency' => $order->currency,
                'amount_minor' => -$total->minor,
                'balance_after_minor' => $balance->minus($total)->minor,
                'source' => 'order',
                'reference' => $order->number,
            ]);
        });

        $this->payments->handle($order, $total, $this->key(), $order->number);

        return PaymentIntent::done($order->number);
    }
}
