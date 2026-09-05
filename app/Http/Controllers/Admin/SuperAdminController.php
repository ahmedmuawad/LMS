<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit\Models\AuditLog;
use App\Core\Entitlements\Models\Feature;
use App\Core\Entitlements\Models\Plan;
use App\Core\Support\HealthCheck;
use App\Core\Support\Money;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

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

    /**
     * الاستهلاك والحدود — من اقترب من سقفه، مرتّباً بالأقرب فالأقرب.
     * هذه شاشة مبيعات قبل أن تكون شاشة تشغيل: من بلغ حده مرشّح للترقية.
     */
    public function usage(Request $request): View
    {
        $featureKey = (string) $request->input('feature', '');

        $features = Feature::whereIn('type', ['limit', 'quota'])->orderBy('position')->get();
        $featureKey = $features->contains('key', $featureKey) ? $featureKey : (string) $features->first()?->key;

        $rows = collect();

        if ($featureKey !== '') {
            $rows = Tenant::whereIn('status', ['trialing', 'active', 'past_due'])
                ->get()
                ->map(fn (Tenant $tenant): array => [
                    'tenant' => $tenant,
                    'used' => $tenant->usageOf($featureKey),
                    'limit' => $tenant->limitOf($featureKey),
                    'percent' => $tenant->entitlements()->usagePercent($featureKey),
                ])
                ->sortByDesc(fn (array $row): float => $row['percent'] ?? -1)
                ->values();
        }

        return view('super-admin.usage', [
            'features' => $features,
            'feature' => $features->firstWhere('key', $featureKey),
            'featureKey' => $featureKey,
            'rows' => $rows,
            'atRisk' => $rows->filter(fn (array $r): bool => ($r['percent'] ?? 0) >= 80)->count(),
        ]);
    }

    /**
     * صحة النظام — فحوص تُجرى الآن، لا أرقام محفوظة من آخر مرة.
     * الأرقام المحفوظة تطمئن بينما يكون النظام واقعاً.
     */
    public function health(): View
    {
        return view('super-admin.health', [
            /*
             | فحوصٌ تقيس الأثر لا الإعداد.
             |
             | كان فحص الطوابير `config('queue.default') !== null` —
             | وهو يمرّ دائماً، ولو لم يكن ثمة عاملٌ مركَّب إطلاقاً.
             | وقد ظلّت المنصة أياماً بلا عامل وبلا كرون: إيميلات
             | التفعيل تُصفّ ولا تُرسَل، والفوترة لا تدور، وهذه
             | الصفحة تقول «كل الفحوص سليمة».
             |
             | فالفحص الآن يسأل: هل نُفِّذت المهام؟ متى دار المجدول؟
             | هل ثمة نسخة احتياطية؟ — لا: هل الإعداد مكتوب؟
             */
            'checks' => array_map(
                fn (array $c): array => [
                    'key' => $c['key'],
                    'label' => $c['label'],
                    'ok' => $c['ok'],
                    'detail' => $c['detail'],
                ],
                app(HealthCheck::class)->run(),
            ),
            'stuck' => Tenant::where('status', 'provisioning')
                ->where('created_at', '<', now()->subMinutes(15))->get(),
            'failedJobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            'versions' => [
                'PHP' => PHP_VERSION,
                'Laravel' => app()->version(),
                'قاعدة البيانات' => config('database.default'),
                'الكاش' => config('cache.default'),
                'الطوابير' => config('queue.default'),
                'البيئة' => app()->environment(),
            ],
        ]);
    }

    /** سجلّ تدخّلاتنا في حسابات العملاء. */
    public function audit(Request $request): View
    {
        return view('super-admin.audit', [
            'entries' => AuditLog::with('tenant')
                ->when($request->filled('action'), fn ($q) => $q->where('action', $request->input('action')))
                ->when($request->filled('tenant'), fn ($q) => $q->where('tenant_id', $request->input('tenant')))
                ->latest('created_at')
                ->paginate(30)
                ->withQueryString(),
            'actions' => AuditLog::ACTIONS,
        ]);
    }

    /** @return array{key:string,label:string,ok:bool,error:?string} */
    private function check(string $key, string $label, callable $probe): array
    {
        try {
            return ['key' => $key, 'label' => __($label), 'ok' => (bool) $probe(), 'error' => null];
        } catch (Throwable $e) {
            return ['key' => $key, 'label' => __($label), 'ok' => false, 'error' => $e->getMessage()];
        }
    }
}
