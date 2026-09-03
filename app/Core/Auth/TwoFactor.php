<?php

declare(strict_types=1);

namespace App\Core\Auth;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * التوثيق بخطوتين (TOTP).
 *
 * السرّ يُشفَّر لا يُهشَّم: التحقّق يحتاج قراءته. ويُثبَّت مفعّلاً
 * بعد أن يُدخل صاحبه رمزاً صحيحاً مرّة — التفعيل بلا تأكيد يُقفل
 * الحساب على صاحبه إن أخطأ في إعداد تطبيقه.
 */
final class TwoFactor
{
    private const RECOVERY_CODES = 8;

    public function __construct(private readonly Google2FA $engine) {}

    /** يُنشئ سرّاً جديداً غير مفعّل بعد، ويعيده لعرض رمز الـQR. */
    public function generateFor(User $user): string
    {
        $secret = $this->engine->generateSecretKey(32);

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($this->recoveryCodes())),
            'two_factor_confirmed_at' => null,
        ])->save();

        return $secret;
    }

    public function confirm(User $user, string $code): bool
    {
        if (! $this->verify($user, $code)) {
            return false;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return true;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function verify(User $user, string $code): bool
    {
        $secret = $this->secretFor($user);

        if ($secret === null) {
            return false;
        }

        // نافذة ±دورة واحدة: ساعة الهاتف تتقدّم أو تتأخّر ثانياتٍ
        return $this->engine->verifyKey($secret, trim($code), 1);
    }

    /** رمز الاستعادة يُستهلك مرّة واحدة ثم يُحذف من القائمة. */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $this->recoveryCodesFor($user);
        $given = mb_strtoupper(trim($code));

        $index = array_search($given, $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);

        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($codes))),
        ])->save();

        return true;
    }

    public function isEnabled(?User $user): bool
    {
        return $user !== null
            && $user->two_factor_secret !== null
            && $user->two_factor_confirmed_at !== null;
    }

    /** @return list<string> */
    public function recoveryCodesFor(User $user): array
    {
        if ($user->two_factor_recovery_codes === null) {
            return [];
        }

        $decoded = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }

    public function secretFor(User $user): ?string
    {
        return $user->two_factor_secret === null
            ? null
            : Crypt::decryptString($user->two_factor_secret);
    }

    /** رمز QR كـSVG مُضمَّن — بلا خدمة خارجية ترى سرّ المستخدم. */
    public function qrSvg(User $user, string $secret): string
    {
        $issuer = (string) (setting()->translated('general.site_name') ?: tenant('name') ?? config('app.name'));

        $url = $this->engine->getQRCodeUrl($issuer, (string) $user->email, $secret);

        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 0), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    /** @return list<string> */
    private function recoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODES))
            ->map(fn (): string => mb_strtoupper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }
}
