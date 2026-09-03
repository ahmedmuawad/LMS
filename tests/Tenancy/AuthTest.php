<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\RateLimiter;

// لوحة التحكم لا تُفتح بلا حساب، ولا لكل حساب.

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(CountrySeeder::class);
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);

    $this->tenant = provision(['owner_email' => 'owner@auth.test', 'password' => 'owner-password']);
    $this->base = 'http://'.$this->tenant->domains->first()->domain;
    RateLimiter::clear('login:owner@auth.test|127.0.0.1');
});

afterEach(fn () => tenancy()->initialized && tenancy()->end());

it('sends a guest from the panel to the login page', function () {
    $this->get($this->base.'/admin/users')->assertRedirect($this->base.'/login');
});

it('logs the owner in and lands on the panel', function () {
    $this->post($this->base.'/login', ['email' => 'owner@auth.test', 'password' => 'owner-password'])
        ->assertRedirect($this->base.'/admin/dashboard');

    $this->assertAuthenticated();
});

it('accepts a phone number as the login identifier', function () {
    $this->tenant->run(fn () => User::where('email', 'owner@auth.test')->update(['phone' => '+201000000000']));

    $this->post($this->base.'/login', ['email' => '+201000000000', 'password' => 'owner-password'])
        ->assertRedirect($this->base.'/admin/dashboard');
});

it('rejects wrong credentials without saying which part was wrong', function () {
    $this->from($this->base.'/login')
        ->post($this->base.'/login', ['email' => 'owner@auth.test', 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('locks out after repeated failures', function () {
    foreach (range(1, 5) as $ignored) {
        $this->from($this->base.'/login')
            ->post($this->base.'/login', ['email' => 'owner@auth.test', 'password' => 'wrong']);
    }

    // المحاولة السادسة تُرفض حتى بكلمة المرور الصحيحة
    $this->from($this->base.'/login')
        ->post($this->base.'/login', ['email' => 'owner@auth.test', 'password' => 'owner-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('refuses the panel to a student rather than pretending the session expired', function () {
    $student = $this->tenant->run(fn () => User::create([
        'name' => 'طالب', 'email' => 'student@auth.test', 'password' => 'student-password',
        'status' => 'active', 'role' => 'student',
    ]));

    tenancy()->initialize($this->tenant);
    $this->actingAs($student);

    $this->get($this->base.'/admin/users')->assertForbidden();
});

it('lets an instructor into the panel but only onto its own screens', function () {
    $instructor = $this->tenant->run(fn () => User::create([
        'name' => 'مدرّس', 'email' => 'teacher@auth.test', 'password' => 'teacher-password',
        'status' => 'active', 'role' => 'instructor',
    ]));

    tenancy()->initialize($this->tenant);
    $this->actingAs($instructor);

    // يدخل اللوحة…
    $this->get($this->base.'/admin/dashboard')->assertOk();

    // …ولا يفتح شاشة ليست له. كان هذا يُعيد 200 قبل طبقة الصلاحيات.
    $this->get($this->base.'/admin/users')->assertForbidden();
});

it('refuses a suspended admin', function () {
    $suspended = $this->tenant->run(fn () => User::create([
        'name' => 'موقوف', 'email' => 'sus@auth.test', 'password' => 'x-password',
        'status' => 'suspended', 'role' => 'admin',
    ]));

    tenancy()->initialize($this->tenant);
    $this->actingAs($suspended);

    $this->get($this->base.'/admin/users')->assertForbidden();
});

it('makes the provisioned account the owner', function () {
    $this->tenant->run(function (): void {
        $owner = User::where('email', 'owner@auth.test')->first();

        expect($owner->role)->toBe('owner')
            ->and($owner->isOwner())->toBeTrue()
            ->and($owner->canAccessPanel())->toBeTrue();
    });
});

it('logs out', function () {
    $this->post($this->base.'/login', ['email' => 'owner@auth.test', 'password' => 'owner-password']);
    $this->assertAuthenticated();

    $this->post($this->base.'/logout')->assertRedirect($this->base);
    $this->assertGuest();
});

it('keeps the public site open to guests', function () {
    $this->get($this->base.'/')->assertOk();
});
