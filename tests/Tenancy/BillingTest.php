<?php

declare(strict_types=1);

use App\Core\Billing\Actions\IssueInvoice;
use App\Core\Billing\Actions\RecordPayment;
use App\Core\Billing\Actions\StartSubscription;
use App\Core\Billing\InvoiceNumber;
use App\Core\Billing\Models\Invoice;
use App\Core\Billing\Models\Subscription;
use App\Core\Entitlements\Models\Plan;
use App\Core\Support\Money;
use App\Core\Tenancy\Models\Tenant;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

it('opens a subscription when a tenant is provisioned', function () {
    $tenant = provision(['plan_key' => 'growth']);

    $subscription = Subscription::where('tenant_id', $tenant->id)->firstOrFail();

    expect($subscription->plan_key)->toBe('growth')
        ->and($subscription->status)->toBe('trialing')
        ->and($subscription->currency)->toBe($tenant->currency)
        ->and($subscription->isLive())->toBeTrue();
});

it('freezes the price at signup so a later rise never reaches an existing subscriber', function () {
    $tenant = provision(['plan_key' => 'growth']);
    $before = Subscription::where('tenant_id', $tenant->id)->firstOrFail()->amount_minor;

    $plan = Plan::find('growth');
    $plan->prices = [...$plan->prices, 'EGP' => $plan->prices['EGP'] * 3];
    $plan->save();

    expect(Subscription::where('tenant_id', $tenant->id)->firstOrFail()->amount_minor)->toBe($before);
});

it('never leaves two live subscriptions on one tenant', function () {
    actingAsSuperAdmin();
    $tenant = provision(['plan_key' => 'starter']);

    app(StartSubscription::class)->handle($tenant, 'professional');

    $live = Subscription::where('tenant_id', $tenant->id)
        ->whereIn('status', ['trialing', 'active', 'past_due', 'paused'])->get();

    expect($live)->toHaveCount(1)
        ->and($live->first()->plan_key)->toBe('professional');
});

it('refuses a plan that has no price in the tenant currency', function () {
    $tenant = makeTenant(attributes: ['currency' => 'JPY']);

    expect(fn () => app(StartSubscription::class)->handle($tenant, 'growth'))
        ->toThrow(InvalidArgumentException::class);
});

it('issues an invoice that freezes its own lines', function () {
    $tenant = provision(['plan_key' => 'growth']);
    $subscription = Subscription::where('tenant_id', $tenant->id)->firstOrFail();

    $invoice = app(IssueInvoice::class)->handle($subscription, taxRate: 14);

    expect($invoice->subtotal_minor)->toBe($subscription->amount_minor)
        ->and($invoice->tax_minor)->toBe((int) round($subscription->amount_minor * 0.14))
        ->and($invoice->total_minor)->toBe($invoice->subtotal_minor + $invoice->tax_minor)
        ->and($invoice->lines)->toHaveCount(1)
        ->and($invoice->billing_details['email'])->toBe($tenant->owner_email);
});

it('numbers invoices in an unbroken sequence', function () {
    $tenant = provision(['plan_key' => 'growth']);
    $subscription = Subscription::where('tenant_id', $tenant->id)->firstOrFail();

    $numbers = collect(range(1, 3))
        ->map(fn (): string => app(IssueInvoice::class)->handle($subscription)->number);

    $year = now()->format('Y');

    expect($numbers->all())->toBe([
        "INV-{$year}-00001", "INV-{$year}-00002", "INV-{$year}-00003",
    ]);
});

it('starts a new sequence each year without colliding', function () {
    expect(InvoiceNumber::next('INV', 2030))->toBe('INV-2030-00001');
});

it('keeps an invoice open on a partial payment', function () {
    actingAsSuperAdmin();
    $invoice = issueInvoiceFor(provision(['plan_key' => 'growth']));

    app(RecordPayment::class)->handle(
        $invoice,
        Money::fromMinor((int) floor($invoice->total_minor / 2), $invoice->currency),
        'bank_transfer',
    );

    $invoice->refresh();

    expect($invoice->status)->toBe('open')
        ->and($invoice->outstanding()->minor)->toBeGreaterThan(0);
});

it('closes the invoice when the full amount lands', function () {
    actingAsSuperAdmin();
    $invoice = issueInvoiceFor(provision(['plan_key' => 'growth']));

    app(RecordPayment::class)->handle($invoice, $invoice->total(), 'bank_transfer', 'TRX-1');

    $invoice->refresh();

    expect($invoice->status)->toBe('paid')
        ->and($invoice->paid_at)->not->toBeNull()
        ->and($invoice->outstanding()->isZero())->toBeTrue();
});

it('puts a suspended tenant back to work the moment they pay', function () {
    actingAsSuperAdmin();
    $tenant = provision(['plan_key' => 'growth']);
    $invoice = issueInvoiceFor($tenant);

    Subscription::where('tenant_id', $tenant->id)->update(['status' => 'past_due']);
    Tenant::withoutEvents(fn () => $tenant->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save());

    app(RecordPayment::class)->handle($invoice, $invoice->total(), 'bank_transfer');

    expect(Tenant::find($tenant->id)->status)->toBe('active')
        ->and(Tenant::find($tenant->id)->suspended_at)->toBeNull()
        ->and(Subscription::where('tenant_id', $tenant->id)->firstOrFail()->status)->toBe('active');
});

it('counts a yearly subscription as one twelfth per month', function () {
    $subscription = new Subscription([
        'currency' => 'EGP', 'amount_minor' => 120000, 'interval' => 'year', 'interval_count' => 1,
    ]);

    expect($subscription->monthlyEquivalent()->minor)->toBe(10000);
});

it('shows the tenant their subscription, plans and invoices', function () {
    actingAsSuperAdmin();
    $tenant = provision(['plan_key' => 'growth']);
    issueInvoiceFor($tenant);
    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/billing')
        ->assertOk()
        ->assertSee('الاشتراك والفواتير')
        ->assertSee('INV-'.now()->format('Y'));
});

it('lists subscriptions and invoices in the platform panel', function () {
    actingAsSuperAdmin();
    $tenant = provision(['plan_key' => 'growth']);
    $invoice = issueInvoiceFor($tenant);

    $this->get('/admin/subscriptions')->assertOk()->assertSee($tenant->name);
    $this->get('/admin/invoices')->assertOk()->assertSee($invoice->number);
    $this->get('/admin/invoices/'.$invoice->id)->assertOk()->assertSee($invoice->number);
});

it('records a manual payment from the invoice screen', function () {
    actingAsSuperAdmin();
    $invoice = issueInvoiceFor(provision(['plan_key' => 'growth']));

    $this->post('/admin/invoices/'.$invoice->id.'/pay', [
        'amount' => $invoice->total()->toDecimal(),
        'gateway' => 'bank_transfer',
        'reference' => 'TRX-99',
    ])->assertRedirect();

    expect(Invoice::find($invoice->id)->status)->toBe('paid');
});

it('refuses to void an invoice that was already paid', function () {
    actingAsSuperAdmin();
    $invoice = issueInvoiceFor(provision(['plan_key' => 'growth']));

    app(RecordPayment::class)->handle($invoice, $invoice->total(), 'cash');

    $this->put('/admin/invoices/'.$invoice->id.'/void')->assertStatus(409);

    expect(Invoice::find($invoice->id)->status)->toBe('paid');
});

function issueInvoiceFor(Tenant $tenant): Invoice
{
    return app(IssueInvoice::class)->handle(
        Subscription::where('tenant_id', $tenant->id)->firstOrFail(),
    );
}
