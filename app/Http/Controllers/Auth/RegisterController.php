<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Core\Auth\PasswordPolicy;
use App\Models\User;
use App\Modules\Lms\Models\Instructor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * إنشاء حساب.
 *
 * كان غائباً تماماً: لا مسار ولا شاشة. المستخدم الوحيد الذي وُجد
 * هو من أدخله صاحب المنصّة يدوياً — ومنصّة تبيع كورسات ولا يستطيع
 * أحد التسجيل فيها ليست منصّة بعد.
 */
final class RegisterController
{
    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect(url('/my-courses'));
        }

        abort_unless((bool) setting('users.registration_open', true), 404);

        return view('auth.register', [
            'mayBeInstructor' => $this->instructorSignupOpen(),
            'passwordHint' => PasswordPolicy::hint(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless((bool) setting('users.registration_open', true), 404);

        // حدّ لكل عنوان: التسجيل الآلي يُنشئ ألف حساب في دقيقة
        $key = 'register:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('محاولات كثيرة. حاول بعد :seconds ثانية.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        $input = $request->validate($this->rules(), [], $this->attributes());

        RateLimiter::hit($key, 600);

        $wantsInstructor = $this->instructorSignupOpen() && ($input['role'] ?? 'student') === 'instructor';

        $user = DB::transaction(function () use ($input, $wantsInstructor): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => mb_strtolower($input['email']),
                'phone' => $input['phone'] ?? null,
                'password' => $input['password'],
                'role' => 'student',
                // المدرّس يبدأ طالباً حتى يُعتمد: دور المدرّس يفتح
                // اللوحة، وفتحُها قبل الاعتماد يُبطل معنى الاعتماد
                'status' => 'active',
                'locale' => app()->getLocale(),
                'terms_accepted' => (bool) ($input['terms'] ?? false),
                'password_changed_at' => now(),
                'last_seen_at' => now(),
            ]);

            if ($wantsInstructor) {
                Instructor::create([
                    'user_id' => $user->getKey(),
                    // بلا `approved_at`: ينتظر اعتماد صاحب المنصّة
                    'approved_at' => (bool) setting('users.instructor_approval', true) ? null : now(),
                ]);
            }

            return $user;
        });

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        if ((bool) setting('users.verify_email', true)) {
            $user->sendEmailVerificationNotification();

            return redirect(url('/verify-email'))
                ->with('status', __('أرسلنا رابط التأكيد إلى بريدك.'));
        }

        notify('account.welcome', $user, ['login_url' => url('/login'), 'url' => url('/my-courses')]);

        return redirect(url('/my-courses'))->with('status', __('أهلاً بك. حسابك جاهز.'));
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')],
            'password' => PasswordPolicy::rules(),
        ];

        if ((bool) setting('users.terms_required', true)) {
            $rules['terms'] = ['accepted'];
        }

        if ($this->instructorSignupOpen()) {
            $rules['role'] = ['nullable', 'in:student,instructor'];
        }

        if ((bool) setting('users.verify_phone', false)) {
            $rules['phone'] = ['required', 'string', 'max:32', Rule::unique('users', 'phone')];
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function attributes(): array
    {
        return [
            'name' => __('الاسم'),
            'email' => __('البريد'),
            'phone' => __('الهاتف'),
            'password' => __('كلمة المرور'),
            'terms' => __('الشروط'),
        ];
    }

    private function instructorSignupOpen(): bool
    {
        return (bool) setting('lms.instructor_signup', false);
    }
}
