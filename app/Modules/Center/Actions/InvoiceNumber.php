<?php

declare(strict_types=1);

namespace App\Modules\Center\Actions;

use App\Modules\Center\Models\Invoice;
use Illuminate\Support\Facades\DB;

/** ترقيم متسلسل غير قابل للتلاعب — شرط محاسبي لا تقني. */
final class InvoiceNumber
{
    public static function next(): string
    {
        $prefix = 'CINV-'.now()->format('Y').'-';

        return DB::transaction(function () use ($prefix): string {
            $last = Invoice::where('number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('number')
                ->value('number');

            $sequence = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

            return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        });
    }
}
