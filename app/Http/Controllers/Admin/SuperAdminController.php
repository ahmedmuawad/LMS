<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Entitlements\Models\Plan;
use App\Core\Support\Money;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\View\View;

/**
 * اللوحة العليا — ما نراه نحن عن أعمالنا.
 */
final class SuperAdminController
{
    public function overview(): View
    {
        $byStatus = Tenant::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $paying = Tenant::query()->whereIn('status', ['active', 'past_due'])->get(['plan_key', 'currency']);

        // الإيراد الشهري المتكرر بعملة الأساس، بأسعار الباقات المثبّتة
        $plans = Plan::all()->keyBy('key');
        $mrr = Money::zero('EGP');

        foreach ($paying as $tenant) {
            $price = $plans->get($tenant->plan_key)?->priceIn('EGP');

            if ($price !== null) {
                $mrr = $mrr->plus($price);
            }
        }

        return view('super-admin.overview', [
            'byStatus' => $byStatus,
            'total' => Tenant::count(),
            'trialing' => (int) ($byStatus['trialing'] ?? 0),
            'active' => (int) ($byStatus['active'] ?? 0),
            'pastDue' => (int) ($byStatus['past_due'] ?? 0),
            'suspended' => (int) ($byStatus['suspended'] ?? 0),
            'mrr' => $mrr,
            'recent' => Tenant::with('domains')->latest()->limit(8)->get(),
            'byMode' => Tenant::query()->selectRaw('platform_mode, count(*) as total')
                ->groupBy('platform_mode')->pluck('total', 'platform_mode'),
            'byCountry' => Tenant::query()->selectRaw('country, count(*) as total')
                ->groupBy('country')->orderByDesc('total')->pluck('total', 'country'),
        ]);
    }
}
