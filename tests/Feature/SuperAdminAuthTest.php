<?php

declare(strict_types=1);

use App\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

function superAdmin(array $overrides = []): SuperAdmin
{
    return SuperAdmin::create([
        'name' => 'أحمد معوّض',
        'email' => 'team@platform.test',
        'password' => Hash::make('platform-password'),
        'role' => 'super_admin',
        'is_active' => true,
        'email_verified_at' => now(),
        ...$overrides,
    ]);
}

it('closes the platform panel to guests', function () {
    $this->get('/admin')->assertRedirect(url('/super/login'));
});

it('shows the team login screen', function () {
    $this->get('/super/login')->assertOk()->assertSee('دخول فريق المنصة');
});

it('lets a team member in', function () {
    superAdmin();

    $this->post('/super/login', [
        'email' => 'team@platform.test',
        'password' => 'platform-password',
    ])->assertRedirect(url('/admin'));

    expect(auth('super')->check())->toBeTrue();
});

it('refuses a wrong password without revealing which field failed', function () {
    superAdmin();

    $this->from('/super/login')->post('/super/login', [
        'email' => 'team@platform.test',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    expect(auth('super')->check())->toBeFalse();
});

it('locks the door after five failed attempts', function () {
    superAdmin();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/super/login', [
            'email' => 'team@platform.test',
            'password' => 'wrong-password',
        ]);
    }

    $this->post('/super/login', [
        'email' => 'team@platform.test',
        'password' => 'platform-password',
    ])->assertSessionHasErrors('email');

    expect(auth('super')->check())->toBeFalse();

    RateLimiter::clear('super-login:team@platform.test|127.0.0.1');
});

it('keeps a suspended team member out of the panel', function () {
    $this->actingAs(superAdmin(['is_active' => false]), 'super')
        ->get('/admin')
        ->assertForbidden();
});

it('opens the overview to a signed-in team member', function () {
    $this->actingAs(superAdmin(), 'super')->get('/admin')->assertOk();
});

it('sends the team member back to the login screen after logout', function () {
    $this->actingAs(superAdmin(), 'super')
        ->post('/super/logout')
        ->assertRedirect(url('/super/login'));

    expect(auth('super')->check())->toBeFalse();
});

it('does not park a signed-in team member on the login screen', function () {
    $this->actingAs(superAdmin(), 'super')
        ->get('/super/login')
        ->assertRedirect(url('/admin'));
});

it('reads team members from the central database, never from a tenant', function () {
    expect((new SuperAdmin)->getConnectionName())
        ->toBe(config('tenancy.database.central_connection'));
});
