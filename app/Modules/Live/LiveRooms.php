<?php

declare(strict_types=1);

namespace App\Modules\Live;

use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Session;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * إنشاء غرف الحصص المباشرة.
 *
 * ## لماذا Jitsi أوّلاً
 *
 * الباقات تَعِد بأربعة مزوّدين ولا سطرَ لأيٍّ منها؛ والموجود حقلُ
 * رابطٍ يلصق فيه المدرّس رابطاً أنشأه بنفسه. وZoom وMeet يحتاجان
 * ربط حساب وموافقة تطبيق، وذلك بابٌ يقف عنده أكثر المدرّسين. أمّا
 * Jitsi فيعمل بلا حساب ولا مفاتيح، فتصير الميزة عاملةً لكل مشترك
 * من أول يوم — ومن أراد Zoom ربَط حسابه لاحقاً.
 *
 * ## سرّ الغرفة هو اسمها
 *
 * الخادم العام لا يعرف مشتركينا، فحمايةُ الغرفة أن يكون اسمها غير
 * قابل للتخمين: يُشتقّ من مفتاح التطبيق ومعرّف المشترك ومعرّف
 * الحصة، فلا يتكرّر ولا يُخمَّن ولا يتغيّر بين فتحتين — والاسم
 * الثابت شرطٌ ليجد الطالب مدرّسه في الغرفة نفسها.
 */
final class LiveRooms
{
    public function provider(): string
    {
        return (string) setting('live.provider', config('live.default', 'jitsi'));
    }

    /** غرفة حصة مجدولة */
    public function forSession(Session $session): ?LiveMeeting
    {
        $start = $session->date?->copy()->setTimeFromTimeString((string) $session->starts_at);
        $end = $session->ends_at !== null && $session->date !== null
            ? $session->date->copy()->setTimeFromTimeString((string) $session->ends_at)
            : $start?->copy()->addHours(2);

        // رابطٌ ثبّته المدرّس بنفسه يسبق أي توليد: هو أدرى بحصّته
        $manual = $session->meeting_url ?: $session->group?->meeting_url;

        if (filled($manual)) {
            return new LiveMeeting('manual', (string) $manual, null, $this->opens($start), $this->closes($end));
        }

        if (! $this->autoEnabled() || $start === null) {
            return null;
        }

        return $this->build('session-'.$session->getKey(), $start, $end);
    }

    /**
     * غرفة مجموعة — للحصة العارضة خارج الجدول.
     *
     * بلا نافذة زمنية: المدرّس قد يفتحها متى شاء، والحصص المجدولة
     * هي التي لها مواعيد.
     */
    public function forGroup(Group $group): ?LiveMeeting
    {
        if (filled($group->meeting_url)) {
            return new LiveMeeting('manual', (string) $group->meeting_url);
        }

        return $this->autoEnabled()
            ? $this->build('group-'.$group->getKey(), null, null)
            : null;
    }

    private function build(string $seed, ?Carbon $start, ?Carbon $end): ?LiveMeeting
    {
        $provider = $this->provider();

        /*
         | ما لم يُبنَ بعد لا يُولّد رابطاً مكسوراً.
         |
         | Zoom وMeet وBBB مُعلَنة في الإعدادات ولم تُنفَّذ؛ واختيار
         | أحدها يعني الرجوع إلى الرابط اليدوي، لا زرّاً يفتح صفحة
         | خطأ عند مزوّد لا نكلّمه أصلاً.
         */
        if ($provider !== 'jitsi') {
            return null;
        }

        $domain = trim((string) setting('live.jitsi_domain', config('live.providers.jitsi.domain', 'meet.jit.si')), '/ ');
        $room = $this->room($seed);

        return new LiveMeeting(
            'jitsi',
            'https://'.$domain.'/'.$room,
            $room,
            $this->opens($start),
            $this->closes($end),
        );
    }

    /**
     * اسم الغرفة: مقروءٌ أوّله وسرٌّ آخره.
     *
     * البادئة تجعل المدرّس يعرف غرفته حين يراها، والتجزئة تجعلها
     * غير قابلة للتخمين — ومفتاح التطبيق داخل التجزئة، فلا يستطيع
     * من عرف معرّف الحصة أن يحسب الاسم.
     */
    private function room(string $seed): string
    {
        $tenant = tenant()?->getTenantKey() ?? 'central';
        $slug = Str::slug((string) (tenant()?->slug ?? 'room'));

        $hash = hash_hmac('sha256', $tenant.'|'.$seed, (string) config('app.key'));

        return $slug.'-'.$seed.'-'.mb_substr($hash, 0, 16);
    }

    private function autoEnabled(): bool
    {
        return (bool) setting('live.auto_rooms', true) && module_enabled('live');
    }

    private function opens(?Carbon $start): ?Carbon
    {
        return $start?->copy()->subMinutes((int) config('live.opens_before_minutes', 30));
    }

    private function closes(?Carbon $end): ?Carbon
    {
        return $end?->copy()->addMinutes((int) config('live.closes_after_minutes', 30));
    }
}
