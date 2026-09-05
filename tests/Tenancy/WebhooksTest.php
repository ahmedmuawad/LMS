<?php

declare(strict_types=1);

use App\Core\Settings\SettingsRepository;
use App\Models\User;
use App\Modules\Webhooks\Jobs\DeliverWebhook;
use App\Modules\Webhooks\Models\WebhookDelivery;
use App\Modules\Webhooks\Models\WebhookEndpoint;
use App\Modules\Webhooks\Webhooks;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

/*
| الـWebhooks الصادرة.
|
| والاختبار هنا لأن الإخفاق صامت بطبيعته: حدثٌ لم يُطلق لا يظهر في
| شاشة ولا في لوج — يظهر عند المشترك بعد شهرٍ حين يسأل عن تكاملٍ
| لم يعمل يوماً.
*/

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);

    tenancy()->initialize(provision());

    app(SettingsRepository::class)->set('integrations.webhooks_enabled', true);
    app(SettingsRepository::class)->flush();
});

function endpointFor(array $events, array $overrides = []): WebhookEndpoint
{
    return WebhookEndpoint::create([
        'name' => 'نظام المحاسبة',
        'url' => 'https://example.test/hooks',
        'events' => $events,
        'is_active' => true,
        ...$overrides,
    ]);
}

it('generates a secret so nobody is asked to invent one', function () {
    $endpoint = endpointFor(['lms.enrolled']);

    expect($endpoint->secret)->toStartWith('whsec_')->toHaveLength(54);
});

it('queues a delivery only for endpoints subscribed to the event', function () {
    Bus::fake();

    endpointFor(['lms.enrolled']);
    endpointFor(['commerce.order_placed']);

    app(Webhooks::class)->fire('lms.enrolled', ['course' => 'الفيزياء']);

    Bus::assertDispatchedTimes(DeliverWebhook::class, 1);
});

it('treats a star subscription as every event', function () {
    Bus::fake();

    endpointFor(['*']);

    app(Webhooks::class)->fire('gamification.badge_earned');

    Bus::assertDispatched(DeliverWebhook::class);
});

it('sends nothing while the tenant has webhooks switched off', function () {
    Bus::fake();

    app(SettingsRepository::class)->set('integrations.webhooks_enabled', false);
    app(SettingsRepository::class)->flush();

    endpointFor(['*']);

    app(Webhooks::class)->fire('lms.enrolled');

    Bus::assertNothingDispatched();
});

it('skips an endpoint that failures already disabled', function () {
    Bus::fake();

    $endpoint = endpointFor(['*']);
    $endpoint->forceFill(['disabled_at' => now()])->save();

    app(Webhooks::class)->fire('lms.enrolled');

    Bus::assertNothingDispatched();
});

it('signs the body with the timestamp so a captured call cannot be replayed', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $endpoint = endpointFor(['lms.enrolled']);

    (new DeliverWebhook($endpoint->getKey(), 'lms.enrolled', ['course' => 'الفيزياء']))->handle();

    Http::assertSent(function ($request) use ($endpoint): bool {
        $timestamp = (int) $request->header('X-Usos-Timestamp')[0];
        $expected = 'sha256='.$endpoint->sign($request->body(), $timestamp);

        return $request->header('X-Usos-Signature')[0] === $expected;
    });
});

it('records the delivery so “did you send it?” has an answer', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $endpoint = endpointFor(['lms.enrolled']);

    (new DeliverWebhook($endpoint->getKey(), 'lms.enrolled', []))->handle();

    expect(WebhookDelivery::where('endpoint_id', $endpoint->getKey())->first())
        ->status_code->toBe(200)
        ->and($endpoint->fresh()->consecutive_failures)->toBe(0);
});

it('counts a rejection and keeps counting until the endpoint is disabled', function () {
    Http::fake(['*' => Http::response('nope', 500)]);

    $endpoint = endpointFor(['lms.enrolled']);
    $endpoint->forceFill(['consecutive_failures' => WebhookEndpoint::FAILURE_LIMIT - 1])->save();

    expect(fn () => (new DeliverWebhook($endpoint->getKey(), 'lms.enrolled', []))->handle())
        ->toThrow(RuntimeException::class);

    expect($endpoint->fresh()->disabled_at)->not->toBeNull();
});

it('clears the failure count after one success', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $endpoint = endpointFor(['lms.enrolled']);
    $endpoint->forceFill(['consecutive_failures' => 7])->save();

    (new DeliverWebhook($endpoint->getKey(), 'lms.enrolled', []))->handle();

    expect($endpoint->fresh()->consecutive_failures)->toBe(0);
});

it('fires from the notifier so every business event is covered', function () {
    Bus::fake();

    endpointFor(['lms.enrolled']);

    $student = User::factory()->create();

    notify('lms.enrolled', $student, ['course_title' => 'الفيزياء']);

    Bus::assertDispatched(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'lms.enrolled');
});
