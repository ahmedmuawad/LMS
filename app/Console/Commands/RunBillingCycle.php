<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Billing\Actions\IssueInvoice;
use App\Core\Billing\Models\Invoice;
use App\Core\Billing\Models\Subscription;
use App\Core\Tenancy\Actions\ChangeTenantStatus;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * دورة الفوترة — ما يجعل الاشتراك اشتراكاً لا هبة.
 *
 * آلة حالات المشترك كانت مبنيّة ولا شيء يُحرّكها بالوقت: تنتهي
 * التجربة ويبقى المشترك يعمل مجاناً إلى الأبد، وتستحقّ الفاتورة
 * ولا يُطالَب بها أحد. هذا الأمر هو عقرب الساعة.
 *
 * أربع خطوات بترتيب مقصود:
 *   ١) تجربة انتهت ولها فاتورة مدفوعة  → تعمل
 *   ٢) تجربة انتهت بلا سداد           → متعثّرة (تعمل في فترة السماح)
 *   ٣) دورة انتهت                     → فاتورة جديدة
 *   ٤) فاتورة تجاوزت السماح           → تعليق
 *
 * التعليق آخرها دائماً: من دفع اليوم يجب أن يُستثنى قبل أن نصل إليه.
 */
final class RunBillingCycle extends Command
{
    protected $signature = 'billing:run {--dry : اعرض ما سيحدث بلا تنفيذ}';

    protected $description = 'ينهي التجارب ويصدر فواتير التجديد ويعلّق المتأخّرين';

    public function handle(IssueInvoice $invoices, ChangeTenantStatus $status): int
    {
        $dry = (bool) $this->option('dry');
        $done = ['ended' => 0, 'due' => 0, 'invoiced' => 0, 'suspended' => 0];

        $done['ended'] = $this->endTrials($status, $dry, true);
        $done['due'] = $this->endTrials($status, $dry, false);
        $done['invoiced'] = $this->renew($invoices, $dry);
        $done['suspended'] = $this->suspendOverdue($status, $dry);

        $this->info(($dry ? '[تجربة] ' : '').sprintf(
            'انتقل إلى الخدمة: %d · صار متعثّراً: %d · فاتورة جديدة: %d · عُلِّق: %d',
            $done['ended'], $done['due'], $done['invoiced'], $done['suspended'],
        ));

        return self::SUCCESS;
    }

    /**
     * تجربة انتهت: من سدّد يعمل، ومن لم يسدّد يصير متعثّراً.
     *
     * لا نُعلّق فوراً: من نسي السداد يوماً لا يُقفل عليه بابه ويُقفل
     * على طلابه معه. فترة السماح هي الفرق بين تحصيلٍ وطردٍ.
     */
    private function endTrials(ChangeTenantStatus $status, bool $dry, bool $paid): int
    {
        $count = 0;

        $tenants = Tenant::where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->get();

        foreach ($tenants as $tenant) {
            $settled = ! Invoice::where('tenant_id', $tenant->id)
                ->whereIn('status', ['open', 'overdue'])
                ->exists();

            if ($settled !== $paid) {
                continue;
            }

            $count++;

            if ($dry) {
                $this->line(sprintf('  %s → %s', $tenant->slug, $paid ? 'active' : 'past_due'));

                continue;
            }

            $this->transition($status, $tenant, $paid ? 'active' : 'past_due', $paid
                ? __('انتهت التجربة والفاتورة مسدّدة.')
                : __('انتهت التجربة بلا سداد.'));
        }

        return $count;
    }

    /** دورة انتهت: فاتورة الدورة التالية تُصدَر قبل استحقاقها لا بعده. */
    private function renew(IssueInvoice $invoices, bool $dry): int
    {
        $count = 0;

        $subscriptions = Subscription::query()
            ->whereIn('status', ['active', 'past_due'])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now()->addDays(3))
            ->with('tenant')
            ->get();

        foreach ($subscriptions as $subscription) {
            // فاتورة الدورة صدرت سلفاً: تشغيلٌ ثانٍ في اليوم لا يُصدرها مرتين
            $exists = Invoice::where('subscription_id', $subscription->id)
                ->where('created_at', '>=', now()->subDays(7))
                ->exists();

            if ($exists) {
                continue;
            }

            $count++;

            if ($dry) {
                $this->line('  فاتورة جديدة لـ'.($subscription->tenant?->slug ?? $subscription->tenant_id));

                continue;
            }

            try {
                $invoices->handle($subscription);
                $this->advancePeriod($subscription);
            } catch (Throwable $e) {
                Log::error('تعذّر إصدار فاتورة تجديد', [
                    'subscription' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
                $count--;
            }
        }

        return $count;
    }

    /** فاتورة تجاوزت السماح: تعليق حتى السداد — والبيانات تبقى. */
    private function suspendOverdue(ChangeTenantStatus $status, bool $dry): int
    {
        $grace = (int) config('platform-billing.grace_days', 7);
        $count = 0;

        $overdue = Invoice::query()
            ->whereIn('status', ['open', 'overdue'])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->subDays($grace))
            ->with('tenant')
            ->get();

        foreach ($overdue as $invoice) {
            $tenant = $invoice->tenant;

            if ($tenant === null || ! in_array($tenant->status, ['past_due', 'active'], true)) {
                continue;
            }

            $count++;

            if ($dry) {
                $this->line('  تعليق '.$tenant->slug.' (فاتورة '.$invoice->number.')');

                continue;
            }

            if ($invoice->status === 'open') {
                $invoice->forceFill(['status' => 'overdue'])->save();
            }

            $this->transition($status, $tenant, 'suspended', __('فاتورة :number متأخّرة أكثر من :days يوماً.', [
                'number' => $invoice->number,
                'days' => $grace,
            ]));
        }

        return $count;
    }

    /** فشل انتقال واحد لا يوقف الدورة على من بعده. */
    private function transition(ChangeTenantStatus $status, Tenant $tenant, string $to, string $reason): void
    {
        try {
            $status->handle($tenant, $to, $reason);
        } catch (Throwable $e) {
            Log::warning('تعذّر تغيير حالة مشترك في دورة الفوترة', [
                'tenant' => $tenant->id,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function advancePeriod(Subscription $subscription): void
    {
        $end = $subscription->current_period_end instanceof Carbon
            ? $subscription->current_period_end->copy()
            : now();

        $next = $subscription->interval === 'year'
            ? $end->addYears(max(1, (int) $subscription->interval_count))
            : $end->addMonths(max(1, (int) $subscription->interval_count));

        $subscription->forceFill([
            'current_period_start' => $end,
            'current_period_end' => $next,
        ])->save();
    }
}
