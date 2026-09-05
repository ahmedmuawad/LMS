<?php

declare(strict_types=1);

namespace App\Core\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * دعوة الطالب لتعيين كلمة مروره.
 *
 * كان المدرّس يُنشئ حساب طالبه فتُولَّد له كلمة مرور عشوائية لا
 * يعرفها أحد ولا تُرسَل — فالحساب موجود ولا يُدخَل إليه أبداً. ثم
 * يسأل المدرّس: من أين آتي بكلمة مروره؟ ولا جواب.
 *
 * ## رابطٌ يُنسَخ لا بريدٌ وحده
 *
 * البريد يُرسَل، لكن كثيراً من الطلبة لا بريد لهم أو لا يفتحونه —
 * والمدرّس يكلّمهم في واتساب. فالرابط يُعرض له لينسخه ويرسله بنفسه،
 * وهو الطريق الذي يعمل فعلاً.
 *
 * والرابط ينتهي بمدّة: دعوةٌ لا تنتهي تبقى صالحة بعد أن تُنسخ في
 * مجموعة ويقرؤها من ليس صاحبها.
 */
final class StudentInvite
{
    /** كم يوماً تبقى الدعوة صالحة */
    public const DAYS = 14;

    /**
     * ينشئ رابط تعيين كلمة المرور.
     *
     * يُعاد استعمال جدول استعادة كلمة المرور نفسه: هو الآلية ذاتها
     * (مفتاح موقّع لمدة)، وجدولٌ ثانٍ بمنطق مطابق يفترق عنه يوماً ما
     * في التحقّق أو الانتهاء.
     */
    public function linkFor(User $user): string
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()],
        );

        return url('/reset-password/'.$token.'?email='.urlencode((string) $user->email));
    }

    /**
     * هل لهذا الحساب بريدٌ حقيقي يصله شيء؟
     *
     * الطالب بلا بريد يأخذ عنواناً داخلياً (`…@students.…local`)
     * لتمييزه، وإرسالُ دعوةٍ إليه يملأ الطابور برسائل ترتدّ.
     */
    public function reachable(User $user): bool
    {
        return ! str_ends_with((string) $user->email, '.local');
    }
}
