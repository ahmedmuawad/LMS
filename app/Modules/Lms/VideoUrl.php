<?php

declare(strict_types=1);

namespace App\Modules\Lms;

use App\Modules\Lms\Models\Lesson;
use Illuminate\Support\Facades\URL;

/**
 * ADR-008 — رابط المشاهدة يُوقَّع لكل طالب ولدقائق معدودة.
 *
 * الرابط الثابت يُنسخ ويُوزَّع في مجموعة واتساب خلال دقائق؛ الرابط
 * الموقَّع يموت قبل أن يصل. لا نخزّن رابطاً مباشراً في القاعدة أبداً.
 */
final class VideoUrl
{
    public function __construct(private readonly int $ttlSeconds = 21600) {}

    public function for(Lesson $lesson, ?int $userId = null): ?string
    {
        if (blank($lesson->video_id)) {
            return null;
        }

        return match ($lesson->video_provider) {
            'bunny' => $this->bunny($lesson, $userId),
            'youtube' => 'https://www.youtube-nocookie.com/embed/'.$lesson->video_id,
            'vimeo' => 'https://player.vimeo.com/video/'.$lesson->video_id,
            default => $this->hosted($lesson),
        };
    }

    /**
     * الملفّ المرفوع على خادمنا — برابطٍ موقَّع ينتهي.
     *
     * كان يُعطى الرابط كما هو، فيُنسَخ من «مصدر الصفحة» ويُلصَق
     * فيفتحه من لم يدفع، ويبقى يعمل إلى الأبد. والتوقيع يجعله
     * ينتهي، والبثّ يفحص التسجيل عند كل طلب.
     *
     * أمّا العنوان الخارجي (رابط على خادمٍ آخر) فيُعاد كما هو: لا
     * نملك حمايته، ولا نُوهم صاحبه أننا نحميه.
     */
    private function hosted(Lesson $lesson): ?string
    {
        $value = (string) $lesson->video_id;

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return URL::temporarySignedRoute(
            'lesson.video',
            now()->addSeconds($this->ttlSeconds),
            ['lesson' => $lesson->getKey()],
        );
    }

    /**
     * توقيع Bunny Stream: SHA256 لمفتاح التوقيع مع المسار ووقت الانتهاء.
     * المفتاح لا يغادر الخادم، والرابط ينتهي بنفسه.
     */
    private function bunny(Lesson $lesson, ?int $userId): ?string
    {
        $zone = setting('integrations.video_pull_zone');
        $key = setting('integrations.video_token_key');

        if (blank($zone) || blank($key)) {
            return null;
        }

        $path = '/'.$lesson->video_id.'/playlist.m3u8';
        $expires = now()->addSeconds($this->ttlSeconds)->timestamp;

        // ربط الرابط بالمستخدم يجعل تسريبه يقود إلى صاحبه
        $user = $userId === null ? '' : '&user='.$userId;
        $token = hash('sha256', $key.$path.$expires, true);

        return 'https://'.$zone.$path
            .'?token='.rtrim(strtr(base64_encode($token), '+/', '-_'), '=')
            .'&expires='.$expires.$user;
    }

    public function isSigned(Lesson $lesson): bool
    {
        return $lesson->video_provider === 'bunny';
    }
}
