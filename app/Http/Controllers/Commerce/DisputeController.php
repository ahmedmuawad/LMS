<?php

declare(strict_types=1);

namespace App\Http\Controllers\Commerce;

use App\Core\Access\Ability;
use App\Modules\Commerce\Models\Dispute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * شاشة النزاعات.
 *
 * ## سببُ وجودها المهلة
 *
 * البوابة تُخطر وتُمهل أياماً معدودة. وبلا شاشة يصل الإخطار إلى
 * بريدٍ لا يقرؤه أحد، وتفوت المهلة، ويخسر المشترك مالاً كان يملك
 * دليلَه — سجلُّ دخول الطالب ومشاهداتُه كلّها عندنا.
 *
 * ## والأقرب مهلةً أولاً
 *
 * لا الأحدث: نزاعٌ وصل اليوم ومهلتُه بعد أسبوعين أقلُّ إلحاحاً من
 * آخرَ وصل الأسبوع الماضي وبقي له يومان.
 */
final class DisputeController
{
    public function index(Request $request): View
    {
        $this->authorise($request);

        return view('commerce.disputes', [
            'rows' => Dispute::with('order:id,number,user_id')
                ->unresolved()
                ->orderByRaw('due_by is null, due_by asc')
                ->paginate(30),

            'resolved' => Dispute::whereNotNull('resolved_at')->count(),
            'statuses' => array_map(fn (string $l): string => __($l), Dispute::STATUSES),
        ]);
    }

    /** يحفظ الدليل الذي سيُرسَل، أو يُسجّل النتيجة */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorise($request);

        $dispute = Dispute::findOrFail($id);

        $input = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(Dispute::STATUSES))],
            'evidence' => ['nullable', 'string', 'max:5000'],
        ], [], ['status' => __('الحالة'), 'evidence' => __('الدليل')]);

        $status = (string) $input['status'];

        $dispute->forceFill([
            'status' => $status,
            'evidence' => $input['evidence'] ?? $dispute->evidence,

            /*
             | «مُحسَم» يُحسب من الحالة لا يُسأل عنه.
             |
             | وحقلٌ منفصل يُنسى فيبقى نزاعٌ خُسر ظاهراً في القائمة
             | العاجلة إلى الأبد.
             */
            'resolved_at' => in_array($status, ['won', 'lost', 'refunded'], true)
                ? ($dispute->resolved_at ?? now())
                : null,
        ])->save();

        return back()->with('status', __('حُدّث النزاع.'));
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::ORDERS_MANAGE), 403);
    }
}
