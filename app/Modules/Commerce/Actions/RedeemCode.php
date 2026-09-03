<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Actions;

use App\Models\User;
use App\Modules\Commerce\Models\RechargeCode;
use App\Modules\Commerce\Models\WalletTransaction;
use App\Modules\Lms\Actions\EnrollStudent;
use App\Modules\Lms\Models\Course;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * استهلاك كود الشحن.
 *
 * القفل داخل معاملة شرط لا رفاهية: كودٌ واحد يُرسَل مرتين في
 * لحظتين متقاربتين يُشحن مرتين لولاه.
 */
final class RedeemCode
{
    public function __construct(private readonly EnrollStudent $enrol) {}

    public function handle(User $user, string $code): RechargeCode
    {
        $normalised = mb_strtoupper(trim($code));

        return DB::transaction(function () use ($user, $normalised): RechargeCode {
            $record = RechargeCode::where('code', $normalised)->lockForUpdate()->first();

            if ($record === null) {
                throw new RuntimeException(__('هذا الكود غير موجود.'));
            }

            if ($record->status === 'used') {
                throw new RuntimeException(__('استُخدم هذا الكود من قبل.'));
            }

            if ($record->status === 'void') {
                throw new RuntimeException(__('أُلغي هذا الكود.'));
            }

            if (! $record->isRedeemable()) {
                throw new RuntimeException(__('انتهت صلاحية هذا الكود.'));
            }

            match ($record->type) {
                'wallet' => $this->credit($user, $record),
                'course', 'bundle' => $this->unlock($user, $record),
                default => throw new RuntimeException(__('نوع كود غير مدعوم.')),
            };

            $record->forceFill([
                'status' => 'used',
                'used_by' => $user->getKey(),
                'used_at' => now(),
            ])->save();

            return $record;
        });
    }

    private function credit(User $user, RechargeCode $record): void
    {
        $currency = $record->currency ?? (string) (tenant('currency') ?? 'EGP');
        $balance = WalletTransaction::balanceFor((int) $user->getKey(), $currency);
        $amount = $record->value();

        WalletTransaction::create([
            'user_id' => $user->getKey(),
            'type' => 'credit',
            'currency' => $currency,
            'amount_minor' => $amount->minor,
            'balance_after_minor' => $balance->plus($amount)->minor,
            'source' => 'code',
            'reference' => $record->code,
        ]);
    }

    private function unlock(User $user, RechargeCode $record): void
    {
        $course = Course::find($record->course_id);

        if ($course === null) {
            throw new RuntimeException(__('الكورس المرتبط بهذا الكود لم يعد موجوداً.'));
        }

        $this->enrol->handle($user, $course, 'code');
    }
}
