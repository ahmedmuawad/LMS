<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Core\Auth\TwoFactor;
use App\Models\User;
use App\Modules\Lms\Models\UserDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * التوثيق بخطوتين: الإعداد والتحدّي.
 *
 * الجلسة تُنشأ **بعد** التحدّي لا قبله: من دخل بكلمة مرور صحيحة
 * وانتظر الرمز ليس مُصادَقاً بعد، وتسجيلُه ثم مطالبتُه يعني أن
 * سرقة كلمة المرور وحدها تكفي.
 */
final class TwoFactorController
{
    private const PENDING = 'two_factor:user';

    public function __construct(private readonly TwoFactor $twoFactor) {}

    // ---------- الإعداد من حساب المستخدم ----------

    public function setup(Request $request): View
    {
        $user = $request->user();
        $enabled = $this->twoFactor->isEnabled($user);

        // السرّ يُولَّد عند فتح الشاشة لا عند الحفظ: نحتاج عرض QR
        $secret = $enabled ? null : $this->twoFactor->secretFor($user) ?? $this->twoFactor->generateFor($user);

        return view('auth.two-factor', [
            'enabled' => $enabled,
            'secret' => $secret,
            'qr' => $secret === null ? null : $this->twoFactor->qrSvg($user, $secret),
            'recoveryCodes' => $enabled ? $this->twoFactor->recoveryCodesFor($user) : [],
            'forced' => (string) setting('users.two_factor', 'optional') === 'required',

            /*
             | الأجهزة في شاشة الأمان لا في شاشةٍ خاصة.
             |
             | «من يدخل حسابي؟» و«كيف أحميه؟» سؤالٌ واحد عند صاحب
             | الحساب، وشاشتان له تجعلانه يجد نصف الجواب.
             */
            'devices' => UserDevice::where('user_id', $user->getKey())
                ->orderByDesc('last_seen_at')->get(),
            'deviceLimit' => tenant()?->allows('device_limit')
                ? tenant()->limitOf('device_limit')
                : null,
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $input = $request->validate(['code' => ['required', 'string', 'min:6', 'max:8']]);

        if (! $this->twoFactor->confirm($request->user(), $input['code'])) {
            throw ValidationException::withMessages(['code' => __('الرمز غير صحيح. تحقّق من ساعة هاتفك.')]);
        }

        return back()->with('status', __('فُعّل التوثيق بخطوتين. احفظ رموز الاستعادة في مكان آمن.'));
    }

    public function disable(Request $request): RedirectResponse
    {
        abort_if((string) setting('users.two_factor', 'optional') === 'required', 403,
            __('التوثيق بخطوتين إلزامي في هذا الموقع.'));

        // كلمة المرور تُطلب للإطفاء: جلسة مسروقة لا تُنزع الحماية
        $input = $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($input['password'], (string) $request->user()->password)) {
            throw ValidationException::withMessages(['password' => __('كلمة المرور غير صحيحة.')]);
        }

        $this->twoFactor->disable($request->user());

        return back()->with('status', __('أُطفئ التوثيق بخطوتين.'));
    }

    // ---------- التحدّي عند الدخول ----------

    public function challenge(Request $request): View|RedirectResponse
    {
        return $this->pendingUser($request) === null
            ? redirect(url('/login'))
            : view('auth.two-factor-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect(url('/login'));
        }

        $key = 'two-factor:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => __('محاولات كثيرة. حاول بعد :seconds ثانية.', [
                'seconds' => RateLimiter::availableIn($key),
            ])]);
        }

        $input = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $code = trim($input['code']);

        $passed = $this->twoFactor->verify($user, $code)
            || $this->twoFactor->consumeRecoveryCode($user, $code);

        if (! $passed) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages(['code' => __('الرمز غير صحيح.')]);
        }

        RateLimiter::clear($key);
        $request->session()->forget(self::PENDING);

        Auth::login($user, (bool) $request->session()->pull('two_factor:remember', false));
        $request->session()->regenerate();

        $user->forceFill(['last_seen_at' => now()])->save();

        return redirect()->intended(url($user->canAccessPanel() ? '/admin/dashboard' : '/my-courses'));
    }

    /** يُعلَّق الدخول: المعرّف في الجلسة وحده، بلا مصادقة. */
    public static function hold(Request $request, User $user, bool $remember): void
    {
        $request->session()->put(self::PENDING, $user->getKey());
        $request->session()->put('two_factor:remember', $remember);
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get(self::PENDING);

        return $id === null ? null : User::find($id);
    }
}
