<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Access\Roles;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * لوحة التحكم لأصحاب الأدوار الإدارية فقط.
 * الطالب المسجّل ليس زائراً — نمنعه بـ 403 لا بإعادته لصفحة الدخول،
 * وإلا بدا الأمر كأن جلسته انتهت.
 */
final class EnsurePanelAccess
{
    public function __construct(private readonly Roles $roles) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(url('/login'));
        }

        if (! $this->roles->mayEnterPanel($user)) {
            throw new AccessDeniedHttpException(__('حسابك لا يملك صلاحية الدخول إلى لوحة التحكم.'));
        }

        return $next($request);
    }
}
