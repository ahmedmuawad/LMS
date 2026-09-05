<?php

declare(strict_types=1);

use App\Core\Entitlements\Exceptions\QuotaExceededException;
use App\Core\Entitlements\Quota;
use App\Modules\Lms\Models\Course;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\DB;

/*
 | المرحلة ١ — الباقة تُنفَّذ.
 |
 | كانت الحدود أرقاماً في القاعدة لا يقرأها شيء، والقفل في القائمة
 | الجانبية يُفتح بكتابة الرابط. هذه الاختبارات تمنع رجوع ذلك.
 |
 | ولكلٍّ منها صيغتان: «يُمنع من تجاوز» و«يُفتح لمن يملك» — فاختبار
 | المنع وحده يمرّ على نظامٍ يمنع الجميع.
 */

beforeEach(function () {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

// ---------------------------------------------------------------
// العدّ الحيّ
// ---------------------------------------------------------------

it('counts what exists rather than trusting a counter', function () {
    $tenant = provision(['plan_key' => 'starter']);
    $quota = new Quota($tenant);

    $tenant->run(fn () => Course::factory()->count(4)->create());

    expect($quota->used('courses'))->toBe(4);

    // الحذف يحرّر مكانه فوراً — وهذا ما يعجز عنه العدّاد التراكمي
    $tenant->run(fn () => Course::query()->limit(2)->get()->each->delete());

    expect($quota->used('courses'))->toBe(2);
});

it('reports remaining and percent against the plan limit', function () {
    $tenant = provision(['plan_key' => 'starter']); // الكورسات = ١٠
    $quota = new Quota($tenant);

    $tenant->run(fn () => Course::factory()->count(8)->create());

    expect($quota->limit('courses'))->toBe(10)
        ->and($quota->remaining('courses'))->toBe(2)
        ->and($quota->percent('courses'))->toBe(80.0)
        ->and($quota->fits('courses', 2))->toBeTrue()
        ->and($quota->fits('courses', 3))->toBeFalse();
});

it('never limits what the plan grants without a ceiling', function () {
    $tenant = provision(['plan_key' => 'growth']); // الكورسات = unlimited
    $quota = new Quota($tenant);

    expect($quota->limit('courses'))->toBeNull()
        ->and($quota->fits('courses', 100_000))->toBeTrue()
        ->and($quota->remaining('courses'))->toBeNull();

    $quota->enforce('courses', 100_000); // لا يرمي
})->throwsNoExceptions();

// ---------------------------------------------------------------
// الإنفاذ
// ---------------------------------------------------------------

it('throws when the addition would cross the limit', function () {
    $tenant = provision(['plan_key' => 'starter']);
    $tenant->run(fn () => Course::factory()->count(10)->create());

    (new Quota($tenant))->enforce('courses');
})->throws(QuotaExceededException::class);

it('allows the addition that lands exactly on the limit', function () {
    $tenant = provision(['plan_key' => 'starter']);
    $tenant->run(fn () => Course::factory()->count(9)->create());

    (new Quota($tenant))->enforce('courses');
})->throwsNoExceptions();

it('tells the subscriber the number, not just no', function () {
    $tenant = provision(['plan_key' => 'starter']);
    $tenant->run(fn () => Course::factory()->count(10)->create());

    try {
        (new Quota($tenant))->enforce('courses');
        $this->fail('كان يجب أن يمنع');
    } catch (QuotaExceededException $e) {
        expect($e->used)->toBe(10)
            ->and($e->limit)->toBe(10)
            ->and($e->forHumans())->toContain('10');
    }
});

// ---------------------------------------------------------------
// الحدّ من الشاشة نفسها
// ---------------------------------------------------------------

it('blocks creating a course past the limit through the panel', function () {
    $tenant = provision(['plan_key' => 'starter']);
    $tenant->run(fn () => Course::factory()->count(10)->create());
    actingAsOwner($tenant);

    $before = $tenant->run(fn (): int => Course::count());

    $this->post('/admin/courses', [
        'title' => ['ar' => 'كورس فوق الحد'],
        'slug' => 'over-the-limit',
        'status' => 'draft',
    ])->assertSessionHas('quota_exceeded');

    expect($tenant->run(fn (): int => Course::count()))->toBe($before);
});

it('lets a course through while there is room', function () {
    $tenant = provision(['plan_key' => 'starter']);
    actingAsOwner($tenant);

    $before = $tenant->run(fn (): int => Course::count());

    $this->post('/admin/courses', [
        'title' => ['ar' => 'كورس ضمن الحد'],
        'slug' => 'within-the-limit',
        'status' => 'draft',
    ])->assertSessionMissing('quota_exceeded');

    expect($tenant->run(fn (): int => Course::count()))->toBe($before + 1);
});

// ---------------------------------------------------------------
// الميزة المقفولة لا تُفتح بكتابة رابطها
// ---------------------------------------------------------------

it('refuses a plan-locked resource even when its URL is typed directly', function () {
    $tenant = provision(['plan_key' => 'starter']); // بلا services_module
    actingAsOwner($tenant);

    $this->get('/admin/services')->assertStatus(402);
    $this->get('/admin/bookings')->assertStatus(402);
});

it('opens the same resource for a plan that includes it', function () {
    $tenant = provision(['plan_key' => 'professional']);
    actingAsOwner($tenant);

    $this->get('/admin/services')->assertOk();
});

it('refuses writing to a plan-locked resource', function () {
    $tenant = provision(['plan_key' => 'starter']);
    actingAsOwner($tenant);

    $this->post('/admin/services', ['title' => ['ar' => 'خدمة متسلّلة'], 'slug' => 'sneak'])
        ->assertStatus(402);
});

// ---------------------------------------------------------------
// ما لا يُعَدّ يُسجَّل، وما يُعَدّ لا يُسجَّل
// ---------------------------------------------------------------

it('meters what cannot be counted', function () {
    $tenant = provision(['plan_key' => 'starter']);
    $quota = new Quota($tenant);

    $quota->record('emails', 250);

    expect($quota->used('emails'))->toBe(250);
});

it('refuses to meter what it counts, so the two never disagree', function () {
    $tenant = provision(['plan_key' => 'starter']);
    $quota = new Quota($tenant);

    $quota->record('courses', 99);

    expect($quota->used('courses'))->toBe(0)
        ->and(DB::connection(config('tenancy.database.central_connection'))
            ->table('usage_records')
            ->where('tenant_id', $tenant->id)
            ->where('feature_key', 'courses')
            ->count())->toBe(0);
});

// ---------------------------------------------------------------
// شاشة الاستهلاك
// ---------------------------------------------------------------

it('shows the subscriber how close each limit is', function () {
    $tenant = provision(['plan_key' => 'starter']);
    $tenant->run(fn () => Course::factory()->count(3)->create());
    actingAsOwner($tenant);

    $this->get('/admin/usage')
        ->assertOk()
        ->assertSee('استهلاك باقتك', false)
        ->assertSee('الكورسات', false);
});

it('hides limits the plan does not grant at all', function () {
    $tenant = provision(['plan_key' => 'starter']);
    $rows = collect((new Quota($tenant))->overview())->pluck('key');

    // «البداية» بلا فروع: صفرٌ من صفر ليس استهلاكاً يُعرض
    expect($rows)->not->toContain('branches');
});
