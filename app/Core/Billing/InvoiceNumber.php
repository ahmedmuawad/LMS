<?php

declare(strict_types=1);

namespace App\Core\Billing;

use App\Core\Billing\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * ترقيم متسلسل غير قابل للتلاعب.
 *
 * نأخذه داخل معاملة مع قفل، لا بـ max()+1: طلبان متزامنان
 * يقرآن نفس الرقم فتُصدَر فاتورتان برقم واحد — وهذه مشكلة
 * محاسبية لا تُكتشف إلا عند المراجعة.
 */
final class InvoiceNumber
{
    public static function next(string $prefix = 'INV', ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $scope = $prefix.'-'.$year.'-';

        return DB::connection(config('tenancy.database.central_connection'))->transaction(function () use ($scope): string {
            $last = Invoice::query()
                ->where('number', 'like', $scope.'%')
                ->lockForUpdate()
                ->orderByDesc('number')
                ->value('number');

            $sequence = $last === null ? 1 : ((int) substr($last, strlen($scope))) + 1;

            return $scope.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        });
    }
}
