<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Core\Access\Ability;
use App\Core\Entitlements\Models\Plan;
use App\Core\Entitlements\Quota;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * استهلاك المشترك من حدود باقته.
 *
 * الحدّ الذي لا يُرى يُصطدَم به فجأةً. وهذه الشاشة تجعل الاقتراب
 * مرئياً قبل الاصطدام — فمن رأى تسعين بالمئة رقّى قبل أن يقف عمله.
 */
final class UsageController
{
    public function __invoke(Request $request, Quota $quota): View
    {
        abort_unless($request->user()->can(Ability::BILLING_MANAGE)
            || $request->user()->can(Ability::SETTINGS_MANAGE), 403);

        $tenant = tenant();

        return view('tenant.usage', [
            'rows' => $quota->overview(),
            'tenant' => $tenant,
            'plan' => Plan::find($tenant?->plan_key),
        ]);
    }
}
