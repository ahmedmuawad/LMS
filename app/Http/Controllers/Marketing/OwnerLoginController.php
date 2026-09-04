<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Core\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * «ادخل إلى منصّتك» من موقعنا.
 *
 * لا حساب مركزي للمشترك ولا يجوز أن يكون: حسابه يعيش في قاعدته هو،
 * وحسابٌ ثانٍ عندنا يعني كلمتَي مرور تفترقان. فما نفعله هنا دلالةٌ
 * لا مصادقة — نجد نطاقه من بريده ونحوّله إليه ليدخل هناك.
 *
 * ولا نكشف من هو مشترك ومن ليس: بريد غير معروف يتلقّى الجواب نفسه
 * الذي يتلقّاه المعروف، فلا تُستعمل الشاشة لجرد عملائنا.
 */
final class OwnerLoginController extends Controller
{
    public function show(): View
    {
        return view('marketing.login');
    }

    public function find(Request $request): RedirectResponse|View
    {
        $input = $request->validate(
            ['email' => ['required', 'email', 'max:190']],
            [],
            ['email' => __('البريد')],
        );

        $tenants = Tenant::with('domains')
            ->where('owner_email', $input['email'])
            ->whereNotIn('status', ['archived'])
            ->get()
            ->filter(fn (Tenant $tenant): bool => $tenant->domains->isNotEmpty())
            ->values();

        if ($tenants->isEmpty()) {
            return view('marketing.login', [
                'notice' => __('إن كان لهذا البريد منصّة عندنا فستصلك رسالة بعنوانها. ولو لم تُنشئ منصّة بعد، ابدأ من صفحة الأسعار.'),
                'email' => $input['email'],
            ]);
        }

        if ($tenants->count() === 1) {
            return redirect()->away($this->loginUrl($request, $tenants->first()));
        }

        // أكثر من منصّة لنفس البريد: لا نختار عنه
        return view('marketing.login', [
            'choices' => $tenants->map(fn (Tenant $tenant): array => [
                'name' => (string) $tenant->name,
                'url' => $this->loginUrl($request, $tenant),
                'domain' => (string) ($tenant->domains->firstWhere('is_primary', true)?->domain
                    ?? $tenant->domains->first()?->domain),
            ])->all(),
            'email' => $input['email'],
        ]);
    }

    private function loginUrl(Request $request, Tenant $tenant): string
    {
        $domain = $tenant->domains->firstWhere('is_primary', true)?->domain
            ?? $tenant->domains->first()?->domain;

        $port = $request->getPort();
        $suffix = in_array($port, [null, 80, 443], true) ? '' : ':'.$port;

        return $request->getScheme().'://'.$domain.$suffix.'/login';
    }
}
