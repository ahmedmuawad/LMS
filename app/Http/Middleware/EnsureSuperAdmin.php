<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('super')->user();

        if ($user === null) {
            return redirect()->guest(url('/super/login'));
        }

        if (! $user->canAccessSuperPanel()) {
            throw new AccessDeniedHttpException(__('حسابك موقوف.'));
        }

        return $next($request);
    }
}
