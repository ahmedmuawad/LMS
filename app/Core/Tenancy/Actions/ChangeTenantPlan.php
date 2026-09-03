<?php

declare(strict_types=1);

namespace App\Core\Tenancy\Actions;

use App\Core\Audit\Audit;
use App\Core\Entitlements\Models\Plan;
use App\Core\Tenancy\Models\Tenant;
use InvalidArgumentException;

/**
 * تغيير الباقة.
 *
 * التنزيل لا يحذف شيئاً: ما تجاوز الحد الجديد يبقى يعمل، ويُمنع
 * الجديد فقط. لا نعاقب طالباً دفع بخلاف بين مشتركه وبيننا.
 */
final class ChangeTenantPlan
{
    public function handle(Tenant $tenant, string $planKey, ?string $reason = null): Tenant
    {
        $plan = Plan::find($planKey);

        if ($plan === null || ! $plan->is_active) {
            throw new InvalidArgumentException("الباقة [{$planKey}] غير متاحة.");
        }

        if (! $plan->supportsMode($tenant->platform_mode)) {
            throw new InvalidArgumentException("الباقة [{$planKey}] لا تدعم نمط [{$tenant->platform_mode}].");
        }

        $from = $tenant->plan_key;

        if ($from === $planKey) {
            return $tenant;
        }

        // نُفرغ الصلاحيات المحفوظة قبل التبديل وبعده: المفتاح يحمل اسم الباقة
        $tenant->forgetEntitlements();
        $tenant->plan_key = $planKey;
        $tenant->save();
        $tenant->forgetEntitlements();

        Audit::record('tenant.plan_changed', $tenant->id, $tenant, [
            'from' => $from,
            'to' => $planKey,
            'reason' => $reason,
        ]);

        return $tenant;
    }
}
