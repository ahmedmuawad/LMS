<?php

declare(strict_types=1);

namespace App\Modules\Center\Actions;

use App\Models\User;
use App\Modules\Center\Models\InventoryItem;
use App\Modules\Center\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * حركةٌ على المخزون — وتحديث الرصيد معها.
 *
 * ## الاثنان في معاملة واحدة
 *
 * حركةٌ تُكتب بلا تحديث الرصيد تجعل القائمة تكذب، ورصيدٌ يُحدَّث
 * بلا حركة يجعل الجرد لا يُفسَّر. فإمّا معاً وإمّا لا.
 *
 * ## والرصيد لا ينزل تحت الصفر
 *
 * بيعُ ما ليس في المخزن خطأُ إدخالٍ لا واقعةٌ تُسجَّل — والسماح به
 * يُنتج جرداً بالسالب لا يُصلحه إلا يدٌ في القاعدة.
 */
final class MoveStock
{
    /** @throws RuntimeException */
    public function handle(
        InventoryItem $item,
        string $type,
        int $quantity,
        ?int $studentId = null,
        ?int $staffId = null,
        ?string $reason = null,
        ?User $by = null,
    ): InventoryMovement {
        if (! array_key_exists($type, InventoryMovement::TYPES)) {
            throw new RuntimeException(__('نوع حركة غير معروف.'));
        }

        $quantity = abs($quantity);

        if ($quantity < 1) {
            throw new RuntimeException(__('الكمية يجب أن تكون واحداً على الأقل.'));
        }

        $adds = InventoryMovement::TYPES[$type][1];
        $signed = $adds ? $quantity : -$quantity;

        return DB::transaction(function () use ($item, $type, $signed, $adds, $quantity, $studentId, $staffId, $reason, $by): InventoryMovement {
            $fresh = InventoryItem::lockForUpdate()->findOrFail($item->getKey());

            if (! $adds && (int) $fresh->stock_qty < $quantity) {
                throw new RuntimeException(__('الرصيد :stock فقط — لا يكفي :qty.', [
                    'stock' => $fresh->stock_qty,
                    'qty' => $quantity,
                ]));
            }

            $movement = InventoryMovement::create([
                'item_id' => $fresh->getKey(),
                'type' => $type,
                'qty' => $signed,
                'student_id' => $studentId,
                'staff_id' => $staffId,
                'reason' => $reason,
                'created_by' => $by?->getKey(),
            ]);

            $fresh->forceFill(['stock_qty' => (int) $fresh->stock_qty + $signed])->save();

            return $movement;
        });
    }

    /**
     * ردُّ عهدة: تُوسَم بالردّ وتُضاف حركةٌ موجبة.
     *
     * ولا يُكتفى بالوسم: الرصيد نقص يوم التسليم، فلا بدّ أن يعود
     * بحركةٍ مقابلة — وإلا بقي المخزن ناقصاً وما رُدّ في مكانه.
     */
    public function returnCustody(InventoryMovement $custody, ?User $by = null): InventoryMovement
    {
        if (! $custody->isOpenCustody()) {
            throw new RuntimeException(__('هذه ليست عهدةً مفتوحة.'));
        }

        $back = $this->handle(
            $custody->item,
            'return',
            (int) abs($custody->qty),
            $custody->student_id,
            $custody->staff_id,
            __('ردّ عهدة #:id', ['id' => $custody->getKey()]),
            $by,
        );

        $custody->forceFill(['returned_at' => now()])->save();

        return $back;
    }
}
