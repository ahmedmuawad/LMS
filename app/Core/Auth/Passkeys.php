<?php

declare(strict_types=1);

namespace App\Core\Auth;

use App\Models\User;
use App\Modules\Lms\Models\Passkey;
use Cose\Algorithms;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * مفاتيح المرور — دخولٌ ببصمةٍ أو وجه، بلا كلمة مرور.
 *
 * ## إعدادٌ كان يَعِد ولا يفعل
 *
 * «مفاتيح المرور» مفتاحٌ مُشغَّلٌ افتراضياً في شاشة المستخدمين منذ
 * البداية ولا سطرَ يقرؤه. فيظنّ المشترك أن منصّته تدعمها، ولا زرَّ
 * في شاشة الدخول ولا في حساب أحد.
 *
 * ## ولماذا هي أهمّ من ميزةٍ فاخرة
 *
 * كلمةُ مرورٍ تُسرَّب أو تُخمَّن أو تُعاد في عشرة مواقع؛ والمفتاح
 * لا يخرج من الجهاز أصلاً — يُوقَّع فيه ويُرسَل التوقيع وحده. ولا
 * يعمل إلا على نطاقنا، فالتصيّد لا ينفع معه: صفحةٌ مزوّرة لا تستطيع
 * أن تطلبه.
 *
 * ## والتشفير لا يُكتب بيدنا
 *
 * التحقّق من CBOR وCOSE وسلاسل الشهادات وعدّاد التكرار عملُ مكتبةٍ
 * مراجَعة؛ وخطأٌ في سطرٍ منه يجعل التوقيع يُقبل وهو مزوَّر — ولا
 * يظهر في اختبار.
 *
 * ## والنطاق هو الحدّ
 *
 * `rpId` نطاقُ المشترك نفسه: مفتاحٌ سُجّل في `eng-heba` لا يفتح
 * `gareb`. وهذا ما نريده — كلُّ أكاديميةٍ مستقلّة.
 */
final class Passkeys
{
    /** كم ثانيةً ينتظر الجهاز قبل أن يستسلم */
    private const TIMEOUT = 60_000;

    public function enabled(): bool
    {
        return (bool) setting('users.passkeys', true);
    }

    /**
     * خيارات التسجيل — يوقّعها جهاز المستخدم.
     *
     * @return array<string, mixed>
     */
    public function registrationOptions(User $user): array
    {
        $options = PublicKeyCredentialCreationOptions::create(
            rp: $this->relyingParty(),
            user: $this->userEntity($user),
            challenge: random_bytes(32),

            /*
             | الخوارزميات بترتيب الأفضلية.
             |
             | ES256 تدعمها كل الأجهزة تقريباً، وRS256 لأجل Windows
             | Hello القديم. وقائمةٌ فارغة تجعل بعض الأجهزة ترفض
             | التسجيل بلا رسالة.
             */
            pubKeyCredParams: [
                PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_ES256),
                PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_RS256),
            ],

            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,

                /*
                 | مفتاحٌ مقيمٌ في الجهاز.
                 |
                 | وبدونه يجب أن يكتب بريده أولاً كي نعرف أيّ مفتاحٍ
                 | نطلب — وهذا يُبطل نصف الفائدة. والمقيم يجعل الزرّ
                 | وحده كافياً.
                 */
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            ),

            /*
             | ولا نطلب شهادة المصنّع.
             |
             | `none` تعني أننا لا نسأل الجهاز عن هويّته؛ ولا نحتاجها:
             | نحن نتحقّق من التوقيع لا من ماركة الجهاز. وطلبُها يُظهر
             | للمستخدم تحذيرَ خصوصيةٍ في بعض المتصفّحات.
             */
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,

            // ما سجّله من قبل يُستبعَد كي لا يسجّل المفتاح نفسه مرّتين
            excludeCredentials: $this->descriptorsFor($user),

            timeout: self::TIMEOUT,
        );

        return $this->toArray($options);
    }

    /**
     * يتحقّق من الردّ ويحفظ المفتاح.
     *
     * @param  array<string, mixed>  $options  ما أُرسل للجهاز، من الجلسة
     *
     * @throws RuntimeException
     */
    public function register(User $user, string $credential, array $options, string $host, string $label): Passkey
    {
        $serializer = $this->serializer();

        $publicKey = $serializer->deserialize($credential, PublicKeyCredential::class, 'json');

        if (! $publicKey->response instanceof AuthenticatorAttestationResponse) {
            throw new RuntimeException(__('ردّ الجهاز غير مفهوم.'));
        }

        $creation = $serializer->deserialize(
            (string) json_encode($options),
            PublicKeyCredentialCreationOptions::class,
            'json',
        );

        try {
            $record = AuthenticatorAttestationResponseValidator::create(
                (new CeremonyStepManagerFactory)->creationCeremony(),
            )->check($publicKey->response, $creation, $host);
        } catch (Throwable $e) {
            Log::warning('رُفض تسجيل مفتاح مرور: '.$e->getMessage());

            throw new RuntimeException(__('تعذّر تسجيل المفتاح — حاول مرّة أخرى.'));
        }

        return Passkey::create([
            'user_id' => $user->getKey(),
            'label' => $label !== '' ? $label : __('مفتاح'),
            'credential_id' => base64_encode($record->publicKeyCredentialId),
            'credential' => $serializer->serialize($record, 'json'),
            'sign_count' => $record->counter,
            'last_used_at' => now(),
        ]);
    }

    /**
     * خيارات الدخول — بلا بريدٍ ولا اسم.
     *
     * المفتاح المقيم يحمل هوية صاحبه، فيختاره المتصفّح من قائمته
     * ويرسله إلينا. ومطالبةُ المستخدم ببريده أولاً تُبطل نصف
     * الفائدة.
     *
     * @return array<string, mixed>
     */
    public function loginOptions(): array
    {
        return $this->toArray(PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(32),
            rpId: $this->rpId(),
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            timeout: self::TIMEOUT,
        ));
    }

    /**
     * يتحقّق من التوقيع ويعيد صاحبه.
     *
     * @param  array<string, mixed>  $options  ما أُرسل للجهاز، من الجلسة
     *
     * @throws RuntimeException
     */
    public function verify(string $credential, array $options, string $host): User
    {
        $serializer = $this->serializer();

        $publicKey = $serializer->deserialize($credential, PublicKeyCredential::class, 'json');

        if (! $publicKey->response instanceof AuthenticatorAssertionResponse) {
            throw new RuntimeException(__('ردّ الجهاز غير مفهوم.'));
        }

        $stored = Passkey::where('credential_id', base64_encode($publicKey->rawId))->first();

        if ($stored === null) {
            throw new RuntimeException(__('هذا المفتاح غير مسجَّل هنا.'));
        }

        $request = $serializer->deserialize(
            (string) json_encode($options),
            PublicKeyCredentialRequestOptions::class,
            'json',
        );

        $source = $serializer->deserialize(
            (string) $stored->credential,
            PublicKeyCredentialSource::class,
            'json',
        );

        try {
            $record = AuthenticatorAssertionResponseValidator::create(
                (new CeremonyStepManagerFactory)->requestCeremony(),
            )->check(
                $source,
                $publicKey->response,
                $request,
                $host,
                $publicKey->response->userHandle,
            );
        } catch (Throwable $e) {
            Log::warning('رُفض دخول بمفتاح مرور: '.$e->getMessage());

            throw new RuntimeException(__('تعذّر التحقّق من المفتاح.'));
        }

        /*
         | عدّاد التوقيع يُحفظ بعد كل دخول.
         |
         | جهازٌ حقيقي يزيده في كل مرّة؛ ونسخةٌ مستنسخة منه تعيد رقماً
         | رأيناه — والمكتبة ترفضها إن حفظناه. وإهمالُ حفظه يُبطل
         | الحماية بلا أن يظهر شيء.
         */
        $stored->forceFill([
            'sign_count' => $record->counter,
            'credential' => $serializer->serialize($record, 'json'),
            'last_used_at' => now(),
        ])->save();

        $user = $stored->user;

        if (! $user instanceof User) {
            throw new RuntimeException(__('صاحب هذا المفتاح لم يعد موجوداً.'));
        }

        return $user;
    }

    /** @return list<PublicKeyCredentialDescriptor> */
    private function descriptorsFor(User $user): array
    {
        return Passkey::where('user_id', $user->getKey())->get()
            ->map(fn (Passkey $key): PublicKeyCredentialDescriptor => PublicKeyCredentialDescriptor::create(
                'public-key',
                (string) base64_decode((string) $key->credential_id, true),
            ))
            ->all();
    }

    private function relyingParty(): PublicKeyCredentialRpEntity
    {
        return PublicKeyCredentialRpEntity::create(site_name(), $this->rpId());
    }

    /**
     * نطاق المشترك — لا نطاق المنصّة.
     *
     * مفتاحٌ سُجّل في `eng-heba` لا يفتح `gareb`؛ وهو المطلوب: كلُّ
     * أكاديميةٍ مستقلّة عن جارتها.
     */
    private function rpId(): string
    {
        return (string) (request()?->getHost() ?? parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    private function userEntity(User $user): PublicKeyCredentialUserEntity
    {
        return PublicKeyCredentialUserEntity::create(
            (string) $user->email,

            /*
             | المعرّف ثابتٌ ولا يحمل معنى.
             |
             | لو كان البريد لتغيّر بتغيّره فضاعت مفاتيحه؛ ولو كان
             | الرقم لأخبر من يقرأ الجهاز كم مستخدماً عندنا.
             */
            (string) ($user->passkey_handle ?: $this->assignHandle($user)),

            (string) $user->name,
        );
    }

    private function assignHandle(User $user): string
    {
        $handle = Str::uuid()->toString();

        $user->forceFill(['passkey_handle' => $handle])->save();

        return $handle;
    }

    /** @return array<string, mixed> */
    private function toArray(object $options): array
    {
        return (array) json_decode($this->serializer()->serialize($options, 'json'), true);
    }

    private function serializer(): SerializerInterface
    {
        $manager = AttestationStatementSupportManager::create();
        $manager->add(NoneAttestationStatementSupport::create());

        return (new WebauthnSerializerFactory($manager))->create();
    }
}
