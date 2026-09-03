<?php

declare(strict_types=1);

namespace App\Http\Controllers\Growth;

use App\Modules\Growth\Actions\RecordConversion;
use App\Modules\Growth\Models\Affiliate;
use App\Modules\Growth\Models\AffiliateConversion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** لوحة المسوّق ولوحة إدارة البرنامج. */
final class AffiliateController
{
    public function dashboard(Request $request): View
    {
        abort_unless((bool) setting('growth.affiliates_enabled', false), 404);

        $affiliate = Affiliate::where('user_id', $request->user()->getKey())->first();

        return view('growth.affiliate', [
            'affiliate' => $affiliate,
            'conversions' => $affiliate === null
                ? collect()
                : $affiliate->conversions()->latest('id')->limit(20)->get(),
            'monthly' => $affiliate === null ? [] : $this->monthly($affiliate),
        ]);
    }

    public function join(Request $request): RedirectResponse
    {
        abort_unless((bool) setting('growth.affiliates_enabled', false), 404);

        $existing = Affiliate::where('user_id', $request->user()->getKey())->first();

        if ($existing !== null) {
            return back()->with('status', __('أنت مسجّل بالفعل في البرنامج.'));
        }

        $input = $request->validate([
            'payout_method' => ['nullable', 'string', 'max:24'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $auto = (bool) setting('growth.affiliates_auto_approve', false);

        Affiliate::create([
            'user_id' => $request->user()->getKey(),
            'code' => $this->code($request->user()->name),
            'status' => $auto ? 'active' : 'pending',
            'approved_at' => $auto ? now() : null,
            'payout_method' => $input['payout_method'] ?? null,
            'notes' => $input['notes'] ?? null,
        ]);

        return back()->with('status', $auto
            ? __('أهلاً بك في البرنامج — رابطك جاهز.')
            : __('وصل طلبك وسيُراجَع قريباً.'));
    }

    public function index(Request $request): View
    {
        return view('growth.affiliates', [
            'affiliates' => Affiliate::with('user')
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->orderByDesc('earned_minor')
                ->paginate(30)
                ->withQueryString(),
            'pendingConversions' => AffiliateConversion::where('status', 'pending')->count(),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $affiliate = Affiliate::findOrFail($id);

        $input = $request->validate([
            'status' => ['required', 'string', 'in:pending,active,suspended'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:90'],
        ]);

        $affiliate->forceFill([
            'status' => $input['status'],
            'commission_rate' => $input['commission_rate'] === null ? null : (float) $input['commission_rate'],
            'approved_at' => $input['status'] === 'active' ? ($affiliate->approved_at ?? now()) : $affiliate->approved_at,
        ])->save();

        return back()->with('status', __('حُدّث المسوّق.'));
    }

    public function rejectConversion(Request $request, string $id, RecordConversion $conversions): RedirectResponse
    {
        $conversion = AffiliateConversion::findOrFail($id);

        $reason = $request->validate([
            'reason' => ['required', 'string', 'max:200'],
        ])['reason'];

        $conversions->reject($conversion, $reason);

        return back()->with('status', __('رُفضت العمولة.'));
    }

    /**
     * كود قصير مقروء: الاسم أولاً ثم فاصل عشوائي.
     *
     * كود من أرقام وحدها لا يُنطق في مكالمة ولا يُكتب من الذاكرة.
     */
    private function code(string $name): string
    {
        $base = Str::of($name)->ascii()->slug('')->limit(10, '')->lower()->value();
        $base = $base === '' ? 'aff' : $base;

        do {
            $code = $base.random_int(100, 999);
        } while (Affiliate::where('code', $code)->exists());

        return $code;
    }

    /** @return array<string, int> آخر ستة أشهر: الشهر ← العمولة */
    private function monthly(Affiliate $affiliate): array
    {
        $rows = $affiliate->conversions()
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->get(['created_at', 'commission_minor', 'status']);

        $months = [];

        foreach ($rows as $row) {
            if ($row->status === 'rejected') {
                continue;
            }

            $key = $row->created_at->format('Y-m');
            $months[$key] = ($months[$key] ?? 0) + (int) $row->commission_minor;
        }

        ksort($months);

        return $months;
    }
}
