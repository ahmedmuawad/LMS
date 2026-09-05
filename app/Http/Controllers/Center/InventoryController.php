<?php

declare(strict_types=1);

namespace App\Http\Controllers\Center;

use App\Core\Access\Ability;
use App\Models\User;
use App\Modules\Center\Actions\MoveStock;
use App\Modules\Center\Models\InventoryItem;
use App\Modules\Center\Models\InventoryMovement;
use App\Modules\Center\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * حركات المخزون والعُهد المفتوحة.
 */
final class InventoryController
{
    public function movements(Request $request, int $itemId): View
    {
        $this->authorise($request);

        $item = InventoryItem::findOrFail($itemId);

        return view('center.inventory-movements', [
            'item' => $item,
            'movements' => $item->movements()
                ->with(['student', 'staff'])
                ->latest('id')
                ->paginate(40),
            'students' => Student::orderBy('name')->limit(500)->get(),
            'staff' => User::whereIn('role', ['owner', 'admin', 'instructor', 'staff'])->orderBy('name')->get(),
            'counted' => $item->countedStock(),
        ]);
    }

    public function store(Request $request, int $itemId, MoveStock $action): RedirectResponse
    {
        $this->authorise($request);

        $item = InventoryItem::findOrFail($itemId);

        $input = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(InventoryMovement::TYPES))],
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'student_id' => ['nullable', 'integer', 'exists:center_students,id'],
            'staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $action->handle(
                $item,
                (string) $input['type'],
                (int) $input['qty'],
                isset($input['student_id']) ? (int) $input['student_id'] : null,
                isset($input['staff_id']) ? (int) $input['staff_id'] : null,
                $input['reason'] ?? null,
                $request->user() instanceof User ? $request->user() : null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['qty' => $e->getMessage()])->withInput();
        }

        return back()->with('status', __('سُجّلت الحركة، والرصيد الآن :stock.', [
            'stock' => $item->fresh()->stock_qty,
        ]));
    }

    public function returnCustody(Request $request, int $movementId, MoveStock $action): RedirectResponse
    {
        $this->authorise($request);

        $custody = InventoryMovement::with('item')->findOrFail($movementId);

        try {
            $action->returnCustody($custody, $request->user() instanceof User ? $request->user() : null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return back()->with('status', __('رُدّت العهدة وعاد الرصيد.'));
    }

    /**
     * العُهد المفتوحة — عبر الأصناف كلّها.
     *
     * صاحب السنتر لا يسأل «ما عهد هذا الصنف؟» بل «ما الذي عند
     * الناس ولم يعد؟» — والسؤال عبر الأصناف لا داخل صنف.
     */
    public function custody(Request $request): View
    {
        $this->authorise($request);

        return view('center.inventory-custody', [
            'open' => InventoryMovement::where('type', 'custody')
                ->whereNull('returned_at')
                ->with(['item', 'student', 'staff'])
                ->latest('id')
                ->paginate(40),

            'low' => InventoryItem::low()->orderBy('stock_qty')->get(),
        ]);
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::CENTER_MANAGE), 403);
        abort_unless(tenant()?->allows('inventory') ?? false, 402, __('هذه الميزة غير متاحة في باقتك.'));
    }
}
