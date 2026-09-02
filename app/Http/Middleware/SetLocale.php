<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * ADR-003: العربية بلا بادئة، والإنجليزية تحت /en/.
 * تحافظ هذه القاعدة على كل الروابط المؤرشفة حالياً في جوجل كما هي.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $segment = $request->segment(1);
        $locale = in_array($segment, config('locales.prefixed', []), true)
            ? $segment
            : config('locales.default', 'ar');

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
