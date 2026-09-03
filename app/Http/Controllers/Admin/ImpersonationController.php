<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit\Audit;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * «الدخول كمشترك» — أداة دعم، لا باب خلفي.
 *
 * كل دخول يُقيَّد باسم من دخل ولأي حساب ومتى، والتذكرة تُستهلك
 * مرة واحدة وتنتهي خلال دقيقة. المشترك يرى شريطاً يخبره أن أحداً
 * من فريق المنصة داخل حسابه الآن.
 */
final class ImpersonationController
{
    public function start(Request $request, string $tenantId): RedirectResponse
    {
        $tenant = Tenant::with('domains')->findOrFail($tenantId);

        abort_unless($tenant->canAccessDashboard(), 409, __('لوحة هذا المشترك مقفلة الآن.'));

        $userId = $request->input('user');

        // الافتراضي: مالك المنصة — من يملك رؤية كل شيء فيها
        $user = $tenant->run(fn () => $userId !== null
            ? User::find($userId)
            : User::where('role', 'owner')->first());

        abort_if($user === null, 404, __('لا يوجد حساب صالح للدخول به.'));

        abort_unless($user->canAccessPanel(), 403, __('هذا الحساب لا يملك صلاحية دخول اللوحة.'));

        $domain = $tenant->domains->firstWhere('is_primary', true)?->domain
            ?? $tenant->domains->first()?->domain;

        abort_if($domain === null, 409, __('هذا المشترك بلا نطاق بعد.'));

        $token = tenancy()->impersonate($tenant, (string) $user->getKey(), '/admin/dashboard', 'web');

        Audit::record('tenant.impersonated', $tenant->id, $tenant, [
            'user_id' => $user->getKey(),
            'user_email' => $user->email,
            'user_role' => $user->role,
        ]);

        return redirect()->away(
            $request->getScheme().'://'.$domain.$this->port($request).'/impersonate/'.$token->token
        );
    }

    private function port(Request $request): string
    {
        $port = $request->getPort();

        return in_array($port, [80, 443, null], true) ? '' : ':'.$port;
    }
}
