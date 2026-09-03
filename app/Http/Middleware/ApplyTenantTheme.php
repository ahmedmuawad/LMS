<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Theming\ThemeManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يفعّل ثيم المشترك الحالي قبل تصيير أي عرض.
 * على النطاق المركزي (موقعنا) يبقى الثيم الافتراضي.
 */
final class ApplyTenantTheme
{
    public function __construct(private readonly ThemeManager $themes) {}

    public function handle(Request $request, Closure $next): Response
    {
        $theme = tenancy()->initialized
            ? (string) tenant('theme')
            : $this->themes->default();

        $this->themes->use($theme);

        return $next($request);
    }
}
