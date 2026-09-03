<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Actions;

use App\Modules\Commerce\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * ترقيم متسلسل داخل معاملة بقفل — لا max()+1.
 * طلبان متزامنان يقرآن نفس الرقم فيتصادمان على القيد الفريد.
 */
final class OrderNumber
{
    public static function next(): string
    {
        $prefix = (string) setting('commerce.order_prefix', 'ORD-');
        $start = (int) setting('commerce.order_start', 1000);

        return DB::transaction(function () use ($prefix, $start): string {
            $last = Order::where('number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByRaw('LENGTH(number) desc, number desc')
                ->value('number');

            $sequence = $last === null ? $start : ((int) substr($last, strlen($prefix))) + 1;

            return $prefix.$sequence;
        });
    }
}
