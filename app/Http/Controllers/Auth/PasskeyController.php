<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Core\Auth\Passkeys;
use App\Models\User;
use App\Modules\Lms\Models\Passkey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * تسجيل مفاتيح المرور واستعمالها.
 *
 * ## التحدّي في الجلسة لا في الطلب
 *
 * لو عاد المتصفّح بالتحدّي الذي أعطيناه لصار مهاجمٌ يخترع تحدّياً
 * ويوقّعه — وسقط الأساس كلّه. فهو يُحفظ عندنا ويُقارَن بما وقّعه.
 *
 * ## وينتهي بعد استعماله
 *
 * تحدٍّ يبقى في الجلسة يُعاد استعماله؛ وهو معنى «مرّة واحدة».
 */
final class PasskeyController
{
    public function __construct(private readonly Passkeys $passkeys) {}

    /** خيارات التسجيل — لمن دخل بالفعل */
    public function registerOptions(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if(! $user instanceof User || ! $this->passkeys->enabled(), 403);

        $options = $this->passkeys->registrationOptions($user);

        $request->session()->put('passkey_creation', $options);

        return response()->json($options);
    }

    /** حفظ المفتاح بعد أن وقّعه الجهاز */
    public function register(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if(! $user instanceof User || ! $this->passkeys->enabled(), 403);

        $options = $request->session()->pull('passkey_creation');

        if (! is_array($options)) {
            return response()->json(['message' => __('انتهت الجلسة — أعد المحاولة.')], 422);
        }

        try {
            $passkey = $this->passkeys->register(
                $user,
                (string) $request->getContent(),
                $options,
                $request->getHost(),
                // الاسم عربيٌّ غالباً، والترويسات لا تحمل إلا ASCII — فيُرمَّز ويُفكّ
                rawurldecode((string) $request->header('X-Passkey-Label', '')),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $passkey->getKey(), 'label' => $passkey->label], 201);
    }

    /** خيارات الدخول — بلا بريدٍ ولا اسم */
    public function loginOptions(Request $request): JsonResponse
    {
        abort_unless($this->passkeys->enabled(), 403);

        $options = $this->passkeys->loginOptions();

        $request->session()->put('passkey_request', $options);

        return response()->json($options);
    }

    /** الدخول بالمفتاح */
    public function login(Request $request): JsonResponse
    {
        abort_unless($this->passkeys->enabled(), 403);

        $options = $request->session()->pull('passkey_request');

        if (! is_array($options)) {
            return response()->json(['message' => __('انتهت الجلسة — أعد المحاولة.')], 422);
        }

        try {
            $user = $this->passkeys->verify(
                (string) $request->getContent(),
                $options,
                $request->getHost(),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => __('هذا الحساب غير مفعّل. راسل الدعم.')], 422);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $user->forceFill(['last_seen_at' => now()])->save();

        /*
         | ولا توثيقَ بخطوتين بعده.
         |
         | المفتاح خطوتان أصلاً: شيءٌ تملكه (الجهاز) وشيءٌ أنت
         | (بصمتُك تفتحه). وطلبُ رمزٍ بعده يجعل الطريقة الأأمن هي
         | الأطول — فلا يستعملها أحد.
         */
        return response()->json([
            'redirect' => url($user->canAccessPanel() ? '/admin/dashboard' : '/me'),
        ]);
    }

    /** حذف مفتاح من حساب صاحبه */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();

        abort_if(! $user instanceof User, 403);

        // مفتاحُه هو لا مفتاح غيره — والشرط في الاستعلام لا بعد جلبه
        Passkey::whereKey($id)->where('user_id', $user->getKey())->delete();

        return back()->with('status', __('حُذف المفتاح.'));
    }
}
