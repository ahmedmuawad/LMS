<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Access\Roles;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * حراسة مسار بصلاحية معلنة عليه.
 *
 *   ->middleware(EnsureAbility::class.':settings.manage')
 *
 * تُعلَن على المسار لا تُفحص داخل المتحكّم: الحراسة في التوجيه تُقرأ
 * بنظرة على ملف المسارات، والحراسة داخل الدوال تُنسى في أول دالة تُضاف.
 */
final class EnsureAbility
{
    public function __construct(private readonly Roles $roles) {}

    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(url('/login'));
        }

        // أيّ صلاحية من المذكورات تكفي: شاشة قد يفتحها دوران لسببين
        foreach ($abilities as $ability) {
            if ($this->roles->allows($user, $ability)) {
                return $next($request);
            }
        }

        throw new AccessDeniedHttpException(__('لا تملك صلاحية الوصول إلى هذه الشاشة.'));
    }
}
