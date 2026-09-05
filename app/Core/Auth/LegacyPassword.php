<?php

declare(strict_types=1);

namespace App\Core\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * كلمات مرور ووردبريس المنقولة.
 *
 * `legacy_hash` كان عموداً يُعرض في اللوحة ولا يقرؤه أحدٌ عند
 * الدخول — فكلّ طالبٍ مستورَد من ووردبريس لا يستطيع الدخول أبداً،
 * ولا رسالة تشرح له لماذا. وهذا يُبطل الاستيراد كلّه: مدرسةٌ
 * نقلَت مئتي طالب فلم يدخل منهم أحد.
 *
 * ## phpass بإيجاز
 *
 * ووردبريس يحفظ `$P$B…` — تجزئةٌ متكرّرة بـMD5، عدد تكراراتها
 * مُرمَّز في الحرف الرابع. وهي ضعيفة بمعايير اليوم، ولذلك لا
 * تُترك: أول دخولٍ ناجح يُعيد التجزئة بمعيارنا ويُطفئ العلَم،
 * فتنتقل المدرسة كلّها خلال أسابيع بلا أن يشعر أحد.
 */
final class LegacyPassword
{
    /** ترميز phpass الستّيني — ليس Base64 القياسي */
    private const ITOA64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /**
     * يتحقّق من كلمة مرور قديمة، ويُحدّثها إلى معيارنا عند النجاح.
     */
    public function verifyAndUpgrade(User $user, string $plain): bool
    {
        if (! $user->legacy_hash) {
            return false;
        }

        if (! $this->matches($plain, (string) $user->password)) {
            return false;
        }

        /*
         | الترقية عند النجاح لا عند المحاولة.
         |
         | فمحاولةٌ خاطئة لا تلمس شيئاً، والناجحة وحدها هي التي
         | تُثبت أن ما بين يدينا هو كلمة المرور الحقيقية.
         */
        $user->forceFill([
            'password' => Hash::make($plain),
            'legacy_hash' => false,
            'password_changed_at' => now(),
        ])->save();

        return true;
    }

    /** هل تُطابق هذه الكلمةُ تجزئةَ phpass؟ */
    public function matches(string $plain, string $hash): bool
    {
        // ووردبريس يقبل MD5 عارياً من نسخه القديمة جداً
        if (! str_starts_with($hash, '$P$') && ! str_starts_with($hash, '$H$')) {
            return strlen($hash) === 32 && hash_equals($hash, md5($plain));
        }

        return hash_equals($hash, $this->crypt($plain, $hash));
    }

    /**
     * يعيد تنفيذ خوارزمية phpass بالضبط.
     *
     * لا مكتبة لهذه: الحزم الموجودة تحمل ووردبريس كاملاً لأجل
     * أربعين سطراً — والخوارزمية ثابتة منذ ٢٠٠٥ فلا تتغيّر تحتنا.
     */
    private function crypt(string $password, string $setting): string
    {
        $output = '*0';

        if (mb_substr($setting, 0, 2, '8bit') === $output) {
            $output = '*1';
        }

        $id = mb_substr($setting, 0, 3, '8bit');

        if ($id !== '$P$' && $id !== '$H$') {
            return $output;
        }

        $countLog2 = strpos(self::ITOA64, $setting[3]);

        if ($countLog2 < 7 || $countLog2 > 30) {
            return $output;
        }

        $count = 1 << $countLog2;
        $salt = mb_substr($setting, 4, 8, '8bit');

        if (mb_strlen($salt, '8bit') !== 8) {
            return $output;
        }

        $hash = md5($salt.$password, true);

        do {
            $hash = md5($hash.$password, true);
        } while (--$count);

        return mb_substr($setting, 0, 12, '8bit').$this->encode64($hash, 16);
    }

    private function encode64(string $input, int $count): string
    {
        $output = '';
        $i = 0;

        do {
            $value = ord($input[$i++]);
            $output .= self::ITOA64[$value & 0x3F];

            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }

            $output .= self::ITOA64[($value >> 6) & 0x3F];

            if ($i++ >= $count) {
                break;
            }

            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }

            $output .= self::ITOA64[($value >> 12) & 0x3F];

            if ($i++ >= $count) {
                break;
            }

            $output .= self::ITOA64[($value >> 18) & 0x3F];
        } while ($i < $count);

        return $output;
    }
}
