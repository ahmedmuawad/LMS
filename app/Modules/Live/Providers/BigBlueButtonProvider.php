<?php

declare(strict_types=1);

namespace App\Modules\Live\Providers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

/**
 * إنشاء غرفة على خادم BigBlueButton الخاص بالمشترك.
 *
 * ## توقيعٌ لا رمز دخول
 *
 * BBB لا يعرف OAuth: كل نداء يحمل `checksum` = sha1(اسمُ النداء +
 * سلسلةُ المعاملات + السرّ المشترك). فمن لا يعرف السرّ لا يستطيع
 * تكوين نداءٍ صالح — والسرّ لا يخرج من خادمنا أبداً.
 *
 * ## والرابط يُبنى ولا يُطلَب
 *
 * `join` نداءٌ يُفتح في المتصفّح لا يُنادى من الخادم، فنبني رابطه
 * موقَّعاً ونعطيه للمستخدم. أمّا `create` فيُنادى من عندنا قبل ذلك:
 * الدخول إلى غرفةٍ لم تُنشأ يردّ خطأً عند BBB.
 */
final class BigBlueButtonProvider
{
    /**
     * ينشئ الغرفة ويعيد رابطي الدخول.
     *
     * @return array{join_url:string, host_url:?string, external_id:?string}
     *
     * @throws RuntimeException
     */
    public function create(string $meetingId, string $name): array
    {
        $response = Http::timeout(20)->get($this->url('create', [
            'meetingID' => $meetingId,
            'name' => mb_substr($name, 0, 200),
            /*
             | الغرفة تبقى قائمةً بعد خروج آخر واحد.
             |
             | مدرّسٌ خرج ليعيد الاتصال يجد الغرفة قد أُغلقت وطلابه
             | خارجها. والدقائق العشر تكفي لعودةٍ ولا تُبقي غرفةً
             | مفتوحة على خادم المشترك بلا داعٍ.
             */
            'meetingExpireIfNoUserJoinedInMinutes' => 30,
            'meetingExpireWhenLastUserLeftInMinutes' => 10,
            'record' => 'false',
        ]));

        if ($response->failed()) {
            throw new RuntimeException(__('لا يستجيب خادم BigBlueButton. راجع عنوانه.'));
        }

        $this->assertSuccess((string) $response->body());

        return [
            'join_url' => '',   // يُبنى لكل مستخدم باسمه ودوره
            'host_url' => null,
            'external_id' => $meetingId,
        ];
    }

    /**
     * رابط دخولٍ موقَّع لهذا الشخص بدوره.
     *
     * لكل داخلٍ رابطُه: الاسم يظهر في قائمة الحاضرين، والدور يفصل
     * المدرّس عن الطالب — فلا يطرد طالبٌ زميله ولا يكتم مدرّسه.
     */
    public function joinUrl(string $meetingId, string $fullName, bool $moderator): string
    {
        return $this->url('join', [
            'meetingID' => $meetingId,
            'fullName' => mb_substr($fullName, 0, 100),
            'role' => $moderator ? 'MODERATOR' : 'VIEWER',
            'redirect' => 'true',
        ]);
    }

    public static function configured(): bool
    {
        return filled(setting('live.bbb_url')) && filled(setting('live.bbb_secret'));
    }

    /**
     * @param  array<string, string|int>  $params
     *
     * @throws RuntimeException
     */
    private function url(string $call, array $params): string
    {
        $base = rtrim((string) setting('live.bbb_url'), '/ ');
        $secret = (string) setting('live.bbb_secret');

        if ($base === '' || $secret === '') {
            throw new RuntimeException(__('لم يُضبط خادم BigBlueButton بعد.'));
        }

        // العنوان قد يُكتب بلا `/api` — والنداء لا يعمل بدونه
        if (! str_ends_with($base, '/api')) {
            $base .= '/api';
        }

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return $base.'/'.$call.'?'.$query.'&checksum='.sha1($call.$query.$secret);
    }

    /** @throws RuntimeException */
    private function assertSuccess(string $xml): void
    {
        $previous = libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if ($parsed === false) {
            throw new RuntimeException(__('ردّ خادم BigBlueButton غير مفهوم.'));
        }

        $code = (string) ($parsed->returncode ?? '');

        if ($code === 'SUCCESS') {
            return;
        }

        /*
         | خطأ التوقيع يُسمّى بعينه.
         |
         | هو أكثر ما يقع، وسببه سرٌّ منسوخ بمسافةٍ زائدة — ورسالةٌ
         | عامّة تجعل المشترك يشكّ في العنوان وهو صحيح.
         */
        throw new RuntimeException((string) ($parsed->messageKey ?? '') === 'checksumError'
            ? __('سرّ BigBlueButton غير صحيح.')
            : __('رفض خادم BigBlueButton إنشاء الغرفة.'));
    }
}
