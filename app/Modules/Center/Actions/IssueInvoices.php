<?php

declare(strict_types=1);

namespace App\Modules\Center\Actions;

use App\Modules\Center\Models\CenterEnrollment;
use App\Modules\Center\Models\FeePlan;
use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * إصدار فواتير الفترة لكل مسجّل.
 *
 * الفاتورة لا تتكرّر لنفس الفترة: تشغيل الإصدار مرتين في نفس اليوم
 * لا يُطالب الطالب بقسطين — وهذا يحدث فعلاً كل شهر.
 */
final class IssueInvoices
{
    /**
     * فاتورة تسجيلٍ واحد — تُستدعى لحظة التسجيل لا في دورةٍ شهرية.
     *
     * تُعيد الفاتورة القائمة إن وُجدت لهذه الفترة: تسجيلٌ يُعاد
     * تفعيله بعد انقطاع لا يُنشئ قسطاً ثانياً لنفس الشهر.
     */
    public function forEnrolment(CenterEnrollment $enrolment, ?string $period = null): ?Invoice
    {
        $period ??= now()->format('Y-m');

        $existing = Invoice::where('enrollment_id', $enrolment->getKey())
            ->where('period', $period)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $net = $enrolment->netPrice();

        if ($net->isZero()) {
            return null;
        }

        $plan = FeePlan::where('group_id', $enrolment->group_id)->where('is_active', true)->first();
        $dueDay = (int) ($plan?->due_day ?? 1);

        /*
         | المقدَّم يُستحقّ اليوم لا أول الشهر.
         |
         | من سجّل في العشرين لا يُقال له إن قسطه كان مستحقّاً في
         | الأول فصار متأخّراً قبل أن يبدأ.
         */
        $dueOn = Carbon::parse($period.'-01')->day(min($dueDay, 28));
        $dueOn = $dueOn->isPast() ? now() : $dueOn;

        return Invoice::create([
            'number' => InvoiceNumber::next(),
            'student_id' => $enrolment->student_id,
            'group_id' => $enrolment->group_id,
            'enrollment_id' => $enrolment->getKey(),
            'period' => $period,
            'currency' => $net->currency,
            'amount_minor' => (int) $enrolment->price_minor,
            'discount_minor' => (int) $enrolment->discount_minor,
            'total_minor' => $net->minor,
            'due_on' => $dueOn->toDateString(),
            'status' => 'unpaid',
        ]);
    }

    /** @return array{issued:int, skipped:int} */
    public function handle(Group $group, ?string $period = null): array
    {
        $period ??= now()->format('Y-m');
        $plan = FeePlan::where('group_id', $group->getKey())->where('is_active', true)->first();
        $dueDay = (int) ($plan?->due_day ?? 1);
        $dueOn = Carbon::parse($period.'-01')->day(min($dueDay, 28));

        $issued = 0;
        $skipped = 0;

        DB::transaction(function () use ($group, $period, $dueOn, &$issued, &$skipped): void {
            foreach ($group->enrollments()->active()->with('student')->get() as $enrollment) {
                $exists = Invoice::where('enrollment_id', $enrollment->getKey())
                    ->where('period', $period)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                $net = $enrollment->netPrice();

                Invoice::create([
                    'number' => InvoiceNumber::next(),
                    'student_id' => $enrollment->student_id,
                    'group_id' => $group->getKey(),
                    'enrollment_id' => $enrollment->getKey(),
                    'period' => $period,
                    'currency' => $net->currency,
                    'amount_minor' => (int) $enrollment->price_minor,
                    'discount_minor' => (int) $enrollment->discount_minor,
                    'total_minor' => $net->minor,
                    'due_on' => $dueOn->toDateString(),
                    'status' => $net->isZero() ? 'paid' : 'unpaid',
                ]);

                $issued++;
            }
        });

        return ['issued' => $issued, 'skipped' => $skipped];
    }

    /**
     * غرامة التأخير تُضاف مرة واحدة بعد انقضاء مهلة السماح.
     * إضافتها كل يوم تُحوّل قسطاً متأخراً إلى دَين لا يُسدَّد.
     */
    public function applyLateFees(Group $group): int
    {
        $plan = FeePlan::where('group_id', $group->getKey())->where('is_active', true)->first();

        if ($plan === null || $plan->late_fee_percent <= 0) {
            return 0;
        }

        $applied = 0;

        foreach (Invoice::where('group_id', $group->getKey())->overdue()->where('late_fee_minor', 0)->get() as $invoice) {
            $fee = $plan->lateFeeOn($invoice->total(), $invoice->daysLate());

            if ($fee->isZero()) {
                continue;
            }

            $invoice->forceFill([
                'late_fee_minor' => $fee->minor,
                'total_minor' => $invoice->total_minor + $fee->minor,
            ])->save();

            $applied++;
        }

        return $applied;
    }
}
