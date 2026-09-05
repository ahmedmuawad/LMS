<?php

declare(strict_types=1);

use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\DB;

// ADR-011 — كل ميزة مبنية؛ الباقة وحدها تقرّر المتاح.

beforeEach(function () {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

it('grants boolean features from the plan', function () {
    $tenant = makeTenant('growth');

    /*
     | «إدارة السنتر» في باقة النمو عمداً.
     |
     | كانت خارجها، فوجد المدرّس المشترك فيها أن لا مراحل ولا
     | مجموعات ولا حضور — وهي أوّل ما يطلبه مدرّسٌ يُدرّس مجموعات.
     | فأُضيفت مع `groups` و`center_finance` و`parent_portal`.
     |
     | وSCORM تبقى خارجها: هي للباقة الاحترافية.
     */
    expect($tenant->allows('page_builder'))->toBeTrue()
        ->and($tenant->allows('recharge_codes'))->toBeTrue()
        ->and($tenant->allows('center_management'))->toBeTrue()
        ->and($tenant->allows('scorm'))->toBeFalse();
});

it('denies everything a plan does not grant', function () {
    expect(makeTenant('starter')->allows('custom_domain'))->toBeFalse();
});

it('reads numeric limits and unlimited values', function () {
    $tenant = makeTenant('growth');

    expect($tenant->limitOf('students'))->toBe(1000)
        ->and($tenant->limitOf('courses'))->toBeNull()               // unlimited
        ->and($tenant->entitlements()->isUnlimited('courses'))->toBeTrue()
        ->and($tenant->limitOf('branches'))->toBe(0);                // غير ممنوحة أصلاً
});

it('lets a tenant-specific override beat the plan', function () {
    $tenant = makeTenant('starter');
    expect($tenant->allows('custom_domain'))->toBeFalse();

    DB::table('tenant_features')->insert([
        'tenant_id' => $tenant->id, 'feature_key' => 'custom_domain',
        'value' => '1', 'reason' => 'عرض ترويجي',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $tenant->entitlements()->flush();

    expect($tenant->fresh()->allows('custom_domain'))->toBeTrue();
});

it('ignores an expired override', function () {
    $tenant = makeTenant('starter');

    DB::table('tenant_features')->insert([
        'tenant_id' => $tenant->id, 'feature_key' => 'custom_domain',
        'value' => '1', 'expires_at' => now()->subDay(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $tenant->entitlements()->flush();

    expect($tenant->allows('custom_domain'))->toBeFalse();
});

it('tracks usage and remaining capacity', function () {
    $tenant = makeTenant('starter');   // 100 طالب

    expect($tenant->usageOf('students'))->toBe(0)
        ->and($tenant->remainingOf('students'))->toBe(100);

    $tenant->entitlements()->recordUsage('students', 98);

    expect($tenant->usageOf('students'))->toBe(98)
        ->and($tenant->remainingOf('students'))->toBe(2)
        ->and($tenant->hasReachedLimit('students'))->toBeFalse()
        ->and($tenant->entitlements()->usagePercent('students'))->toBe(98.0);

    $tenant->entitlements()->recordUsage('students', 2);

    expect($tenant->hasReachedLimit('students'))->toBeTrue();
});

it('never reports an unlimited feature as exhausted', function () {
    $tenant = makeTenant('growth');
    $tenant->entitlements()->recordUsage('courses', 100_000);

    expect($tenant->hasReachedLimit('courses'))->toBeFalse()
        ->and($tenant->remainingOf('courses'))->toBeNull();
});

it('never lets usage fall below zero', function () {
    $tenant = makeTenant('starter');
    $tenant->entitlements()->recordUsage('students', 3);
    $tenant->entitlements()->recordUsage('students', -10);

    expect($tenant->usageOf('students'))->toBe(0);
});

it('scopes monthly quotas to the current period', function () {
    $tenant = makeTenant('starter');
    $tenant->entitlements()->recordUsage('emails', 500);

    expect($tenant->usageOf('emails'))->toBe(500);

    // الشهر التالي يبدأ من الصفر
    $this->travel(1)->months();
    expect($tenant->usageOf('emails'))->toBe(0);
});
