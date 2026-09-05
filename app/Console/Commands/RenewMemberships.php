<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenancy\Models\Tenant;
use App\Modules\Lms\Models\Membership;
use Illuminate\Console\Command;
use Throwable;

/**
 * دورة عضويات الطلبة.
 *
 * تُنهي التجارب، وتُعلّم ما حان تجديده متأخّراً، وتُغلق ما انتهت
 * مدّته المدفوعة.
 *
 * ## ولا تُحصّل مالاً
 *
 * التحصيل التلقائي يحتاج بطاقةً محفوظة عند بوّابة دفع، وأكثر
 * المشتركين يحصّلون نقداً أو إنستاباي. فالدورة تُعلّم «متأخّرة»
 * ويُنبَّه الطالب ومدرّسه — والتحصيل يقع كما يقع بينهما.
 *
 * وهذا أصدق من وعدٍ بخصمٍ تلقائي لا يقع.
 */
final class RenewMemberships extends Command
{
    protected $signature = 'memberships:run {--tenant= : مشترك بعينه}';

    protected $description = 'ينهي التجارب ويعلّم المتأخّر ويُغلق المنتهي من عضويات الطلبة';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q) => $q->where('slug', $this->option('tenant')))
            ->whereIn('status', ['active', 'trialing'])
            ->get();

        $totals = ['due' => 0, 'closed' => 0];

        foreach ($tenants as $tenant) {
            try {
                $result = $this->runFor($tenant);
                $totals['due'] += $result['due'];
                $totals['closed'] += $result['closed'];
            } catch (Throwable $e) {
                $this->error("[{$tenant->slug}] ".mb_substr($e->getMessage(), 0, 160));
            }
        }

        $this->info("متأخّرة: {$totals['due']} · مغلقة: {$totals['closed']}");

        return self::SUCCESS;
    }

    /** @return array{due:int, closed:int} */
    private function runFor(Tenant $tenant): array
    {
        return $tenant->run(function (): array {
            if (! module_enabled('subscriptions')) {
                return ['due' => 0, 'closed' => 0];
            }

            /*
             | ما حان تجديده ولم يُدفع يصير «متأخّراً» لا «منتهياً».
             |
             | والفرق مهمّ: المتأخّر يبقى فاتحاً مدّة سماح، فمن تأخّر
             | يوماً لا يفقد وصوله وهو ينوي الدفع اليوم.
             */
            $due = Membership::whereIn('status', ['active', 'trialing'])
                ->whereNotNull('renews_at')
                ->where('renews_at', '<=', now())
                ->get();

            foreach ($due as $membership) {
                $grace = (int) setting('subscriptions.grace_days', 3);

                $membership->forceFill([
                    'status' => 'past_due',
                    'ends_at' => $membership->renews_at?->copy()->addDays($grace),
                ])->save();

                if ($membership->user !== null) {
                    notify('lms.membership_due', $membership->user, [
                        'plan' => (string) ($membership->plan?->name ?? ''),
                        'amount' => $membership->plan?->price()->format() ?? '',
                        'grace_days' => (string) $grace,
                    ]);
                }
            }

            // وما انقضت مهلته يُغلق
            $closed = Membership::whereIn('status', ['past_due', 'cancelled'])
                ->whereNotNull('ends_at')
                ->where('ends_at', '<', now())
                ->update(['status' => 'expired']);

            return ['due' => $due->count(), 'closed' => (int) $closed];
        });
    }
}
