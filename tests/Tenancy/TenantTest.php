<?php

declare(strict_types=1);

use App\Core\Entitlements\Models\Plan;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

it('stores declared columns as real columns, not JSON', function () {
    $tenant = makeTenant('growth', ['platform_mode' => 'center', 'country' => 'SA', 'currency' => 'SAR']);

    $row = DB::table('tenants')->find($tenant->id);

    expect($row->platform_mode)->toBe('center')
        ->and($row->country)->toBe('SA')
        ->and($row->currency)->toBe('SAR');
});

it('answers questions about its platform mode', function () {
    expect(makeTenant(attributes: ['platform_mode' => 'solo'])->hasMultipleInstructors())->toBeFalse()
        ->and(makeTenant(attributes: ['platform_mode' => 'marketplace'])->hasMultipleInstructors())->toBeTrue()
        ->and(makeTenant(attributes: ['platform_mode' => 'center'])->managesCenter())->toBeTrue()
        ->and(makeTenant(attributes: ['platform_mode' => 'solo', 'center_enabled' => true])->managesCenter())->toBeTrue()
        ->and(makeTenant(attributes: ['delivery_mode' => 'blended'])->offersLive())->toBeTrue()
        ->and(makeTenant(attributes: ['delivery_mode' => 'recorded'])->offersLive())->toBeFalse();
});

it('keeps the public site running for students when the tenant is suspended', function () {
    // قاعدة مقدّسة: لا نعاقب الطالب بمشكلة اشتراك مشتركه
    $tenant = makeTenant(attributes: ['status' => 'suspended']);

    expect($tenant->isPubliclyAvailable())->toBeTrue()
        ->and($tenant->canAccessDashboard())->toBeFalse();
});

it('closes the public site only while provisioning or archived', function () {
    expect(makeTenant(attributes: ['status' => 'provisioning'])->isPubliclyAvailable())->toBeFalse()
        ->and(makeTenant(attributes: ['status' => 'archived'])->isPubliclyAvailable())->toBeFalse()
        ->and(makeTenant(attributes: ['status' => 'past_due'])->canAccessDashboard())->toBeTrue();
});

it('counts trial days left', function () {
    $tenant = makeTenant(attributes: ['status' => 'trialing', 'trial_ends_at' => now()->addDays(9)->addHour()]);

    expect($tenant->onTrial())->toBeTrue()
        ->and($tenant->trialDaysLeft())->toBe(10);
});

it('prices a plan per currency without conversion', function () {
    $plan = Plan::find('professional');

    expect($plan->priceIn('EGP')->toDecimal())->toBe('2999.00')
        ->and($plan->priceIn('SAR')->toDecimal())->toBe('499.00')
        ->and($plan->priceIn('KWD'))->toBeNull()          // لا سعر مثبّت لهذه العملة
        ->and($plan->supportsMode('center'))->toBeTrue()
        ->and(Plan::find('starter')->supportsMode('center'))->toBeFalse();
});
