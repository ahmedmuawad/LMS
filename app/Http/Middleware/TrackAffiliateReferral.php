<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Growth\Actions\TrackAffiliate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * التقاط `?ref=` على أي صفحة عامة.
 *
 * على كل صفحة لا على الرئيسية وحدها: المسوّق يشارك رابط الكورس
 * نفسه لا رابط الموقع، وقصْره على «/» يُضيّع أغلب النقرات.
 */
final class TrackAffiliateReferral
{
    public function __construct(private readonly TrackAffiliate $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->filled('ref') && (bool) setting('growth.affiliates_enabled', false)) {
            $this->tracker->fromRequest($request);
        }

        return $next($request);
    }
}
