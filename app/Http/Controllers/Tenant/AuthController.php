<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Core\Auth\TwoFactor;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AuthController
{
    public function __construct(private readonly TwoFactor $twoFactor) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return view('auth.login');
        }

        // الطالب لا لوحة له: توجيهه إليها يُعيده 403 بعد دخول ناجح
        return redirect(url($request->user()->canAccessPanel() ? '/admin/dashboard' : '/my-courses'));
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // حدّ محاولات لكل بريد + عنوان، فلا يُستخدم الحساب لإقفال صاحبه
        $key = 'login:'.mb_strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('محاولات كثيرة. حاول بعد :seconds ثانية.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        $field = filter_var($credentials['email'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        /*
         | نتحقّق بلا تسجيل أولاً.
         |
         | `Auth::attempt` تُنشئ الجلسة فوراً؛ ولو أُنشئت ثم طُولب
         | بالرمز لكانت سرقةُ كلمة المرور وحدها كافية. فنسأل
         | المزوّد عن الصحّة، ثم نُعلّق أو نُسجّل.
         */
        $guard = Auth::guard();
        $payload = [$field => $credentials['email'], 'password' => $credentials['password']];

        if (! $guard->validate($payload)) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages([
                'email' => __('بيانات الدخول غير صحيحة.'),
            ]);
        }

        RateLimiter::clear($key);

        /** @var User $user */
        $user = $guard->getLastAttempted();

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => __('هذا الحساب غير مفعّل. راسل الدعم.'),
            ]);
        }

        if ($this->twoFactor->isEnabled($user)) {
            TwoFactorController::hold($request, $user, $request->boolean('remember'));

            return redirect(url('/two-factor'));
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill(['last_seen_at' => now()])->save();

        // التوثيق إلزامي ولم يُفعّله بعد: يُوجَّه إلى إعداده لا إلى اللوحة
        if ((string) setting('users.two_factor', 'optional') === 'required' && ! $this->twoFactor->isEnabled($user)) {
            return redirect(url('/account/two-factor'))
                ->with('status', __('التوثيق بخطوتين إلزامي — فعّله لمتابعة الاستخدام.'));
        }

        return redirect()->intended(url($user->canAccessPanel() ? '/admin/dashboard' : '/my-courses'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(url('/'));
    }
}
