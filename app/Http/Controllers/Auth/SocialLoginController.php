<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Core\Auth\SocialLogin;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * الدخول والتسجيل بحسابٍ اجتماعي.
 *
 * ## لماذا يُربط بالبريد لا يُنشأ حسابٌ جديد دائماً
 *
 * من سجّل ببريده ثم عاد يدخل بـGoogle بالبريد نفسه هو الشخص نفسه؛
 * وإنشاءُ حسابٍ ثانٍ له يعني أن كورساته التي دفع فيها اختفت. وهو
 * لا يفهم لماذا — يرى منصّةً أكلت نقوده.
 *
 * ## والبريد غير المؤكَّد لا يُربط
 *
 * شبكةٌ تعطينا بريداً بلا تأكيدٍ تجعل من يسجّل فيها ببريد غيره
 * يدخل حساب غيره عندنا. وGoogle تؤكّده، وFacebook تعطيه مؤكَّداً
 * أيضاً؛ ومن لا يعطي بريداً أصلاً يُنشأ له حسابٌ جديد.
 */
final class SocialLoginController
{
    public function __construct(private readonly SocialLogin $social) {}

    /** يذهب بالمستخدم إلى الشبكة */
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        abort_unless($this->social->configured($provider), 404);

        $state = SocialLogin::newState();

        /*
         | `state` تُحفظ في الجلسة وتُقارَن عند العودة.
         |
         | وبلاها يستطيع مهاجمٌ أن يجعل الضحيّة تدخل بحسابه هو، فيصير
         | كلُّ ما تكتبه بعدها في حسابٍ يملكه — وهي سطرٌ يُنسى كثيراً.
         */
        $request->session()->put('social_state', $state);
        $request->session()->put('social_provider', $provider);

        return redirect()->away($this->social->redirectUrl($provider, $state));
    }

    /** يعود منها بالرمز */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless($this->social->configured($provider), 404);

        $expected = $request->session()->pull('social_state');
        $from = $request->session()->pull('social_provider');

        if (! is_string($expected)
            || ! hash_equals($expected, (string) $request->query('state'))
            || $from !== $provider) {
            return redirect(url('/login'))->withErrors([
                'email' => __('انتهت جلسة الدخول — ابدأ من جديد.'),
            ]);
        }

        // ضغط «إلغاء» عند الشبكة يعود بلا رمز، وهو ليس خطأً يُبلَّغ عنه
        if (blank($request->query('code'))) {
            return redirect(url('/login'));
        }

        try {
            $profile = $this->social->user($provider, (string) $request->query('code'));
        } catch (RuntimeException $e) {
            return redirect(url('/login'))->withErrors(['email' => $e->getMessage()]);
        }

        $user = $this->resolve($provider, $profile);

        if ($user === null) {
            return redirect(url('/login'))->withErrors([
                'email' => __('لم يُعطنا :net بريدك — سجّل ببريدك مباشرةً.', [
                    'net' => $this->social->label($provider),
                ]),
            ]);
        }

        if ($user->status !== 'active') {
            return redirect(url('/login'))->withErrors([
                'email' => __('هذا الحساب غير مفعّل. راسل الدعم.'),
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $user->forceFill(['last_seen_at' => now()])->save();

        return redirect(url($user->canAccessPanel() ? '/admin/dashboard' : '/me'));
    }

    /**
     * يجد صاحب الحساب أو يُنشئه.
     *
     * @param  array{id:string, email:?string, name:?string}  $profile
     */
    private function resolve(string $provider, array $profile): ?User
    {
        $email = $profile['email'];

        if (blank($email)) {
            return null;
        }

        $user = User::where('email', $email)->first();

        if ($user !== null) {
            return $user;
        }

        /*
         | حسابٌ جديد بكلمة مرورٍ عشوائية لا فارغة.
         |
         | تركُها فارغةً يجعل `Hash::check` يمرّ بأي شيء في بعض
         | المزوّدين. ومن أراد كلمة مرورٍ لاحقاً استعمل «نسيت كلمة
         | المرور» — بريدُه مؤكَّدٌ عندنا أصلاً.
         */
        return User::create([
            'name' => $profile['name'] ?: Str::before($email, '@'),
            'email' => $email,
            'password' => Hash::make(Str::random(48)),
            'role' => 'student',
            'status' => 'active',

            // الشبكة أكّدت البريد؛ ومطالبتُه بتأكيده ثانيةً عبثٌ يُخسّرنا إيّاه
            'email_verified_at' => now(),
        ]);
    }
}
