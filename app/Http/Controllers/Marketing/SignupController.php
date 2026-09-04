<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Core\Billing\Actions\StartSignup;
use App\Core\Billing\Models\Invoice;
use App\Core\Billing\PlatformGateways;
use App\Core\Entitlements\Models\Plan;
use App\Core\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * التسجيل: من اختيار الباقة إلى منصّة عاملة.
 *
 * خطوة واحدة لا معالج بخمس خطوات: كل ما نحتاجه يسع شاشة واحدة،
 * ومعالجٌ طويل قبل أن يرى الزائر شيئاً يفقد نصف من بدأه. ما بقي
 * من التفاصيل يُسأل عنه داخل المنصّة في معالج التهيئة.
 */
final class SignupController extends Controller
{
    public function __construct(
        private readonly StartSignup $signup,
        private readonly PlatformGateways $gateways,
    ) {}

    public function show(Request $request): View
    {
        $plans = $this->plans();
        $selected = $request->string('plan')->value();

        return view('marketing.start', [
            'plans' => $plans,
            'selected' => $plans->contains('key', $selected) ? $selected : ($plans->first()?->key ?? ''),
            'modes' => config('platform-modes.modes', []),
            'deliveries' => config('platform-modes.delivery', []),
            'countries' => $this->countries(),
            'baseDomain' => (string) config('tenancy.base_domain', 'localhost'),
        ]);
    }

    /** فحص حيّ للنطاق أثناء الكتابة — لا بعد إرسال النموذج. */
    public function checkSlug(Request $request): JsonResponse
    {
        $slug = Str::slug((string) $request->query('slug', ''));

        return response()->json([
            'slug' => $slug,
            'available' => $slug !== '' && preg_match('/^[a-z0-9](?:[a-z0-9-]{1,30}[a-z0-9])?$/', $slug) === 1
                && StartSignup::slugAvailable($slug),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'academy' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'plan' => ['required', 'string', 'exists:plans,key'],
            'mode' => ['required', Rule::in(array_keys(config('platform-modes.modes', [])))],
            'delivery' => ['required', Rule::in(array_keys(config('platform-modes.delivery', [])))],
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],
            'terms' => ['accepted'],
        ], [], [
            'academy' => __('اسم الأكاديمية'), 'slug' => __('النطاق'), 'name' => __('اسمك'),
            'email' => __('البريد'), 'password' => __('كلمة المرور'), 'plan' => __('الباقة'),
            'mode' => __('النمط'), 'terms' => __('الشروط'),
        ]);

        if (! StartSignup::slugAvailable($input['slug'])) {
            return back()->withInput()->withErrors(['slug' => __('هذا النطاق محجوز — اختر غيره.')]);
        }

        ['tenant' => $tenant, 'invoice' => $invoice] = $this->signup->handle($input);

        /*
         | المفتاح في الجلسة لا في الرابط: من يملك الرابط لا يجوز أن
         | يدخل منصّة غيره. والجلسة تنتهي فيبقى الباب مقفلاً.
         */
        $request->session()->put('signup.tenant', $tenant->id);

        return redirect(url('/start/'.$tenant->slug.'/checkout'))
            ->with('status', __('جاهزة! بقي أن تختار كيف تدفع.'))
            ->with('signup.invoice', $invoice?->id);
    }

    /** شاشة الدفع: تجربة الآن أم سداد فوري. */
    public function checkout(Request $request, string $slug): View|RedirectResponse
    {
        $tenant = $this->ownTenant($request, $slug);

        if ($tenant === null) {
            return redirect(url('/start'))->withErrors(['slug' => __('انتهت جلسة التسجيل. ابدأ من جديد.')]);
        }

        $invoice = Invoice::where('tenant_id', $tenant->id)->latest('id')->first();

        return view('marketing.checkout', [
            'tenant' => $tenant,
            'plan' => Plan::find($tenant->plan_key),
            'invoice' => $invoice,
            'gateways' => $this->gateways->available(),
            'trialAllowed' => (bool) config('platform-billing.trial_without_card', true) && $tenant->onTrial(),
        ]);
    }

    /** «ابدأ التجربة الآن» — الفاتورة تبقى مفتوحة إلى نهايتها. */
    public function startTrial(Request $request, string $slug): RedirectResponse
    {
        $tenant = $this->ownTenant($request, $slug);

        if ($tenant === null || ! $tenant->onTrial()) {
            return redirect(url('/start'))->withErrors(['slug' => __('لا تجربة متاحة على هذا الحساب.')]);
        }

        return $this->enter($request, $tenant);
    }

    /**
     * دخول صاحب المنصّة الجديدة إليها.
     *
     * مفتاح الجلسة يُمحى بعد الاستعمال: التذكرة لمرة واحدة، ولا
     * يبقى في المتصفّح ما يفتح منصّة بعد انتهاء التسجيل.
     */
    private function enter(Request $request, Tenant $tenant): RedirectResponse
    {
        $link = $this->signup->signInLink($tenant, $request->getScheme(), $request->getPort());

        $request->session()->forget('signup.tenant');

        return $link === null
            ? redirect(url('/'))->withErrors(['slug' => __('جُهّزت منصّتك، لكن تعذّر الدخول التلقائي. سجّل الدخول من نطاقك.')])
            : redirect()->away($link);
    }

    /** المشترك الذي يملك هذه الجلسة — لا أيّ مشترك في الرابط. */
    private function ownTenant(Request $request, string $slug): ?Tenant
    {
        $id = $request->session()->get('signup.tenant');

        if ($id === null) {
            return null;
        }

        return Tenant::with('domains')->where('id', $id)->where('slug', $slug)->first();
    }

    /** @return Collection<int, Plan> */
    private function plans()
    {
        return Plan::query()
            ->where('is_public', true)->where('is_active', true)
            ->orderBy('position')->get();
    }

    /** @return array<string, string> */
    private function countries(): array
    {
        return DB::connection(config('tenancy.database.central_connection'))
            ->table('countries')->where('is_active', true)->orderBy('position_order')->orderBy('code')
            ->get(['code', 'name', 'currency'])
            ->mapWithKeys(function (object $row): array {
                $name = json_decode((string) $row->name, true) ?: [];

                return [$row->code => ($name[app()->getLocale()] ?? $name['ar'] ?? $row->code).'|'.$row->currency];
            })
            ->all();
    }
}
