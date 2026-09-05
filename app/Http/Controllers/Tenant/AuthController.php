<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Core\Auth\LegacyPassword;
use App\Core\Auth\TwoFactor;
use App\Core\Security\DeviceGuard;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Models\User;
use App\Modules\Lms\Models\UserDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AuthController
{
    public function __construct(
        private readonly TwoFactor $twoFactor,
        private readonly DeviceGuard $devices,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return view('auth.login');
        }

        // الطالب لا لوحة له: توجيهه إليها يُعيده 403 بعد دخول ناجح
        return redirect(url($request->user()->canAccessPanel() ? '/admin/dashboard' : '/me'));
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

        if (! $guard->validate($payload) && ! $this->legacyMatches($field, $credentials)) {
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

        /*
         | حدّ الأجهزة يُفحص قبل إنشاء الجلسة.
         |
         | فحصُه بعدها يعني أن يدخل ثم يُطرد — وهذا يترك جلسةً قائمة
         | لحظةً، ويجعل رسالة المنع تبدو عطلاً لا قاعدة.
         |
         | ويُفحص قبل التوثيق بخطوتين كذلك: لا معنى لأن يُطالَب برمزٍ
         | ثم يُقال له إن جهازه غير مسموح.
         */
        $device = $this->devices->register($request, $user, $this->deviceLimitFor($user));

        if (! $device->allowed) {
            /*
             | مخرجٌ لمن أثبت كلمة مروره.
             |
             | شاشة فكّ الأجهزة خلف الدخول، والمقفول لا يصلها — فيجد
             | نفسه محبوساً خارج حسابه بلا باب إلا الدعم. وهذا فخٌّ
             | يقع كثيراً: من بدّل هاتفه أو مسح كوكيّاته يبلغ حدّه
             | وهو صاحب الحساب لا سارقه.
             |
             | وقد أثبت كلمة مروره للتوّ، فيُعرض عليه فصلُ أقدم جهاز
             | والدخول — ويبقى الحدّ قائماً: العدد لا يزيد، والأقدم
             | هو الذي يخرج.
             */
            return back()
                ->withInput($request->only('email'))
                ->with('device_limit', $device->message())
                ->withErrors(['email' => $device->message()]);
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

        return redirect()->intended(url($user->canAccessPanel() ? '/admin/dashboard' : '/me'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(url('/'));
    }

    /**
     * حدّ الأجهزة من باقة المشترك — أو بلا حدّ.
     *
     * صفرٌ يعني «ممنوع» في بقية الحدود، لكنه هنا يعني «بلا حدّ»:
     * باقةٌ لا تشتري الميزة لا تمنع صاحبها من الدخول أصلاً.
     */
    private function deviceLimit(): ?int
    {
        $tenant = tenant();

        if ($tenant === null || ! $tenant->allows('device_limit')) {
            return null;
        }

        $limit = $tenant->limitOf('device_limit');

        return $limit === null || $limit < 1 ? null : $limit;
    }

    /**
     * الحدّ يخصّ الطلبة لا فريق المنصة.
     *
     * الغرض منه منع تداول حساب الطالب بين عشرة. أمّا صاحب المنصة
     * ومدرّسوها وموظّفوها فيدخلون من حاسوب المكتب وهاتفهم وحاسوب
     * البيت وجهاز الاحتياط — وقفلُ حساب صاحب المركز عن منصّته
     * لأنه بدّل متصفّحه ثلاث مرات كارثةٌ لا حماية.
     *
     * ولا يُقاس هذا بالدور وحده: من يدير المنصة يدخل ليعمل، ومن
     * يتعلّم فيها يدخل ليشاهد — والتداول يقع في الثاني.
     */
    private function deviceLimitFor(User $user): ?int
    {
        return in_array($user->role, User::panelRoles(), true)
            ? null
            : $this->deviceLimit();
    }

    /**
     * كلمة مرور منقولة من ووردبريس.
     *
     * `legacy_hash` كان عموداً يُعرض في اللوحة ولا يقرؤه أحدٌ عند
     * الدخول — فكلّ طالبٍ مستورَد لا يستطيع الدخول أبداً ولا رسالة
     * تشرح له. وهذا يُبطل الاستيراد كلّه: مدرسةٌ نقلَت مئتي طالب
     * فلم يدخل منهم أحد.
     *
     * وأولُ دخولٍ ناجح يُعيد التجزئة بمعيارنا ويُطفئ العلَم، فتنتقل
     * المدرسة كلّها خلال أسابيع بلا أن يشعر أحد.
     *
     * @param  array{email:string, password:string}  $credentials
     */
    private function legacyMatches(string $field, array $credentials): bool
    {
        $user = User::where($field, $credentials['email'])->where('legacy_hash', true)->first();

        if ($user === null) {
            return false;
        }

        if (! app(LegacyPassword::class)->verifyAndUpgrade($user, $credentials['password'])) {
            return false;
        }

        /*
         | نُعيد سؤال الحارس بعد الترقية.
         |
         | فبقيّة المسار تعتمد على `getLastAttempted()`، وتخطّيها
         | هنا يجعل التوثيق بخطوتين وحالةَ الحساب بلا فحص.
         */
        return Auth::guard()->validate([
            $field => $credentials['email'],
            'password' => $credentials['password'],
        ]);
    }

    /**
     * يفصل أقدم جهاز ثم يترك المستخدم يدخل.
     *
     * يُطلب من شاشة الدخول بعد رفضٍ للحدّ، ويُعاد فيه التحقّق
     * كاملاً: من يضغط الزرّ قد لا يكون من كتب كلمة المرور.
     */
    public function releaseDevice(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $field = filter_var($input['email'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        if (! Auth::guard()->validate([$field => $input['email'], 'password' => $input['password']])) {
            throw ValidationException::withMessages(['email' => __('بيانات الدخول غير صحيحة.')]);
        }

        $user = Auth::guard()->getLastAttempted();

        UserDevice::where('user_id', $user->getKey())
            ->orderBy('last_seen_at')
            ->limit(1)
            ->get()
            ->each
            ->delete();

        return back()->with('status', __('فُصل أقدم جهاز. سجّل دخولك الآن.'));
    }
}
