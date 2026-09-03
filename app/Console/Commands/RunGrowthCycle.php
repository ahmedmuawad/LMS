<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenancy\Models\Tenant;
use App\Modules\Growth\Actions\DetectTriggers;
use App\Modules\Growth\Actions\RecordConversion;
use App\Modules\Growth\Actions\RunCampaigns;
use Illuminate\Console\Command;
use Throwable;

/**
 * دورة النمو لكل مشترك.
 *
 * تُشغَّل كل ربع ساعة: الرصد بالاستعلام لا بالحدث، والسلة تُترك
 * بالصمت لا بفعل، فلا شيء يوقظنا إن لم نسأل نحن.
 *
 * مشترك متعثّر لا يوقف الدورة: خطؤه يُسجَّل ويُمضى إلى التالي، وإلا
 * حرم عطلٌ في مشترك واحد كلَّ المشتركين من رسائلهم.
 */
final class RunGrowthCycle extends Command
{
    protected $signature = 'growth:run
        {--tenant= : مشترك بعينه بدل الجميع}
        {--limit=200 : أقصى تسجيل يُنفَّذ لكل مشترك}';

    protected $description = 'يرصد المُطلِقات ويُنفّذ خطوات الحملات وينضج عمولات المسوّقين';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q) => $q->where('slug', $this->option('tenant')))
            ->where('status', '!=', 'archived')
            ->get();

        $totals = ['entered' => 0, 'sent' => 0, 'matured' => 0, 'failed' => 0];

        foreach ($tenants as $tenant) {
            try {
                $totals = $this->runFor($tenant, $totals);
            } catch (Throwable $e) {
                $totals['failed']++;
                $this->error("[{$tenant->slug}] ".mb_substr($e->getMessage(), 0, 160));
                report($e);
            }
        }

        $this->info(__('دخل :entered · أُرسل :sent · نضج :matured · تعثّر :failed', $totals));

        return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $totals
     * @return array<string, int>
     */
    private function runFor(Tenant $tenant, array $totals): array
    {
        $limit = (int) $this->option('limit');

        $result = $tenant->run(function () use ($limit): array {
            $entered = array_sum(app(DetectTriggers::class)->handle());
            $tick = app(RunCampaigns::class)->tick($limit);
            $matured = app(RecordConversion::class)->matureAll();

            return ['entered' => $entered, 'sent' => $tick['sent'], 'matured' => $matured];
        });

        $totals['entered'] += $result['entered'];
        $totals['sent'] += $result['sent'];
        $totals['matured'] += $result['matured'];

        return $totals;
    }
}
