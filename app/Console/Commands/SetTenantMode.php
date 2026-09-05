<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenancy\Actions\ApplyPlatformMode;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Console\Command;

/**
 * تغيير نمط مشترك من الطرفية.
 *
 * الشاشة في اللوحة هي الطريق الطبيعي؛ وهذا الأمر للدعم حين يتعذّر
 * على المشترك الدخول أصلاً — وهي الحالة التي يكون فيها النمط الخاطئ
 * أشدّ ما يكون إعاقةً.
 */
final class SetTenantMode extends Command
{
    protected $signature = 'tenant:mode
        {slug : نطاق المشترك الفرعي}
        {mode : النمط (solo|teacher|marketplace|center|hybrid)}
        {--delivery= : نوع التقديم (recorded|live|blended) — يبقى كما هو إن أُهمل}';

    protected $description = 'يضبط نمط المنصة لمشترك ويطبّق موديولاته وإعداداته';

    public function handle(ApplyPlatformMode $applyMode): int
    {
        $tenant = Tenant::where('slug', $this->argument('slug'))->first();

        if ($tenant === null) {
            $this->error("لا يوجد مشترك بالنطاق [{$this->argument('slug')}].");

            return self::FAILURE;
        }

        $mode = (string) $this->argument('mode');

        if (config("platform-modes.modes.{$mode}") === null) {
            $this->error("نمط غير معروف: [{$mode}].");

            return self::FAILURE;
        }

        $before = $tenant->platform_mode;
        $modules = $applyMode->handle($tenant, $mode, $this->option('delivery') ?: null);

        $this->info("‏{$tenant->slug}: {$before} ← {$mode}");
        $this->line('  الموديولات المفعّلة: '.implode('، ', $modules));

        return self::SUCCESS;
    }
}
