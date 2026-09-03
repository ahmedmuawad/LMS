<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class SuperAuthController
{
    public function show(): View|RedirectResponse
    {
        return Auth::guard('super')->check()
            ? redirect(url('/admin'))
            : view('super-admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'super-login:'.mb_strtolower($input['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('محاولات كثيرة. حاول بعد :seconds ثانية.', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        if (! Auth::guard('super')->attempt($input, $request->boolean('remember'))) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages(['email' => __('بيانات الدخول غير صحيحة.')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(url('/admin'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('super')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(url('/super/login'));
    }
}
