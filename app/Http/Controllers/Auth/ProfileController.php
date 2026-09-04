<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Core\Auth\PasswordPolicy;
use App\Core\Auth\TwoFactor;
use App\Modules\Content\Actions\StoreMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * الملف الشخصي وأمان الحساب.
 *
 * وثيقة 11 §ج تعدّه شاشة، وكان غائباً: لم يستطع مستخدم تغيير اسمه
 * ولا كلمة مروره ولا لغته بعد إنشاء حسابه.
 */
final class ProfileController
{
    public function __construct(private readonly TwoFactor $twoFactor) {}

    public function show(Request $request): View
    {
        return view('auth.profile', [
            'user' => $request->user(),
            'passwordHint' => PasswordPolicy::hint(),
            'twoFactorEnabled' => $this->twoFactor->isEnabled($request->user()),
            'locales' => config('locales.supported', []),
            'mayDelete' => (bool) setting('users.self_delete', true) && ! $request->user()->isOwner(),
        ]);
    }

    public function update(Request $request, StoreMedia $media): RedirectResponse
    {
        $user = $request->user();

        $input = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($user->getKey())],
            'locale' => ['required', 'string', Rule::in(array_keys((array) config('locales.supported', [])))],
            'timezone' => ['nullable', 'string', 'max:64', Rule::in(timezone_identifiers_list())],
            'avatar' => ['nullable', 'image', 'max:4096'],
        ], [], [
            'name' => __('الاسم'), 'phone' => __('الهاتف'), 'locale' => __('اللغة'),
        ]);

        $avatarPath = $user->avatar_path;

        if ($request->hasFile('avatar')) {
            try {
                $avatarPath = $media->handle($request->file('avatar'), $user, 'avatars')->url();
            } catch (RuntimeException $e) {
                return back()->withErrors(['avatar' => $e->getMessage()]);
            }
        }

        // البريد لا يُغيَّر من هنا: تغييره يحتاج تأكيد العنوان الجديد
        $user->forceFill([
            'name' => $input['name'],
            'phone' => $input['phone'] ?? null,
            'locale' => $input['locale'],
            'timezone' => $input['timezone'] ?? $user->timezone,
            'avatar_path' => $avatarPath,
        ])->save();

        return back()->with('status', __('حُفظت بياناتك.'));
    }

    /**
     * طلب تغيير بريد الدخول.
     *
     * لا يُبدَّل في مكانه: يبقى معلّقاً حتى يُفتح رابطه من الصندوق
     * الجديد. خطأ مطبعي واحد في التبديل المباشر يقفل الحساب على
     * صاحبه، ومن استولى على جلسة يحوّل الحساب إلى بريده ثم يطلب
     * «نسيت كلمة المرور». وكلمة المرور الحالية تُطلب هنا لهذا.
     */
    public function requestEmailChange(Request $request): RedirectResponse
    {
        $user = $request->user();

        $input = $request->validate([
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->getKey())],
            'current_password' => ['required', 'string'],
        ], [], ['email' => __('البريد الجديد'), 'current_password' => __('كلمة المرور الحالية')]);

        if (! Hash::check($input['current_password'], (string) $user->password)) {
            throw ValidationException::withMessages(['current_password' => __('كلمة المرور غير صحيحة.')]);
        }

        if (strcasecmp($input['email'], (string) $user->email) === 0) {
            return back()->with('status', __('هذا بريدك الحالي بالفعل.'));
        }

        $token = Str::random(48);

        $user->forceFill([
            'pending_email' => $input['email'],
            'pending_email_token' => hash('sha256', $token),
            'pending_email_sent_at' => now(),
        ])->save();

        /*
         | الرابط يُرسَل إلى العنوان الجديد لا الحالي: التأكيد هو
         | إثبات أن الصندوق الجديد صندوقه هو. ولهذا يُرسَل بالبريد
         | مباشرةً لا عبر `notify()` — تلك تُرسل إلى بريد المستخدم
         | المسجَّل، وهو ما نحاول تغييره.
         */
        Mail::send('mail.email-change', [
            'user' => $user,
            'url' => url('/account/email/'.$token),
        ], fn ($message) => $message->to($input['email'])->subject(__('تأكيد بريدك الجديد')));

        return back()->with('status', __('أرسلنا رابط تأكيد إلى :email — افتحه من هناك لإتمام التغيير.', [
            'email' => $input['email'],
        ]));
    }

    /** تأكيد البريد الجديد من صندوقه — هنا وحدها يُبدَّل فعلاً. */
    public function confirmEmailChange(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();

        $valid = filled($user->pending_email)
            && filled($user->pending_email_token)
            && hash_equals((string) $user->pending_email_token, hash('sha256', $token))
            && $user->pending_email_sent_at?->gt(now()->subHour());

        if (! $valid) {
            return redirect(url('/account'))->withErrors([
                'email' => __('انتهت صلاحية الرابط أو أنه غير صحيح. اطلب تغييراً جديداً.'),
            ]);
        }

        // سُجّل العنوان لغيره بين الطلب والتأكيد
        if (User::where('email', $user->pending_email)->whereKeyNot($user->getKey())->exists()) {
            $user->forceFill(['pending_email' => null, 'pending_email_token' => null])->save();

            return redirect(url('/account'))->withErrors(['email' => __('هذا البريد صار مستعملاً. اختر غيره.')]);
        }

        $user->forceFill([
            'email' => $user->pending_email,
            'email_verified_at' => now(),
            'pending_email' => null,
            'pending_email_token' => null,
            'pending_email_sent_at' => null,
        ])->save();

        return redirect(url('/account'))->with('status', __('صار بريد دخولك :email.', ['email' => $user->email]));
    }

    /** إلغاء طلب معلّق — لمن غيّر رأيه أو أخطأ في الكتابة. */
    public function cancelEmailChange(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'pending_email' => null,
            'pending_email_token' => null,
            'pending_email_sent_at' => null,
        ])->save();

        return back()->with('status', __('أُلغي طلب تغيير البريد.'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $input = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => PasswordPolicy::rules(),
        ], [], ['password' => __('كلمة المرور الجديدة')]);

        if (! Hash::check($input['current_password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('كلمة المرور الحالية غير صحيحة.'),
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($input['password']),
            'password_changed_at' => now(),
        ])->save();

        // تدوير الجلسة: تغيير كلمة المرور يجب أن يُبطل ما سُرق منها
        $request->session()->regenerate();

        notify('account.password_changed', $user, [
            'changed_at' => now()->translatedFormat('j F Y — H:i'),
            'ip' => (string) $request->ip(),
            'url' => url('/account'),
        ]);

        return back()->with('status', __('تغيّرت كلمة المرور.'));
    }

    /**
     * حذف الحساب بيد صاحبه — شرط GDPR وما يقابله.
     *
     * حذفٌ ناعم لا محو: للطلب سجلّ مالي وفواتير تُحفظ سنوات بحكم
     * القانون، ومحوُ المستخدم يترك فاتورةً بلا صاحب.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless((bool) setting('users.self_delete', true), 403);
        abort_if($user->isOwner(), 403, __('صاحب المنصّة لا يحذف حسابه من هنا.'));

        $input = $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($input['password'], (string) $user->password)) {
            throw ValidationException::withMessages(['password' => __('كلمة المرور غير صحيحة.')]);
        }

        $user->forceFill([
            'status' => 'suspended',
            'email' => 'deleted-'.$user->getKey().'@deleted.invalid',
            'phone' => null,
            'name' => __('حساب محذوف'),
        ])->save();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(url('/'))->with('status', __('حُذف حسابك. نأسف لرحيلك.'));
    }
}
