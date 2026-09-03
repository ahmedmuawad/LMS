<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Core\Auth\PasswordPolicy;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** نسيت كلمة المرور وإعادة تعيينها. */
final class PasswordResetController
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * الردّ واحد سواء وُجد البريد أو لا.
     *
     * الرسالة المختلفة تكشف من هو مسجّل عندنا ومن ليس — وهذا وحده
     * يكفي لبناء قائمة عملاء المنافس.
     */
    public function send(Request $request): RedirectResponse
    {
        $input = $request->validate(['email' => ['required', 'email']]);

        $key = 'reset:'.mb_strtolower($input['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages([
                'email' => __('محاولات كثيرة. حاول بعد :seconds ثانية.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        RateLimiter::hit($key, 300);

        Password::sendResetLink(['email' => mb_strtolower($input['email'])]);

        return back()->with('status', __('إن كان لدينا حساب بهذا البريد فقد أرسلنا إليه رابط الاستعادة.'));
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
            'passwordHint' => PasswordPolicy::hint(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => PasswordPolicy::rules(),
        ]);

        $status = Password::reset($input, function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
                'password_changed_at' => now(),
            ])->save();

            event(new PasswordReset($user));

            notify('account.password_changed', $user, [
                'changed_at' => now()->translatedFormat('j F Y — H:i'),
                'ip' => request()->ip(),
                'url' => url('/login'),
            ]);
        });

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect(url('/login'))->with('status', __('تغيّرت كلمة المرور. سجّل دخولك بها.'));
    }
}
