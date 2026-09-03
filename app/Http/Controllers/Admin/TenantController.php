<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Admin\Resources\Central\TenantResource;
use App\Core\Audit\Audit;
use App\Core\Audit\Models\AuditLog;
use App\Core\Entitlements\Entitlements;
use App\Core\Entitlements\Models\Feature;
use App\Core\Entitlements\Models\Plan;
use App\Core\Tenancy\Actions\ChangeTenantPlan;
use App\Core\Tenancy\Actions\ChangeTenantStatus;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

/**
 * ملف المشترك الواحد — كل ما نحتاجه للدعم والفوترة في شاشة واحدة.
 */
final class TenantController
{
    public function show(string $id): View
    {
        $tenant = Tenant::with('domains')->findOrFail($id);

        return view('super-admin.tenant', [
            'tenant' => $tenant,
            'plan' => Plan::find($tenant->plan_key),
            'plans' => Plan::where('is_active', true)->get(),
            'features' => $this->features($tenant),
            'people' => $this->people($tenant),
            'health' => $this->tenantHealth($tenant),
            'log' => AuditLog::where('tenant_id', $tenant->id)->latest('created_at')->limit(20)->get(),
            'nextStatuses' => ChangeTenantStatus::allowedFrom(
                $tenant->status,
                TenantResource::STATUSES,
            ),
        ]);
    }

    public function updateStatus(Request $request, string $id, ChangeTenantStatus $action): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);

        $input = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(ChangeTenantStatus::TRANSITIONS))],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $action->handle($tenant, $input['status'], $input['reason'] ?? null);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return back()->with('status', __('تم تحديث حالة المشترك.'));
    }

    public function updatePlan(Request $request, string $id, ChangeTenantPlan $action): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);

        $input = $request->validate([
            'plan_key' => ['required', 'string', 'exists:plans,key'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $action->handle($tenant, $input['plan_key'], $input['reason'] ?? null);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['plan_key' => $e->getMessage()]);
        }

        return back()->with('status', __('تم تحديث باقة المشترك.'));
    }

    /**
     * تجاوز ميزة لمشترك بعينه — استثناء تجاري موثّق، لا تعديل باقة.
     * القيمة الفارغة تعني: ارجع إلى ما تقوله الباقة.
     */
    public function updateFeature(Request $request, string $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);

        $input = $request->validate([
            'feature_key' => ['required', 'string', 'exists:features,key'],
            'value' => ['nullable', 'string', 'max:32'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $table = DB::connection(config('tenancy.database.central_connection'))->table('tenant_features');
        $match = ['tenant_id' => $tenant->id, 'feature_key' => $input['feature_key']];

        if (blank($input['value'])) {
            $table->where($match)->delete();
        } else {
            $table->updateOrInsert($match, [
                'value' => $input['value'],
                'reason' => $input['reason'] ?? null,
                'expires_at' => $input['expires_at'] ?? null,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }

        $tenant->forgetEntitlements();

        Audit::record('tenant.feature_overridden', $tenant->id, $tenant, [
            'feature' => $input['feature_key'],
            'value' => $input['value'] ?? null,
            'expires_at' => $input['expires_at'] ?? null,
            'reason' => $input['reason'] ?? null,
        ]);

        return back()->with('status', __('تم تحديث مزايا المشترك.'));
    }

    /**
     * المزايا كما يراها المشترك فعلاً، مع بيان ما جاء من الباقة
     * وما هو استثناء خاص به — الفرق هو ما يهم فريق الدعم.
     *
     * @return list<array{key:string,label:string,type:string,effective:?string,fromPlan:?string,override:?string,used:int,percent:?float}>
     */
    private function features(Tenant $tenant): array
    {
        $conn = config('tenancy.database.central_connection');

        $fromPlan = $tenant->plan_key
            ? DB::connection($conn)->table('plan_features')
                ->where('plan_key', $tenant->plan_key)->pluck('value', 'feature_key')
            : collect();

        $overrides = DB::connection($conn)->table('tenant_features')
            ->where('tenant_id', $tenant->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('value', 'feature_key');

        $effective = $tenant->entitlements()->all();

        return Feature::orderBy('type')->orderBy('key')->get()
            ->map(fn (Feature $feature): array => [
                'key' => $feature->key,
                'label' => $feature->name[app()->getLocale()] ?? $feature->name['ar'] ?? $feature->key,
                'type' => $feature->type,
                'effective' => $effective[$feature->key] ?? null,
                'fromPlan' => $fromPlan[$feature->key] ?? null,
                'override' => $overrides[$feature->key] ?? null,
                'used' => $feature->type === 'boolean' ? 0 : $tenant->usageOf($feature->key),
                'percent' => $feature->type === 'boolean' ? null : $tenant->entitlements()->usagePercent($feature->key),
            ])
            ->all();
    }

    /**
     * من في منصّته — نقرأ من قاعدته هو، ولا نحتفظ بنسخة عندنا.
     *
     * @return array{staff:Collection, students:int, total:int}|null
     */
    private function people(Tenant $tenant): ?array
    {
        try {
            return $tenant->run(function (): array {
                $byRole = User::query()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

                return [
                    'staff' => User::whereIn('role', User::PANEL_ROLES)->orderByRaw("role = 'owner' desc")->limit(10)->get(),
                    'students' => (int) ($byRole['student'] ?? 0),
                    'total' => (int) $byRole->sum(),
                ];
            });
        } catch (Throwable) {
            // قاعدة المشترك غير جاهزة بعد أو تعذّر الوصول — نعرض الباقي ولا نُسقط الصفحة
            return null;
        }
    }

    /** @return array<string, bool|string> */
    private function tenantHealth(Tenant $tenant): array
    {
        return [
            'database' => $tenant->database()->manager()->databaseExists((string) $tenant->database()->getName()),
            'domain' => $tenant->domains->isNotEmpty(),
            'provisioned' => $tenant->provisioned_at !== null,
            'error' => $tenant->provision_error,
        ];
    }

    public static function valueLabel(?string $value): string
    {
        return match ($value) {
            null => __('غير متاح'),
            Entitlements::UNLIMITED => __('بلا حد'),
            '1', 'true' => __('متاح'),
            '0', 'false' => __('غير متاح'),
            default => $value,
        };
    }
}
