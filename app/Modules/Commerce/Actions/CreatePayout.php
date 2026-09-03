<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Actions;

use App\Core\Support\Money;
use App\Models\User;
use App\Modules\Commerce\Models\InstructorEarning;
use App\Modules\Commerce\Models\Payout;
use App\Modules\Lms\Models\Instructor;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * تجميع مستحقات مدرّس في تحويل واحد.
 *
 * القيود السالبة (المرتجعات) تدخل في الحساب: تجاهلها يعني تحويل
 * مبلغ يفوق ما استحقّه فعلاً.
 */
final class CreatePayout
{
    public function handle(Instructor $instructor, string $method, array $destination = [], ?User $creator = null): Payout
    {
        return DB::transaction(function () use ($instructor, $method, $destination, $creator): Payout {
            $earnings = InstructorEarning::readyToPay()
                ->where('instructor_id', $instructor->getKey())
                ->lockForUpdate()
                ->get();

            if ($earnings->isEmpty()) {
                throw new RuntimeException(__('لا مستحقات ناضجة لهذا المدرّس.'));
            }

            $currency = (string) $earnings->first()->currency;
            $total = $earnings->sum('amount_minor');

            if ($total <= 0) {
                throw new RuntimeException(__('رصيد المدرّس صفر أو سالب بعد المرتجعات.'));
            }

            $payout = Payout::create([
                'reference' => 'PAY-'.now()->format('Ym').'-'.str_pad((string) (Payout::count() + 1), 4, '0', STR_PAD_LEFT),
                'instructor_id' => $instructor->getKey(),
                'currency' => $currency,
                'amount_minor' => (int) $total,
                'status' => 'pending',
                'method' => $method,
                'destination' => $destination ?: $instructor->payout_method,
                'created_by' => $creator?->getKey(),
            ]);

            InstructorEarning::whereIn('id', $earnings->pluck('id'))
                ->update(['payout_id' => $payout->getKey(), 'status' => 'paid']);

            return $payout;
        });
    }

    public function markPaid(Payout $payout, ?string $reference = null): Payout
    {
        $payout->forceFill([
            'status' => 'paid',
            'transaction_ref' => $reference,
            'paid_at' => now(),
        ])->save();

        return $payout;
    }

    public function balanceFor(Instructor $instructor): Money
    {
        $currency = (string) (tenant('currency') ?? 'EGP');

        $minor = (int) InstructorEarning::readyToPay()
            ->where('instructor_id', $instructor->getKey())
            ->sum('amount_minor');

        return Money::fromMinor(max(0, $minor), $currency);
    }
}
