<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/** تأكيد البريد برابط موقّع — بلا جدول رموز نحفظه ونُبطله. */
final class EmailVerificationController
{
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect(url('/my-courses'))
            : view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect(url('/my-courses'));
        }

        $request->fulfill();

        event(new Verified($request->user()));

        notify('account.welcome', $request->user(), [
            'login_url' => url('/login'),
            'url' => url('/my-courses'),
        ]);

        return redirect(url('/my-courses'))->with('status', __('تأكّد بريدك. أهلاً بك.'));
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect(url('/my-courses'));
        }

        // حدّ الإرسال يحمي سمعة نطاقك قبل أن يحمي المستخدم من الإزعاج
        $key = 'verify:'.$request->user()->getKey();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors(['email' => __('أرسلنا الرابط للتوّ. انتظر :seconds ثانية.', [
                'seconds' => RateLimiter::availableIn($key),
            ])]);
        }

        RateLimiter::hit($key, 120);

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', __('أُرسل الرابط مرة أخرى.'));
    }
}
