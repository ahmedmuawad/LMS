<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Core\Billing\Actions\RecordPayment;
use App\Core\Billing\Actions\StartSignup;
use App\Core\Billing\Models\Invoice;
use App\Core\Billing\PlatformGateways;
use App\Core\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * دفع فاتورة الاشتراك — لا فاتورة المشترك لطلابه.
 *
 * التأكّد من الدفع يأتي من البوابة نفسها لا من رابط العودة: من يعرف
 * رابط النجاح يستطيع فتحه بيده، فلا يُفعَّل اشتراك على زيارة رابط.
 */
final class PlatformCheckoutController extends Controller
{
    public function __construct(
        private readonly PlatformGateways $gateways,
        private readonly RecordPayment $payments,
        private readonly StartSignup $signup,
    ) {}

    public function pay(Request $request, string $slug): RedirectResponse|View
    {
        $tenant = $this->ownTenant($request, $slug);

        if ($tenant === null) {
            return redirect(url('/start'))->withErrors(['slug' => __('انتهت جلسة التسجيل. ابدأ من جديد.')]);
        }

        $available = collect($this->gateways->available())->map(fn ($g): string => $g->key())->all();

        $input = $request->validate(
            ['gateway' => ['required', 'string', Rule::in($available)]],
            [],
            ['gateway' => __('طريقة الدفع')],
        );

        $invoice = Invoice::where('tenant_id', $tenant->id)->latest('id')->firstOrFail();

        if ($invoice->status === 'paid') {
            return $this->enter($request, $tenant);
        }

        $gateway = $this->gateways->resolve($input['gateway']);

        try {
            $intent = $gateway->start($invoice, url('/start/'.$tenant->slug.'/return/'.$gateway->key()));
        } catch (RuntimeException $e) {
            return back()->withErrors(['gateway' => $e->getMessage()]);
        }

        return match ($intent->mode) {
            'redirect' => redirect()->away((string) $intent->url),
            // تعليمات تحويل: المنصّة تعمل من الآن والفاتورة تنتظر الاعتماد
            'instructions' => view('marketing.instructions', [
                'tenant' => $tenant,
                'invoice' => $invoice,
                'gateway' => $gateway,
                'intent' => $intent,
                'enterUrl' => url('/start/'.$tenant->slug.'/enter'),
            ]),
            default => $this->settle($request, $tenant, $invoice, $gateway->key(), $intent->reference),
        };
    }

    /** عودة المستخدم من البوابة — نسأل البوابة ولا نصدّق الرابط. */
    public function return(Request $request, string $slug, string $gatewayKey): RedirectResponse
    {
        $tenant = $this->ownTenant($request, $slug);

        if ($tenant === null || ! $this->gateways->has($gatewayKey)) {
            return redirect(url('/start'))->withErrors(['slug' => __('انتهت جلسة التسجيل. ابدأ من جديد.')]);
        }

        if ($request->boolean('cancelled')) {
            return redirect(url('/start/'.$tenant->slug.'/checkout'))
                ->withErrors(['gateway' => __('أُلغي الدفع. منصّتك جاهزة ويمكنك بدء التجربة أو المحاولة ثانية.')]);
        }

        $result = $this->gateways->resolve($gatewayKey)->handleCallback($request);

        if ($result === null || ! $result->paid) {
            return redirect(url('/start/'.$tenant->slug.'/checkout'))
                ->withErrors(['gateway' => $result?->message ?? __('تعذّر تأكيد الدفع.')]);
        }

        $invoice = Invoice::where('tenant_id', $tenant->id)->where('id', $result->invoiceId)->first()
            ?? Invoice::where('tenant_id', $tenant->id)->latest('id')->firstOrFail();

        // دفعة وصلت مرّتين (عودة + webhook) لا تُسجَّل مرّتين
        if ($invoice->status !== 'paid') {
            $this->payments->handle($invoice, $result->amount, $gatewayKey, $result->reference);
        }

        return $this->enter($request, $tenant);
    }

    /** دخول بلا دفع — للتحويل البنكي وللتجربة. */
    public function enter(Request $request, Tenant|string $tenant): RedirectResponse
    {
        if (is_string($tenant)) {
            $resolved = $this->ownTenant($request, $tenant);

            if ($resolved === null) {
                return redirect(url('/start'))->withErrors(['slug' => __('انتهت جلسة التسجيل. ابدأ من جديد.')]);
            }

            $tenant = $resolved;
        }

        $link = $this->signup->signInLink($tenant, $request->getScheme(), $request->getPort());

        $request->session()->forget('signup.tenant');

        return $link === null
            ? redirect(url('/'))->withErrors(['slug' => __('جُهّزت منصّتك، لكن تعذّر الدخول التلقائي. سجّل الدخول من نطاقك.')])
            : redirect()->away($link);
    }

    private function settle(Request $request, Tenant $tenant, Invoice $invoice, string $gateway, ?string $reference): RedirectResponse
    {
        $this->payments->handle($invoice, $invoice->outstanding(), $gateway, $reference);

        return $this->enter($request, $tenant);
    }

    private function ownTenant(Request $request, string $slug): ?Tenant
    {
        $id = $request->session()->get('signup.tenant');

        return $id === null
            ? null
            : Tenant::with('domains')->where('id', $id)->where('slug', $slug)->first();
    }
}
