<?php

declare(strict_types=1);

namespace App\Http\Controllers\Center;

use App\Core\Support\Money;
use App\Modules\Center\Actions\CloseCashbox;
use App\Modules\Center\Actions\CollectPayment;
use App\Modules\Center\Actions\IssueInvoices;
use App\Modules\Center\Models\Cashbox;
use App\Modules\Center\Models\CashMovement;
use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Invoice;
use App\Modules\Center\Models\Payment;
use App\Modules\Center\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * المالية اليومية: من عليه فلوس، وكم في الدرج.
 */
final class FinanceController
{
    /** لوحة المتأخرات مرتّبة بالأقدم والأكبر — ومنها التذكير. */
    public function arrears(Request $request): View
    {
        $currency = (string) (tenant('currency') ?? 'EGP');

        $invoices = Invoice::outstanding()
            ->with(['student.user', 'student.guardians', 'group'])
            ->when($request->filled('group'), fn ($q) => $q->where('group_id', $request->integer('group')))
            ->when($request->input('only') === 'overdue', fn ($q) => $q->whereDate('due_on', '<', now()))
            ->orderBy('due_on')
            ->paginate(40)
            ->withQueryString();

        $totals = Invoice::outstanding()->selectRaw('sum(total_minor - paid_minor) as due')->value('due');
        $overdue = Invoice::overdue()->selectRaw('sum(total_minor - paid_minor) as due')->value('due');

        return view('center.arrears', [
            'invoices' => $invoices,
            'groups' => Group::open()->get(),
            'outstanding' => Money::fromMinor((int) $totals, $currency),
            'overdue' => Money::fromMinor((int) $overdue, $currency),
            'overdueCount' => Invoice::overdue()->count(),
        ]);
    }

    /**
     * إصدار فواتير الشهر لكل المجموعات — أو لمجموعة بعينها.
     *
     * الشاشة كانت تعرض المستحقّات ولا تُنشئها، والإصدار أمرُ طرفيّة
     * لا يعرفه صاحب المركز: فيرى «لا مستحقات» وعليه طلبةٌ لم
     * تُقيَّد أقساطهم قط.
     *
     * وهو آمنٌ بالتكرار: `IssueInvoices` تتخطّى ما صدر لهذه الفترة،
     * فضغطُه مرّتين لا يُنشئ قسطين.
     */
    public function issueAll(Request $request, IssueInvoices $action): RedirectResponse
    {
        $input = $request->validate([
            'group' => ['nullable', 'integer', 'exists:center_groups,id'],
            'period' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $period = $input['period'] ?? now()->format('Y-m');

        $groups = $request->filled('group')
            ? Group::whereKey($input['group'])->get()
            : Group::open()->get();

        $issued = 0;
        $skipped = 0;

        foreach ($groups as $group) {
            $result = $action->handle($group, $period);
            $issued += $result['issued'];
            $skipped += $result['skipped'];
        }

        return back()->with('status', $issued === 0
            ? __('لا فواتير جديدة — كل الأقساط صادرة لهذه الفترة (:skipped).', ['skipped' => $skipped])
            : __('صدرت :issued فاتورة لشهر :period.', ['issued' => $issued, 'period' => $period]));
    }

    public function collect(Request $request, CollectPayment $action): RedirectResponse
    {
        $input = $request->validate([
            'invoice_id' => ['nullable', 'integer', 'exists:center_invoices,id'],
            'student_id' => ['required', 'integer', 'exists:center_students,id'],
            'cashbox_id' => ['nullable', 'integer', 'exists:center_cashboxes,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'in:'.implode(',', array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $currency = (string) (tenant('currency') ?? 'EGP');

        try {
            $payment = $action->handle(
                Student::findOrFail($input['student_id']),
                Money::fromDecimal($input['amount'], $currency),
                isset($input['invoice_id']) ? Invoice::find($input['invoice_id']) : null,
                isset($input['cashbox_id']) ? Cashbox::find($input['cashbox_id']) : null,
                $input['method'],
                $request->user(),
                $input['reference'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', __('تم التحصيل. رقم الإيصال: :no', ['no' => $payment->receipt_no]));
    }

    public function issue(Request $request, string $groupId, IssueInvoices $action): RedirectResponse
    {
        $result = $action->handle(Group::findOrFail($groupId), $request->input('period'));

        return back()->with('status', __('صدرت :issued فاتورة، وتُخطّيت :skipped موجودة.', [
            'issued' => $result['issued'],
            'skipped' => $result['skipped'],
        ]));
    }

    public function cashboxes(CloseCashbox $closer): View
    {
        $boxes = Cashbox::with('branch')->where('is_active', true)->get();

        return view('center.cashboxes', [
            'boxes' => $boxes->map(fn (Cashbox $box): array => [
                'box' => $box,
                'expected' => $closer->expectedFor($box),
                'closedToday' => $box->closings()->whereDate('closed_on', now())->exists(),
            ]),
            'movements' => CashMovement::with('cashbox')
                ->latest('id')->limit(30)->get(),
        ]);
    }

    public function close(Request $request, string $cashboxId, CloseCashbox $action): RedirectResponse
    {
        $box = Cashbox::findOrFail($cashboxId);

        $input = $request->validate([
            'counted' => ['required', 'numeric', 'min:0'],
            'explanation' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $closing = $action->handle(
                $box,
                Money::fromDecimal($input['counted'], $box->currency),
                $request->user(),
                $input['explanation'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['counted' => $e->getMessage()]);
        }

        return back()->with('status', $closing->isBalanced()
            ? __('أُقفلت الخزنة مطابِقة.')
            : __('أُقفلت الخزنة بفرق :diff — مسجَّل ومبرَّر.', ['diff' => $closing->difference()->format()]));
    }
}
