<?php

declare(strict_types=1);

namespace App\Core\Billing\Actions;

use App\Core\Audit\Audit;
use App\Core\Billing\Models\Subscription;
use App\Core\Entitlements\Models\Plan;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * بدء اشتراك المشترك عندنا.
 *
 * السعر يُجمَّد وقت الاشتراك: رفع سعر الباقة لاحقاً لا يمسّ من
 * اشترك قبله. هذا وعدٌ نقطعه في صفحة التسعير ونحفظه هنا في العمود.
 */
final class StartSubscription
{
    public function handle(Tenant $tenant, string $planKey, ?string $currency = null, bool $withTrial = true): Subscription
    {
        $plan = Plan::find($planKey);

        if ($plan === null || ! $plan->is_active) {
            throw new InvalidArgumentException("الباقة [{$planKey}] غير متاحة.");
        }

        $currency ??= $tenant->currency ?? 'EGP';
        $price = $plan->priceIn($currency)
            ?? throw new InvalidArgumentException("الباقة [{$planKey}] بلا سعر بعملة [{$currency}].");

        // اشتراك قائم يُلغى قبل الجديد — لا يجتمع اشتراكان حيّان لمشترك واحد
        Subscription::where('tenant_id', $tenant->id)
            ->whereIn('status', ['trialing', 'active', 'past_due', 'paused'])
            ->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => 'استُبدل باشتراك جديد']);

        $trialDays = $withTrial ? (int) $plan->trial_days : 0;
        $start = now();

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_key' => $plan->key,
            'status' => $trialDays > 0 ? 'trialing' : 'active',
            'currency' => $currency,
            'amount_minor' => $price->minor,
            'interval' => $plan->interval,
            'interval_count' => $plan->interval_count,
            'trial_ends_at' => $trialDays > 0 ? $start->copy()->addDays($trialDays) : null,
            'current_period_start' => $start,
            'current_period_end' => $this->periodEnd($start->copy(), $plan->interval, (int) $plan->interval_count, $trialDays),
            'auto_renew' => true,
        ]);

        $tenant->forceFill([
            'plan_key' => $plan->key,
            'currency' => $currency,
            'status' => $subscription->status === 'trialing' ? 'trialing' : 'active',
            'trial_ends_at' => $subscription->trial_ends_at,
        ])->save();

        $tenant->forgetEntitlements();

        Audit::record('subscription.started', $tenant->id, $subscription, [
            'plan' => $plan->key,
            'currency' => $currency,
            'trial_days' => $trialDays,
        ]);

        return $subscription;
    }

    private function periodEnd(Carbon $start, string $interval, int $count, int $trialDays): Carbon
    {
        $end = $interval === 'year'
            ? $start->copy()->addYears(max(1, $count))
            : $start->copy()->addMonths(max(1, $count));

        return $trialDays > 0 ? $end->addDays($trialDays) : $end;
    }
}
