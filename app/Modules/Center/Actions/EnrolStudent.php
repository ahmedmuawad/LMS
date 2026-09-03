<?php

declare(strict_types=1);

namespace App\Modules\Center\Actions;

use App\Core\Support\Money;
use App\Modules\Center\Models\CenterEnrollment;
use App\Modules\Center\Models\Discount;
use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Student;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * تسجيل طالب في مجموعة.
 *
 * السعر يُجمَّد وقت التسجيل، والخصم يُحسب من خصومات الطالب السارية
 * — خصم الأخوة لا يُطلب من الموظف أن يتذكّره في كل مرة.
 */
final class EnrolStudent
{
    public function handle(Student $student, Group $group, ?int $customDiscountMinor = null, ?string $reason = null): CenterEnrollment
    {
        return DB::transaction(function () use ($student, $group, $customDiscountMinor, $reason): CenterEnrollment {
            $existing = CenterEnrollment::where('student_id', $student->getKey())
                ->where('group_id', $group->getKey())
                ->first();

            if ($existing !== null && $existing->status === 'active') {
                return $existing;
            }

            $fresh = Group::whereKey($group->getKey())->lockForUpdate()->first();

            if ($fresh->isFull() && $existing === null) {
                throw new RuntimeException(__('المجموعة مكتملة (:capacity طالباً).', ['capacity' => $fresh->capacity]));
            }

            if (! in_array($fresh->status, ['open', 'running'], true)) {
                throw new RuntimeException(__('هذه المجموعة غير مفتوحة للتسجيل.'));
            }

            $price = $fresh->price();
            $discount = $customDiscountMinor !== null
                ? Money::fromMinor($customDiscountMinor, $price->currency)
                : $this->autoDiscount($student, $fresh, $price);

            $enrollment = CenterEnrollment::updateOrCreate(
                ['student_id' => $student->getKey(), 'group_id' => $fresh->getKey()],
                [
                    'term_id' => $fresh->term_id,
                    'currency' => $price->currency,
                    'price_minor' => $price->minor,
                    'discount_minor' => $discount->minor,
                    'discount_reason' => $reason ?? ($discount->isZero() ? null : __('خصم تلقائي')),
                    'starts_at' => now()->toDateString(),
                    'status' => 'active',
                ],
            );

            $fresh->forceFill([
                'enrolled_count' => CenterEnrollment::where('group_id', $fresh->getKey())->active()->count(),
            ])->save();

            return $enrollment;
        });
    }

    public function drop(CenterEnrollment $enrollment, string $status = 'dropped'): CenterEnrollment
    {
        $enrollment->forceFill(['status' => $status, 'ends_at' => now()->toDateString()])->save();

        $group = $enrollment->group;

        $group?->forceFill([
            'enrolled_count' => CenterEnrollment::where('group_id', $group->getKey())->active()->count(),
        ])->save();

        return $enrollment;
    }

    private function autoDiscount(Student $student, Group $group, Money $price): Money
    {
        $discount = Discount::live()
            ->where('student_id', $student->getKey())
            ->where(fn ($q) => $q->whereNull('group_id')->orWhere('group_id', $group->getKey()))
            ->orderByDesc('value')
            ->first();

        return $discount?->amountOn($price) ?? Money::zero($price->currency);
    }
}
