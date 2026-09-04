<?php

declare(strict_types=1);

namespace App\Core\Billing\Actions;

use App\Core\Billing\Models\Invoice;
use App\Core\Billing\Models\Subscription;
use App\Core\Entitlements\Models\Plan;
use App\Core\Tenancy\Actions\ProvisionTenant;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * من زائر على صفحة الأسعار إلى منصّة عاملة.
 *
 * التجهيز نفسه مبنيّ (`ProvisionTenant`)، وكان ينقصه بابٌ يدخل منه
 * الناس: أزرار صفحة الأسعار كانت تشير إلى مرساة `#start` لا وجود
 * لها. هذه هي تلك البوابة.
 *
 * الفاتورة تُصدَر دائماً — حتى مع التجربة المجانية: المشترك يرى ما
 * سيُستحقّ عليه ومتى، ونحن نرى التزامنا. وما يُدفع الآن يُغلقها،
 * وما يُؤجَّل يبقيها مفتوحة إلى نهاية التجربة.
 */
final class StartSignup
{
    public function __construct(
        private readonly ProvisionTenant $provision,
        private readonly IssueInvoice $invoices,
    ) {}

    /**
     * @param  array{academy:string, slug:string, name:string, email:string, phone:?string,
     *               password:string, plan:string, mode:string, delivery:string,
     *               country:string, currency:string, locale?:string}  $input
     * @return array{tenant:Tenant, invoice:Invoice|null}
     */
    public function handle(array $input): array
    {
        $plan = Plan::find($input['plan']);

        if ($plan === null || ! $plan->is_active || ! $plan->is_public) {
            throw ValidationException::withMessages(['plan' => __('هذه الباقة غير متاحة الآن.')]);
        }

        if (! $plan->supportsMode($input['mode'])) {
            throw ValidationException::withMessages([
                'mode' => __('هذا النمط غير متاح في باقة :plan.', ['plan' => $this->planName($plan)]),
            ]);
        }

        if ($plan->priceIn($input['currency']) === null) {
            throw ValidationException::withMessages([
                'currency' => __('باقة :plan غير مسعّرة بهذه العملة.', ['plan' => $this->planName($plan)]),
            ]);
        }

        $tenant = $this->provision->handle([
            'name' => $input['academy'],
            'slug' => $input['slug'],
            'owner_name' => $input['name'],
            'owner_email' => $input['email'],
            'owner_phone' => $input['phone'] ?? null,
            'plan_key' => $plan->key,
            'platform_mode' => $input['mode'],
            'delivery_mode' => $input['delivery'],
            'country' => $input['country'],
            'currency' => $input['currency'],
            'locale' => $input['locale'] ?? 'ar',
            'password' => $input['password'],
        ]);

        $subscription = Subscription::where('tenant_id', $tenant->id)->latest('id')->first();

        return [
            'tenant' => $tenant->refresh(),
            'invoice' => $subscription === null ? null : $this->invoices->handle($subscription),
        ];
    }

    /**
     * هل النطاق الفرعي متاح؟
     *
     * نفحص الجدولين معاً: مشترك محجوز باسمه، ونطاقٌ مسجَّل قد يكون
     * لمشترك حُذف سجلّه ولم يُحذف نطاقه.
     */
    /**
     * أسماء لا تُمنح لمشترك: بعضها مساراتنا نحن، وبعضها يُخلط بها.
     *
     * @var list<string>
     */
    public const RESERVED = [
        'www', 'admin', 'app', 'api', 'mail', 'smtp', 'ftp', 'cdn', 'static', 'assets',
        'super', 'billing', 'pay', 'checkout', 'status', 'help', 'support', 'docs',
        'blog', 'shop', 'store', 'account', 'accounts', 'login', 'register', 'start',
        'dashboard', 'panel', 'test', 'demo', 'staging', 'dev', 'localhost',
    ];

    public static function slugAvailable(string $slug): bool
    {
        if (in_array($slug, self::RESERVED, true)) {
            return false;
        }

        $domain = $slug.'.'.config('tenancy.base_domain', 'localhost');

        return ! Tenant::where('slug', $slug)->exists()
            && ! DB::table('domains')->where('domain', $domain)->exists();
    }

    /**
     * تذكرة دخول لصاحب المنصّة الجديدة — ليجد نفسه داخلها لا أمام
     * شاشة دخول يكتب فيها ما كتبه قبل ثوانٍ.
     */
    public function signInLink(Tenant $tenant, string $scheme = 'https', ?int $port = null): ?string
    {
        $owner = $tenant->run(fn () => User::where('role', 'owner')->first());

        if ($owner === null) {
            return null;
        }

        $domain = $tenant->domains->firstWhere('is_primary', true)?->domain
            ?? $tenant->domains->first()?->domain;

        if ($domain === null) {
            return null;
        }

        $token = tenancy()->impersonate($tenant, (string) $owner->getKey(), '/admin/dashboard', 'web');
        $suffix = in_array($port, [null, 80, 443], true) ? '' : ':'.$port;

        return $scheme.'://'.$domain.$suffix.'/impersonate/'.$token->token;
    }

    private function planName(Plan $plan): string
    {
        return $plan->name[app()->getLocale()] ?? $plan->name['ar'] ?? $plan->key;
    }
}
