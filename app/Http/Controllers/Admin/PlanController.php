<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit\Audit;
use App\Core\Entitlements\Models\Feature;
use App\Core\Entitlements\Models\Plan;
use App\Core\Support\Money;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * الباقات والمزايا — مصفوفة واحدة تُقرأ كجدول مقارنة،
 * لأن هذا هو شكلها في صفحة التسعير أصلاً.
 */
final class PlanController
{
    public function index(): View
    {
        $plans = Plan::with('features')->orderBy('position')->get();

        return view('super-admin.plans', [
            'plans' => $plans,
            'features' => Feature::orderBy('type')->orderBy('position')->orderBy('key')->get(),
            'matrix' => $plans->mapWithKeys(fn (Plan $plan): array => [
                $plan->key => $plan->features->pluck('value', 'feature_key'),
            ]),
            'counts' => Tenant::query()->selectRaw('plan_key, count(*) as total')
                ->groupBy('plan_key')->pluck('total', 'plan_key'),
            'currencies' => $this->currencies($plans),
        ]);
    }

    /**
     * حفظ خلية واحدة من المصفوفة. القيمة الفارغة تحذف الصف —
     * وغياب الصف يعني «غير متاح»، فلا نخزّن أصفاراً بلا داعٍ.
     */
    public function updateFeature(Request $request, string $planKey): RedirectResponse
    {
        $plan = Plan::findOrFail($planKey);

        $input = $request->validate([
            'feature_key' => ['required', 'string', 'exists:features,key'],
            'value' => ['nullable', 'string', 'max:32'],
        ]);

        if (blank($input['value'])) {
            $plan->features()->where('feature_key', $input['feature_key'])->delete();
        } else {
            $plan->features()->updateOrCreate(
                ['feature_key' => $input['feature_key']],
                ['value' => $input['value']],
            );
        }

        $this->flushPlanEntitlements($plan);

        Audit::record('plan.updated', null, $plan, [
            'feature' => $input['feature_key'],
            'value' => $input['value'] ?? null,
        ]);

        return back()->with('status', __('تم حفظ المصفوفة.'));
    }

    /**
     * كل باقة تُغيَّر تُبطل صلاحيات كل مشتركيها — وإلا بقي المشترك
     * ساعة كاملة على الحدود القديمة، وهو ما لا يُفهم من لوحة الإدارة.
     */
    private function flushPlanEntitlements(Plan $plan): void
    {
        Tenant::where('plan_key', $plan->key)
            ->cursor()
            ->each(fn (Tenant $tenant) => Cache::forget("entitlements:{$tenant->id}:{$plan->key}"));
    }

    /** @return array<string, string> العملات التي لها سعر مثبّت في أي باقة */
    private function currencies($plans): array
    {
        return $plans
            ->flatMap(fn (Plan $plan): array => array_keys($plan->prices ?? []))
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $code): array => [$code => $code])
            ->all();
    }

    public static function price(Plan $plan, string $currency): string
    {
        return $plan->priceIn($currency)?->format() ?? '—';
    }

    public static function zero(string $currency): Money
    {
        return Money::zero($currency);
    }
}
