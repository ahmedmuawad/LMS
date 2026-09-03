<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Core\Billing\Models\Invoice;
use App\Core\Billing\Models\Subscription;
use App\Core\Entitlements\Models\Feature;
use App\Core\Entitlements\Models\Plan;
use Illuminate\View\View;

/**
 * ما يراه المشترك عن اشتراكه هو: باقته وفواتيره وخيارات الترقية.
 * البيانات كلها في القاعدة المركزية، ويُقرأ منها بمعرّفه وحده.
 */
final class BillingController
{
    public function __invoke(): View
    {
        $tenant = tenant();

        $plans = Plan::with('features')->where('is_active', true)->where('is_public', true)
            ->orderBy('position')->get()
            ->filter(fn (Plan $plan): bool => $plan->supportsMode($tenant->platform_mode))
            ->values();

        return view('tenant.billing', [
            'tenant' => $tenant,
            'plan' => Plan::find($tenant->plan_key),
            'plans' => $plans,
            'features' => Feature::where('is_visible', true)->orderBy('position')->orderBy('key')->get(),
            'matrix' => $plans->mapWithKeys(fn (Plan $p): array => [$p->key => $p->features->pluck('value', 'feature_key')]),
            'subscription' => Subscription::where('tenant_id', $tenant->id)
                ->whereIn('status', ['trialing', 'active', 'past_due', 'paused'])
                ->latest()->first(),
            'invoices' => Invoice::where('tenant_id', $tenant->id)->latest('issued_at')->limit(24)->get(),
        ]);
    }
}
