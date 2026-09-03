<?php

declare(strict_types=1);

namespace App\Http\Controllers\Commerce;

use App\Modules\Commerce\Actions\RedeemCode;
use App\Modules\Commerce\Models\WalletTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use RuntimeException;

final class WalletController
{
    public function show(Request $request): View
    {
        $currency = (string) (tenant('currency') ?? 'EGP');

        return view('commerce.wallet', [
            'balance' => WalletTransaction::balanceFor((int) $request->user()->getKey(), $currency),
            'transactions' => WalletTransaction::where('user_id', $request->user()->getKey())
                ->latest('id')->limit(50)->get(),
        ]);
    }

    /**
     * استهلاك كود. محدود المحاولات: بلا حدّ يصير تخمين الأكواد
     * ممكناً بالقوة الغاشمة.
     */
    public function redeem(Request $request, RedeemCode $action): RedirectResponse
    {
        $input = $request->validate(['code' => ['required', 'string', 'max:32']]);

        $key = 'redeem:'.$request->user()->getKey();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withErrors(['code' => __('محاولات كثيرة. حاول بعد :s ثانية.', [
                's' => RateLimiter::availableIn($key),
            ])]);
        }

        try {
            $code = $action->handle($request->user(), $input['code']);
        } catch (RuntimeException $e) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['code' => $e->getMessage()]);
        }

        RateLimiter::clear($key);

        return back()->with('status', $code->type === 'wallet'
            ? __('شُحن رصيدك بمبلغ :amount.', ['amount' => $code->value()->format()])
            : __('فُتح الكورس. ستجده في «كورساتي».'));
    }
}
