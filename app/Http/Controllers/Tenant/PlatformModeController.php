<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Core\Access\Ability;
use App\Core\Entitlements\Models\Plan;
use App\Core\Tenancy\Actions\ApplyPlatformMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * تغيير نمط المنصة بعد التسجيل.
 *
 * كان الاختيار يُتّخذ مرة واحدة في شاشة التسجيل ولا يُراجَع أبداً،
 * فمن اختار خطأً — أو تغيّر عمله — بقي في لوحة ناقصة بلا مخرج إلا
 * حساب جديد. والتعطيل إخفاء لا حذف (`ApplyPlatformMode`)، فالتبديل
 * لا يُفقد بيانات: من عاد إلى نمطه وجد مجموعاته كما تركها.
 */
final class PlatformModeController
{
    public function __construct(private readonly ApplyPlatformMode $applyMode) {}

    public function show(Request $request): View
    {
        $request->user()->can(Ability::SETTINGS_MANAGE) || abort(403);

        $tenant = tenant();
        $plan = Plan::find($tenant->plan_key);

        return view('admin.platform-mode', [
            'tenant' => $tenant,
            'current' => $tenant->platform_mode,
            'currentDelivery' => $tenant->delivery_mode,

            // ما لا تسمح به الباقة يُعرض مقفولاً لا مخفيّاً: صاحب الحساب
            // هو من يشتري الترقية، فإخفاء ما يريده عنه يمنعه من شرائه.
            'modes' => collect(config('platform-modes.modes'))
                ->map(fn (array $mode, string $key): array => [
                    'key' => $key,
                    'name' => $mode['name'][app()->getLocale()] ?? $mode['name']['ar'],
                    'summary' => $mode['summary'][app()->getLocale()] ?? $mode['summary']['ar'],
                    'icon' => $mode['icon'],
                    'allowed' => $plan === null || $plan->supportsMode($key),
                ])->values()->all(),

            'deliveries' => collect(config('platform-modes.delivery'))
                ->map(fn (array $d, string $key): array => [
                    'key' => $key,
                    'name' => $d['name'][app()->getLocale()] ?? $d['name']['ar'],
                ])->values()->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->user()->can(Ability::SETTINGS_MANAGE) || abort(403);

        $input = $request->validate([
            'mode' => ['required', 'string', 'in:'.implode(',', array_keys(config('platform-modes.modes')))],
            'delivery' => ['required', 'string', 'in:'.implode(',', array_keys(config('platform-modes.delivery')))],
        ]);

        $tenant = tenant();
        $plan = Plan::find($tenant->plan_key);

        if ($plan !== null && ! $plan->supportsMode($input['mode'])) {
            return back()->withErrors(['mode' => __('هذا النمط غير متاح في باقتك الحالية. رقِّ الباقة أولاً.')]);
        }

        $this->applyMode->handle($tenant, $input['mode'], $input['delivery']);

        return redirect()
            ->route('admin.platform-mode')
            ->with('status', __('تم تحديث النمط. راجع القائمة الجانبية — تغيّرت أقسامها.'));
    }
}
