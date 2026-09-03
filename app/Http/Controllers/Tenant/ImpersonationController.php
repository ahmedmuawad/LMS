<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Stancl\Tenancy\Features\UserImpersonation;

/**
 * الطرف المستقبِل للتذكرة داخل موقع المشترك.
 */
final class ImpersonationController
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $response = UserImpersonation::makeResponse($token);

        // علامة الجلسة هي ما يرسم الشريط التحذيري في اللوحة
        $request->session()->put('impersonating', true);

        return $response;
    }
}
