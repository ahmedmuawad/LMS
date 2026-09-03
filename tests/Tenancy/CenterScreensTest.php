<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Center\Actions\EnrolStudent;
use App\Modules\Center\Actions\IssueInvoices;
use App\Modules\Center\Models\Attendance;
use App\Modules\Center\Models\Guardian;
use App\Modules\Center\Models\Invoice;
use App\Modules\Center\Models\Session;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

it('opens every center admin screen for the owner', function () {
    $tenant = provision(['platform_mode' => 'center']);

    $tenant->run(function (): void {
        $group = seedGroup();
        seedCashbox();
        app(EnrolStudent::class)->handle(seedCenterStudent(), $group);
    });

    actingAsOwner($tenant);

    foreach (['groups', 'center-students', 'branches', 'rooms', 'center-invoices'] as $resource) {
        tenantGet($tenant, '/admin/'.$resource)->assertOk();
    }

    tenantGet($tenant, '/admin/attendance')->assertOk();
    tenantGet($tenant, '/admin/schedule')->assertOk();
    tenantGet($tenant, '/admin/fees')->assertOk();
    tenantGet($tenant, '/admin/cashboxes')->assertOk();
});

it('lists the day sessions and opens the attendance sheet', function () {
    $tenant = provision(['platform_mode' => 'center']);

    $sessionId = $tenant->run(function (): int {
        $group = seedGroup();
        app(EnrolStudent::class)->handle(seedCenterStudent(), $group);

        return seedSession($group)->id;
    });

    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/attendance')->assertOk()->assertSee('فيزياء ٣ث');
    tenantGet($tenant, '/admin/attendance/'.$sessionId)->assertOk()->assertSee('يوسف حمدي');
});

it('saves an attendance sheet from the screen', function () {
    $tenant = provision(['platform_mode' => 'center']);

    [$sessionId, $studentId] = $tenant->run(function (): array {
        $group = seedGroup();
        $student = seedCenterStudent();
        app(EnrolStudent::class)->handle($student, $group);

        return [seedSession($group)->id, $student->id];
    });

    actingAsOwner($tenant);

    tenantPost($tenant, '/admin/attendance/'.$sessionId, [
        'status' => [$studentId => 'absent'],
    ])->assertRedirect();

    $tenant->run(function () use ($sessionId, $studentId): void {
        expect(Attendance::where('session_id', $sessionId)->where('student_id', $studentId)->first()->status)
            ->toBe('absent')
            ->and(Session::find($sessionId)->attendanceTaken())->toBeTrue();
    });
});

it('marks a card scan over json without reloading the page', function () {
    $tenant = provision(['platform_mode' => 'center']);

    [$sessionId, $code] = $tenant->run(function (): array {
        $group = seedGroup();
        $student = seedCenterStudent();
        app(EnrolStudent::class)->handle($student, $group);

        return [seedSession($group)->id, $student->code];
    });

    actingAsOwner($tenant);

    tenantPost($tenant, '/admin/attendance/'.$sessionId.'/mark', ['code' => $code, 'method' => 'qr'])
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('says plainly when a scanned card is not in this group', function () {
    $tenant = provision(['platform_mode' => 'center']);

    $sessionId = $tenant->run(function (): int {
        $group = seedGroup();
        seedCenterStudent();

        return seedSession($group)->id;
    });

    actingAsOwner($tenant);

    tenantPost($tenant, '/admin/attendance/'.$sessionId.'/mark', ['code' => 'ST00001'])
        ->assertStatus(422)
        ->assertJson(['ok' => false]);
});

it('checks a slot for clashes before anything is saved', function () {
    $tenant = provision(['platform_mode' => 'center']);

    [$groupId, $roomId] = $tenant->run(function (): array {
        $branch = seedBranch();
        $room = seedRoom($branch);
        $busy = seedGroup(['branch' => $branch]);
        $wanted = seedGroup(['branch' => $branch]);
        seedSession($busy, ['room_id' => $room->id]);

        return [$wanted->id, $room->id];
    });

    actingAsOwner($tenant);

    $response = tenantPost($tenant, '/admin/schedule/check', [
        'group_id' => $groupId,
        'room_id' => $roomId,
        'date' => now()->toDateString(),
        'starts_at' => '17:00',
        'ends_at' => '19:00',
    ])->assertOk();

    expect($response->json('ok'))->toBeFalse()
        ->and($response->json('suggestion.starts_at'))->not->toBeNull();
});

it('refuses to save a clashing session from the form', function () {
    $tenant = provision(['platform_mode' => 'center']);

    [$groupId, $roomId] = $tenant->run(function (): array {
        $branch = seedBranch();
        $room = seedRoom($branch);
        $busy = seedGroup(['branch' => $branch]);
        $wanted = seedGroup(['branch' => $branch]);
        seedSession($busy, ['room_id' => $room->id]);

        return [$wanted->id, $room->id];
    });

    actingAsOwner($tenant);

    tenantPost($tenant, '/admin/schedule/sessions', [
        'group_id' => $groupId,
        'room_id' => $roomId,
        'date' => now()->toDateString(),
        'starts_at' => '17:00',
        'ends_at' => '19:00',
    ])->assertSessionHasErrors('schedule');

    $tenant->run(fn () => expect(Session::count())->toBe(1));
});

it('shows the arrears board with the money actually owed', function () {
    $tenant = provision(['platform_mode' => 'center']);

    $tenant->run(function (): void {
        $group = seedGroup(['price_minor' => 40000]);
        app(EnrolStudent::class)->handle(seedCenterStudent(), $group);
        app(IssueInvoices::class)->handle($group, now()->format('Y-m'));
    });

    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/fees')
        ->assertOk()
        ->assertSee('يوسف حمدي')
        ->assertSee('400.00');
});

it('collects a payment from the arrears board', function () {
    $tenant = provision(['platform_mode' => 'center']);

    [$invoiceId, $studentId] = $tenant->run(function (): array {
        $group = seedGroup(['price_minor' => 40000]);
        $student = seedCenterStudent();
        app(EnrolStudent::class)->handle($student, $group);
        app(IssueInvoices::class)->handle($group, now()->format('Y-m'));

        return [Invoice::firstOrFail()->id, $student->id];
    });

    actingAsOwner($tenant);

    tenantPost($tenant, '/admin/fees/collect', [
        'invoice_id' => $invoiceId,
        'student_id' => $studentId,
        'amount' => '400.00',
        'method' => 'cash',
    ])->assertRedirect();

    $tenant->run(fn () => expect(Invoice::find($invoiceId)->status)->toBe('paid'));
});

it('opens the student file with attendance, dues and marks together', function () {
    $tenant = provision(['platform_mode' => 'center']);

    $studentId = $tenant->run(function (): int {
        $group = seedGroup(['price_minor' => 40000]);
        $student = seedCenterStudent();
        app(EnrolStudent::class)->handle($student, $group);
        app(IssueInvoices::class)->handle($group, now()->format('Y-m'));

        return $student->id;
    });

    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/center-students/'.$studentId)
        ->assertOk()
        ->assertSee('يوسف حمدي')
        ->assertSee('نسبة الحضور')
        ->assertSee('المستحق عليه');
});

it('renders the monthly report a guardian would receive', function () {
    $tenant = provision(['platform_mode' => 'center']);

    $studentId = $tenant->run(function (): int {
        $group = seedGroup();
        $student = seedCenterStudent();
        app(EnrolStudent::class)->handle($student, $group);

        return $student->id;
    });

    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/center-students/'.$studentId.'/report')
        ->assertOk()
        ->assertSee('التقرير الشهري');
});

it('shows a guardian their own children and nobody else', function () {
    $tenant = provision(['platform_mode' => 'center']);

    [$parentUser, $childId, $strangerId] = $tenant->run(function (): array {
        $group = seedGroup();
        $mine = seedCenterStudent('mine@example.test');
        $stranger = seedCenterStudent('other@example.test');
        app(EnrolStudent::class)->handle($mine, $group);

        $parentUser = User::create([
            'name' => 'والد يوسف', 'email' => 'parent@example.test',
            'password' => 'secret-password', 'role' => 'guardian', 'status' => 'active',
        ]);

        $guardian = Guardian::create([
            'user_id' => $parentUser->id, 'name' => 'والد يوسف',
            'phone' => '01000000000', 'can_login' => true,
        ]);

        $guardian->students()->attach($mine->id, ['is_primary' => true]);

        return [$parentUser, $mine->id, $stranger->id];
    });

    tenancy()->initialize($tenant);
    $this->actingAs($parentUser);

    tenantGet($tenant, '/guardian')->assertOk()->assertSee('يوسف حمدي');
    tenantGet($tenant, '/guardian/children/'.$childId)->assertOk();
    tenantGet($tenant, '/guardian/children/'.$strangerId)->assertNotFound();
});

it('keeps the guardian portal shut to someone who is not a guardian', function () {
    $tenant = provision(['platform_mode' => 'center']);
    actingAsOwner($tenant);

    tenantGet($tenant, '/guardian')->assertForbidden();
});
