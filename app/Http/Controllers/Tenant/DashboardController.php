<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Core\Entitlements\Models\Feature;
use App\Core\Entitlements\Models\Plan;
use App\Core\Entitlements\Quota;
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
        /*
         | الأرقام من `Quota` لا من عدّاد الاستهلاك.
         |
         | كانت تُقرأ من `usage_records`، وهو جدول لا يكتب فيه شيء في
         | التطبيق — فكانت البطاقة تعرض صفراً لكل حدّ مهما بلغ استهلاك
         | المشترك، وتطمئنه وهو على حافّة التوقّف.
         */
        $labels = Feature::query()
            ->whereIn('type', ['limit', 'quota'])
            ->pluck('name', 'key')
            ->map(fn ($name): ?array => is_array($name) ? $name : json_decode((string) $name, true))
            ->all();

        return collect(app(Quota::class)->overview())
            ->map(fn (array $row): array => [
                ...$row,
                'label' => $labels[$row['key']][app()->getLocale()]
                    ?? $labels[$row['key']]['ar']
                    ?? $row['key'],
            ])
            ->sortByDesc(fn (array $row): float => $row['percent'] ?? -1)
            ->values()
            ->all();
    }
}
