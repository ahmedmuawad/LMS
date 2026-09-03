<?php

declare(strict_types=1);

namespace App\Modules\Center\Actions;

use App\Core\Support\Money;
use App\Models\User;
use App\Modules\Center\Models\Cashbox;
use App\Modules\Center\Models\CashClosing;
use App\Modules\Center\Models\CashMovement;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * تقفيل الخزنة اليومي.
 *
 * الفرق بين المتوقَّع والمعدود يُسجَّل ويُبرَّر ولا يُطمس، ولا
 * تُعدَّل الحركات لتُطابق العدّ — التسوية الصامتة تُخفي التسرّب.
 */
final class CloseCashbox
{
    public function handle(Cashbox $cashbox, Money $counted, ?User $closer = null, ?string $explanation = null, ?Carbon $date = null): CashClosing
    {
        $date ??= now();
        $expected = $this->expectedFor($cashbox, $date);
        $difference = $counted->minor - $expected->minor;

        if ($difference !== 0 && blank($explanation)) {
            throw new RuntimeException(__('الفرق :amount يحتاج تفسيراً مكتوباً.', [
                'amount' => Money::fromMinor(abs($difference), $cashbox->currency)->format(),
            ]));
        }

        return CashClosing::updateOrCreate(
            ['cashbox_id' => $cashbox->getKey(), 'closed_on' => $date->toDateString()],
            [
                'expected_minor' => $expected->minor,
                'counted_minor' => $counted->minor,
                'difference_minor' => $difference,
                'explanation' => $explanation,
                'closed_by' => $closer?->getKey(),
            ],
        );
    }

    /** المتوقَّع = رصيد آخر تقفيل + حركات اليوم. */
    public function expectedFor(Cashbox $cashbox, ?Carbon $date = null): Money
    {
        $date ??= now();

        $opening = CashClosing::where('cashbox_id', $cashbox->getKey())
            ->whereDate('closed_on', '<', $date)
            ->orderByDesc('closed_on')
            ->value('counted_minor') ?? (int) $cashbox->opening_minor;

        $movements = (int) CashMovement::where('cashbox_id', $cashbox->getKey())
            ->whereDate('created_at', $date)
            ->sum('amount_minor');

        return Money::fromMinor((int) $opening + $movements, $cashbox->currency);
    }
}
