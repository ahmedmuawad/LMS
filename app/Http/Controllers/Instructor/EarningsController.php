<?php

declare(strict_types=1);

namespace App\Http\Controllers\Instructor;

use App\Core\Access\Scope;
use App\Core\Support\Money;
use App\Models\User;
use App\Modules\Commerce\Actions\CreatePayout;
use App\Modules\Commerce\Models\InstructorEarning;
use App\Modules\Commerce\Models\Payout;
use App\Modules\Lms\Models\Instructor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * الأرباح والعمولات وطلب السحب.
 *
 * الرصيد ثلاث طبقات لا رقم واحد: **قيد النضج** (لم تنقضِ مهلة
 * استرداده)، و**متاح** (نضج ولم يُطلَب)، و**محوَّل**. عرضها رقماً
 * واحداً يجعل كل تحويل شكوى: «أين بقيّة مالي؟».
 */
final class EarningsController
{
    public function __construct(
        private readonly Scope $scope,
        private readonly CreatePayout $payouts,
    ) {}

    public function index(Request $request): View
    {
        $user = $this->user($request);
        $instructor = $this->instructor($user);
        $currency = (string) (tenant('currency') ?? 'EGP');

        $earnings = $this->scope->byInstructor(InstructorEarning::query(), $user);

        $sum = fn (string $status): Money => Money::fromMinor(
            (int) (clone $earnings)->where('status', $status)->sum('amount_minor'),
            $currency,
        );

        return view('instructor.earnings', [
            'pending' => $sum('pending'),
            'available' => $instructor === null
                ? Money::fromMinor(0, $currency)
                : $this->payouts->balanceFor($instructor),
            'paid' => $sum('paid'),
            'reversed' => $sum('reversed'),
            'rate' => $instructor?->commission_rate,
            'rows' => (clone $earnings)
                ->with(['orderItem', 'payout'])
                ->latest('id')->paginate(25),
            'requests' => $this->scope->byInstructor(Payout::query(), $user)
                ->latest('id')->limit(10)->get(),
            'canRequest' => (bool) setting('commerce.payout_requests', true)
                && $instructor !== null
                && $this->payouts->balanceFor($instructor)->minor >= $this->minimum($currency)->minor
                && $this->payouts->balanceFor($instructor)->minor > 0,
            'minimum' => $this->minimum($currency),
            'openRequest' => $instructor !== null && Payout::where('instructor_id', $instructor->getKey())
                ->whereIn('status', ['pending', 'processing'])->exists(),
        ]);
    }

    /** طلب السحب: المدرّس يطلب، وصاحب المنصّة يعتمد ويحوّل. */
    public function request(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $instructor = $this->instructor($user);

        if ($instructor === null) {
            abort(404);
        }

        abort_unless((bool) setting('commerce.payout_requests', true), 404);

        $currency = (string) (tenant('currency') ?? 'EGP');
        $balance = $this->payouts->balanceFor($instructor);

        if ($balance->minor < max(1, $this->minimum($currency)->minor)) {
            return back()->withErrors(['payout' => __('رصيدك المتاح دون الحدّ الأدنى للسحب.')]);
        }

        // طلب معلّق قائم يعني ألّا يُفتح ثانٍ فوقه
        if (Payout::where('instructor_id', $instructor->getKey())
            ->whereIn('status', ['pending', 'processing'])->exists()) {
            return back()->withErrors(['payout' => __('لديك طلب سحب قيد المعالجة.')]);
        }

        $input = $request->validate([
            'method' => ['required', 'string', 'max:32'],
            'destination' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $this->payouts->handle(
                $instructor,
                $input['method'],
                filled($input['destination'] ?? null) ? ['account' => $input['destination']] : [],
                $request->user() instanceof User ? $request->user() : null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['payout' => $e->getMessage()]);
        }

        return back()->with('status', __('وصل طلبك وسيُراجَع.'));
    }

    /** الحدّ الأدنى محفوظ بالوحدة الكبرى في الإعدادات كما يكتبه صاحبه. */
    private function minimum(string $currency): Money
    {
        return Money::fromDecimal((string) setting('commerce.payout_minimum', 0), $currency);
    }

    private function instructor(?User $user): ?Instructor
    {
        $id = $this->scope->instructorIdFor($user);

        return $id === null ? null : Instructor::find($id);
    }

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
