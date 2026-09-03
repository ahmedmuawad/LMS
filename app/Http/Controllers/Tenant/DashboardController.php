<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Core\Entitlements\Models\Feature;
use App\Core\Entitlements\Models\Plan;
use App\Http\Controllers\Instructor\DashboardController as InstructorDashboard;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * لوحة المشترك — أول ما يراه بعد التهيئة.
 *
 * تعرض حالته هو: نمطه، باقته، استهلاكه مقابل حدوده، وفريقه.
 * لا تعرض شيئاً عن أعمالنا نحن.
 *
 * ومن كان نطاقه محصوراً — المدرّس — فله لوحته: الباقة والاستهلاك
 * قرارُ صاحب المنصّة لا قرارُه، وعرضها عليه ضجيج لا معلومة.
 */
final class DashboardController
{
    public function __invoke(): View
    {
        $me = auth()->user();

        if ($me instanceof User && $me->isScoped()) {
            return app(InstructorDashboard::class)();
        }

        $tenant = tenant();

        $byRole = User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return view('tenant.dashboard', [
            'tenant' => $tenant,
            'plan' => Plan::find($tenant->plan_key),
            'usage' => $this->usage(),
            'byRole' => $byRole,
            'staff' => $byRole->only(User::panelRoles())->sum(),
            'students' => (int) ($byRole['student'] ?? 0),
            'modules' => DB::table('modules')->where('enabled', true)->pluck('key'),
        ]);
    }

    /**
     * الحدود المرئية فقط، وبترتيب اقتراب كل حد من الامتلاء —
     * ما أوشك على النفاد أولاً، فهو ما يحتاج قراراً.
     *
     * @return list<array{key:string,label:string,used:int,limit:?int,percent:?float}>
     */
    private function usage(): array
    {
        $tenant = tenant();

        $rows = Feature::query()
            ->whereIn('type', ['limit', 'quota'])
            ->where('is_visible', true)
            ->get()
            ->map(fn (Feature $feature): array => [
                'key' => $feature->key,
                'label' => $feature->name[app()->getLocale()] ?? $feature->name['ar'] ?? $feature->key,
                'used' => $tenant->usageOf($feature->key),
                'limit' => $tenant->limitOf($feature->key),
                'percent' => $tenant->entitlements()->usagePercent($feature->key),
            ])
            // الحد صفر يعني ميزة خارج الباقة — مكانها صفحة الترقية لا لوحة الاستهلاك
            ->reject(fn (array $row): bool => $row['limit'] === 0)
            ->sortByDesc(fn (array $row): float => $row['percent'] ?? -1)
            ->values();

        return $rows->all();
    }
}
