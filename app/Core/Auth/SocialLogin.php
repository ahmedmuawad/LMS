<?php

declare(strict_types=1);

namespace App\Core\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * الدخول بحسابٍ اجتماعي — OAuth2 مباشرةً.
 *
 * ## لماذا بلا Socialite
 *
 * الحزمة تشترط Guzzle 7 والمشروع على 8، وإنزالُ Guzzle لأجلها
 * يمسّ كلّ طلبٍ خارجي في المنصّة: بوّابات الدفع وواتساب وأسعار
 * الصرف. وتدفّقُ «رمز التفويض» نفسه ثلاثون سطراً — وهذه أرخص من
 * تبعيةٍ تحكم في نصف المشروع.
 *
 * ## والحالة تُحفظ في الجلسة
 *
 * بلا `state` يستطيع مهاجمٌ أن يجعل الضحيّة تدخل بحسابه هو، فيصير
 * كلُّ ما تكتبه بعدها في حسابٍ يملكه. وهي سطرٌ يُنسى كثيراً.
 *
 * ## وApple مؤجَّلة عمداً
 *
 * سرُّها ليس نصّاً بل JWT يُوقَّع بمفتاح `.p8` ويُجدَّد كل ستّة
 * أشهر — ووعدٌ بزرٍّ يعمل مرّةً ثم يصمت بلا رسالة أسوأ من غيابه.
 */
final class SocialLogin
{
    /**
     * ما نعرف كيف نتحدّث إليه.
     *
     * @var array<string, array{auth:string, token:string, user:string, scope:string, label:string}>
     */
    private const PROVIDERS = [
        'google' => [
            'auth' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token' => 'https://oauth2.googleapis.com/token',
            'user' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'scope' => 'openid email profile',
            'label' => 'Google',
        ],
        'facebook' => [
            'auth' => 'https://www.facebook.com/v21.0/dialog/oauth',
            'token' => 'https://graph.facebook.com/v21.0/oauth/access_token',
            'user' => 'https://graph.facebook.com/v21.0/me?fields=id,name,email',
            'scope' => 'email public_profile',
            'label' => 'Facebook',
        ],
        'linkedin' => [
            'auth' => 'https://www.linkedin.com/oauth/v2/authorization',
            'token' => 'https://www.linkedin.com/oauth/v2/accessToken',
            'user' => 'https://api.linkedin.com/v2/userinfo',
            'scope' => 'openid email profile',
            'label' => 'LinkedIn',
        ],
    ];

    /** الشبكات التي فعّلها المشترك ووضع مفاتيحها فعلاً */
    public function available(): array
    {
        $ready = [];

        foreach (self::PROVIDERS as $key => $provider) {
            if ($this->configured($key)) {
                $ready[$key] = $provider['label'];
            }
        }

        return $ready;
    }

    public function configured(string $provider): bool
    {
        if (! isset(self::PROVIDERS[$provider])) {
            return false;
        }

        /*
         | المفتاح والسرّ كلاهما، لا التفعيل وحده.
         |
         | مشتركٌ فعّل Google ولم يضع مفاتيحه يرى زرّاً يقود إلى
         | صفحة خطأٍ من Google — وهو لا يفهم لماذا.
         */
        return (bool) setting('users.'.$provider.'_enabled', false)
            && filled(setting('users.'.$provider.'_client_id'))
            && filled(setting('users.'.$provider.'_client_secret'));
    }

    /** رابط الذهاب إلى الشبكة — مع `state` تُحفظ في الجلسة */
    public function redirectUrl(string $provider, string $state): string
    {
        $config = $this->config($provider);

        return $config['auth'].'?'.http_build_query([
            'client_id' => (string) setting('users.'.$provider.'_client_id'),
            'redirect_uri' => $this->callbackUrl($provider),
            'response_type' => 'code',
            'scope' => $config['scope'],
            'state' => $state,
        ]);
    }

    /**
     * يبدّل الرمز ببيانات المستخدم.
     *
     * @return array{id:string, email:?string, name:?string}
     *
     * @throws RuntimeException
     */
    public function user(string $provider, string $code): array
    {
        $config = $this->config($provider);

        $token = Http::asForm()->timeout(20)->post($config['token'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->callbackUrl($provider),
            'client_id' => (string) setting('users.'.$provider.'_client_id'),
            'client_secret' => (string) setting('users.'.$provider.'_client_secret'),
        ]);

        if ($token->failed() || blank($token->json('access_token'))) {
            throw new RuntimeException(__('تعذّر إكمال الدخول — حاول مرّة أخرى.'));
        }

        $profile = Http::withToken((string) $token->json('access_token'))
            ->timeout(20)->get($config['user']);

        if ($profile->failed()) {
            throw new RuntimeException(__('تعذّرت قراءة حسابك من :net.', [
                'net' => $config['label'],
            ]));
        }

        return [
            'id' => (string) ($profile->json('sub') ?? $profile->json('id') ?? ''),
            'email' => $profile->json('email'),
            'name' => $profile->json('name') ?? $profile->json('given_name'),
        ];
    }

    public function label(string $provider): string
    {
        return self::PROVIDERS[$provider]['label'] ?? $provider;
    }

    public function callbackUrl(string $provider): string
    {
        return url('/auth/'.$provider.'/callback');
    }

    public static function newState(): string
    {
        return Str::random(40);
    }

    /** @return array{auth:string, token:string, user:string, scope:string, label:string} */
    private function config(string $provider): array
    {
        if (! $this->configured($provider)) {
            throw new RuntimeException(__('هذه الطريقة غير مفعّلة.'));
        }

        return self::PROVIDERS[$provider];
    }
}
