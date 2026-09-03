<?php

declare(strict_types=1);

namespace App\Modules\Growth\Actions;

use App\Modules\Growth\Models\Affiliate;
use App\Modules\Growth\Models\AffiliateClick;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * تسجيل النقرة ونسبها لاحقاً.
 *
 * النافذة تُضبط من الإعدادات: يوم واحد يظلم المسوّق في منتج يُفكَّر
 * فيه أسبوعاً، وتسعون يوماً تنسب إليه بيعاً جاء من إعلاننا نحن.
 */
final class TrackAffiliate
{
    public const COOKIE = 'aff_ref';

    public function fromRequest(Request $request): ?Affiliate
    {
        $code = $request->string('ref')->trim()->value();

        if ($code === '') {
            return null;
        }

        $affiliate = Affiliate::active()->where('code', $code)->first();

        if ($affiliate === null) {
            return null;
        }

        // آخر نقرة تفوز: من عاد برابط مسوّق آخر فهو من أقنعه أخيراً
        $days = max(1, (int) setting('growth.affiliates_cookie_days', 30));

        Cookie::queue(Cookie::make(self::COOKIE, (string) $affiliate->getKey(), $days * 24 * 60));

        AffiliateClick::create([
            'affiliate_id' => $affiliate->getKey(),
            'landing_url' => mb_substr($request->fullUrl(), 0, 500),
            'referer' => mb_substr((string) $request->headers->get('referer'), 0, 500) ?: null,
            // العنوان مُهشَّم: يكفي لكشف التلاعب ولا يحفظ هوية أحد
            'ip_hash' => hash('sha256', (string) $request->ip().config('app.key')),
            'country' => $request->headers->get('CF-IPCountry'),
        ]);

        $affiliate->increment('clicks_count');

        return $affiliate;
    }

    /** المسوّق المحفوظ في الكوكي — إن كان ما يزال نشطاً. */
    public function current(Request $request): ?Affiliate
    {
        $id = $request->cookie(self::COOKIE);

        if (! is_string($id) && ! is_int($id)) {
            return null;
        }

        return Affiliate::active()->find((int) $id);
    }

    public function forget(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE));
    }
}
