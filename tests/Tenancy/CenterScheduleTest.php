<?php

declare(strict_types=1);

use App\Core\Admin\Resources\Center\GroupResource;
use App\Models\User;
use App\Modules\Center\Actions\DetectConflicts;
use App\Modules\Center\Actions\GenerateSessions;
use App\Modules\Center\Models\Branch;
use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Room;
use App\Modules\Center\Models\Schedule;
use App\Modules\Center\Models\Session;
use App\Modules\Center\Models\Subject;
use App\Modules\Center\Models\SubjectTeacher;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

/*
 | جدول السنتر — القاعة لا تُحجز مرتين.
 |
 | كان الفحص كلّه على الحصة المفردة، والسنتر يُدار بالموعد الأسبوعي.
 | فموعدان في قاعة واحدة يُقبَلان بلا اعتراض، ولا يظهر الخلل إلا
 | بابين مقفلين ومدرّسَين واقفَين بعد أن وُزّع الجدول.
 */

/** سنتر صغير: فرع وقاعتان ومادة ومدرّسان ومجموعتان. */
function seedCenterFixture(): array
{
    $branch = Branch::create(['name' => ['ar' => 'المقر'], 'code' => 'HQ', 'is_active' => true]);

    $rooms = collect(['قاعة أ', 'قاعة ب'])->map(fn (string $name): Room => Room::create([
        'branch_id' => $branch->getKey(), 'name' => ['ar' => $name], 'capacity' => 20, 'is_active' => true,
    ]));

    $subject = Subject::create(['name' => ['ar' => 'الرياضيات'], 'is_active' => true]);

    $teachers = collect(['أ. منى', 'أ. طارق'])->map(function (string $name, int $i) use ($subject, $branch): User {
        $user = User::create([
            'name' => $name, 'email' => 'teacher'.$i.'@c.test', 'password' => 'password',
            'role' => 'instructor', 'status' => 'active',
        ]);

        SubjectTeacher::create([
            'subject_id' => $subject->getKey(), 'user_id' => $user->getKey(),
            'branch_id' => $branch->getKey(), 'is_active' => true,
        ]);

        return $user;
    });

    $groups = $teachers->map(fn (User $teacher, int $i): Group => Group::create([
        'branch_id' => $branch->getKey(),
        'subject_id' => $subject->getKey(),
        'teacher_id' => $teacher->getKey(),
        'name' => ['ar' => 'مجموعة '.($i + 1)],
        'venue' => 'branch', 'kind' => 'group',
        'capacity' => 18, 'currency' => 'EGP', 'price_minor' => 30000,
        'status' => 'running',
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->addMonths(2)->toDateString(),
    ]));

    return compact('branch', 'rooms', 'subject', 'teachers', 'groups');
}

// ------------------------------------------------------------------
// تعارض الموعد المتكرر
// ------------------------------------------------------------------

it('يمنع حجز قاعة محجوزة في اليوم والوقت نفسه', function (): void {
    provision(['platform_mode' => 'center', 'plan_key' => 'center'])->run(function (): void {
        $fixture = seedCenterFixture();
        $room = $fixture['rooms'][0];

        Schedule::create([
            'group_id' => $fixture['groups'][0]->getKey(),
            'room_id' => $room->getKey(),
            'weekday' => 6, 'starts_at' => '16:00:00', 'ends_at' => '17:30:00',
        ]);

        $conflicts = app(DetectConflicts::class)->forSchedule([
            'group_id' => (int) $fixture['groups'][1]->getKey(),
            'room_id' => (int) $room->getKey(),
            'teacher_id' => (int) $fixture['teachers'][1]->getKey(),
            'weekday' => 6, 'starts_at' => '16:00', 'ends_at' => '17:30',
        ]);

        expect(collect($conflicts)->pluck('code'))->toContain('room');
    });
});

it('يمنع التداخل الجزئي لا المطابقة وحدها', function (): void {
    provision(['platform_mode' => 'center', 'plan_key' => 'center'])->run(function (): void {
        $fixture = seedCenterFixture();
        $room = $fixture['rooms'][0];

        Schedule::create([
            'group_id' => $fixture['groups'][0]->getKey(),
            'room_id' => $room->getKey(),
            'weekday' => 6, 'starts_at' => '16:00:00', 'ends_at' => '17:30:00',
        ]);

        $check = fn (string $from, string $to): array => app(DetectConflicts::class)->forSchedule([
            'group_id' => (int) $fixture['groups'][1]->getKey(),
            'room_id' => (int) $room->getKey(),
            'weekday' => 6, 'starts_at' => $from, 'ends_at' => $to,
        ]);

        // يبدأ داخلها · ينتهي داخلها · يبتلعها كاملة
        expect(collect($check('17:00', '18:30'))->pluck('code'))->toContain('room')
            ->and(collect($check('15:00', '16:30'))->pluck('code'))->toContain('room')
            ->and(collect($check('15:00', '19:00'))->pluck('code'))->toContain('room')
            // والملاصق لا يتعارض: تنتهي ٤:٣٠ وتبدأ ٤:٣٠
            ->and($check('17:30', '19:00'))->toBe([])
            ->and($check('14:00', '16:00'))->toBe([]);
    });
});

it('يمنع المدرّس من مكانين في وقت واحد', function (): void {
    provision(['platform_mode' => 'center', 'plan_key' => 'center'])->run(function (): void {
        $fixture = seedCenterFixture();

        Schedule::create([
            'group_id' => $fixture['groups'][0]->getKey(),
            'room_id' => $fixture['rooms'][0]->getKey(),
            'weekday' => 6, 'starts_at' => '16:00:00', 'ends_at' => '17:30:00',
        ]);

        // مجموعة أخرى لنفس المدرّس في قاعة أخرى — القاعة خالية والمدرّس لا
        $second = Group::create([
            'branch_id' => $fixture['branch']->getKey(),
            'subject_id' => $fixture['subject']->getKey(),
            'teacher_id' => $fixture['teachers'][0]->getKey(),
            'name' => ['ar' => 'مجموعة ثالثة'], 'venue' => 'branch', 'kind' => 'group',
            'capacity' => 18, 'currency' => 'EGP', 'status' => 'running',
        ]);

        $conflicts = app(DetectConflicts::class)->forSchedule([
            'group_id' => (int) $second->getKey(),
            'room_id' => (int) $fixture['rooms'][1]->getKey(),
            'teacher_id' => (int) $fixture['teachers'][0]->getKey(),
            'weekday' => 6, 'starts_at' => '16:30', 'ends_at' => '18:00',
        ]);

        expect(collect($conflicts)->pluck('code'))->toContain('teacher')
            ->not->toContain('room');
    });
});

it('لا يتعارض موعدان تباعدت نافذتا سريانهما', function (): void {
    provision(['platform_mode' => 'center', 'plan_key' => 'center'])->run(function (): void {
        $fixture = seedCenterFixture();
        $room = $fixture['rooms'][0];

        Schedule::create([
            'group_id' => $fixture['groups'][0]->getKey(),
            'room_id' => $room->getKey(),
            'weekday' => 6, 'starts_at' => '16:00:00', 'ends_at' => '17:30:00',
            'effective_from' => now()->toDateString(),
            'effective_to' => now()->addMonth()->toDateString(),
        ]);

        // يبدأ بعد أن ينتهي الأول — القاعة تُسلَّم لا تُقاسَم
        $conflicts = app(DetectConflicts::class)->forSchedule([
            'group_id' => (int) $fixture['groups'][1]->getKey(),
            'room_id' => (int) $room->getKey(),
            'weekday' => 6, 'starts_at' => '16:00', 'ends_at' => '17:30',
            'effective_from' => now()->addMonths(2)->toDateString(),
        ]);

        expect($conflicts)->toBe([]);
    });
});

it('يرفض قاعة في فرع غير فرع المجموعة', function (): void {
    provision(['platform_mode' => 'center', 'plan_key' => 'center'])->run(function (): void {
        $fixture = seedCenterFixture();

        $other = Branch::create(['name' => ['ar' => 'فرع آخر'], 'code' => 'OTH', 'is_active' => true]);
        $room = Room::create(['branch_id' => $other->getKey(), 'name' => ['ar' => 'قاعة بعيدة'], 'capacity' => 20]);

        $conflicts = app(DetectConflicts::class)->forSchedule([
            'group_id' => (int) $fixture['groups'][0]->getKey(),
            'room_id' => (int) $room->getKey(),
            'weekday' => 6, 'starts_at' => '16:00', 'ends_at' => '17:30',
        ]);

        expect(collect($conflicts)->pluck('code'))->toContain('branch');
    });
});

// ------------------------------------------------------------------
// من الشاشة لا من الكود
// ------------------------------------------------------------------

it('ترفض شاشة المواعيد حجزاً متعارضاً ولا تحفظه', function (): void {
    $tenant = provision(['platform_mode' => 'center', 'plan_key' => 'center']);

    $ids = $tenant->run(function (): array {
        $fixture = seedCenterFixture();

        Schedule::create([
            'group_id' => $fixture['groups'][0]->getKey(),
            'room_id' => $fixture['rooms'][0]->getKey(),
            'weekday' => 6, 'starts_at' => '16:00:00', 'ends_at' => '17:30:00',
        ]);

        return [
            'group' => (int) $fixture['groups'][1]->getKey(),
            'room' => (int) $fixture['rooms'][0]->getKey(),
        ];
    });

    tenancy()->initialize($tenant);
    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/groups/'.$ids['group'].'/slots')->assertOk();

    tenantPost($tenant, '/admin/groups/'.$ids['group'].'/slots', [
        'room_id' => $ids['room'], 'weekday' => 6, 'starts_at' => '16:30', 'ends_at' => '18:00',
    ])->assertSessionHasErrors('slot');

    $tenant->run(fn () => expect(Schedule::count())->toBe(1));
});

it('تقبل الشاشة موعداً خالياً وتحفظه', function (): void {
    $tenant = provision(['platform_mode' => 'center', 'plan_key' => 'center']);

    $ids = $tenant->run(function (): array {
        $fixture = seedCenterFixture();

        return [
            'group' => (int) $fixture['groups'][0]->getKey(),
            'room' => (int) $fixture['rooms'][0]->getKey(),
        ];
    });

    tenancy()->initialize($tenant);
    actingAsOwner($tenant);

    tenantPost($tenant, '/admin/groups/'.$ids['group'].'/slots', [
        'room_id' => $ids['room'], 'weekday' => 6, 'starts_at' => '16:00', 'ends_at' => '17:30',
    ])->assertSessionHasNoErrors();

    $tenant->run(function (): void {
        $slot = Schedule::firstOrFail();

        expect($slot->weekday)->toBe(6)
            ->and($slot->timeLabel())->toBe('16:00 – 17:30');
    });
});

// ------------------------------------------------------------------
// التوليد لا يدهس حجزاً
// ------------------------------------------------------------------

it('لا يولّد التوليد حصة في قاعة محجوزة ويُبلّغ عنها', function (): void {
    provision(['platform_mode' => 'center', 'plan_key' => 'center'])->run(function (): void {
        $fixture = seedCenterFixture();
        $room = $fixture['rooms'][0];

        // موعدان متعارضان زُرعا في القاعدة مباشرة (بيانات مهاجَرة مثلاً)
        foreach ([0, 1] as $i) {
            Schedule::create([
                'group_id' => $fixture['groups'][$i]->getKey(),
                'room_id' => $room->getKey(),
                'weekday' => 6, 'starts_at' => '16:00:00', 'ends_at' => '17:30:00',
            ]);
        }

        $from = now()->startOfWeek(Carbon::SUNDAY);
        $to = $from->copy()->addWeeks(2);

        $first = app(GenerateSessions::class)->handle($fixture['groups'][0]->refresh(), $from->copy(), $to->copy());
        $second = app(GenerateSessions::class)->handle($fixture['groups'][1]->refresh(), $from->copy(), $to->copy());

        expect($first['created'])->toBeGreaterThan(0)
            // الثانية لا تُولَّد: القاعة صارت محجوزة، والسبب مذكور
            ->and($second['created'])->toBe(0)
            ->and($second['conflicts'])->not->toBeEmpty()
            ->and($second['conflicts'][0]['reason'])->toContain('القاعة محجوزة');

        // ولا حصّتان في القاعة نفسها في اللحظة نفسها
        $clashes = Session::where('room_id', $room->getKey())
            ->selectRaw('date, starts_at, count(*) as total')
            ->groupBy('date', 'starts_at')
            ->havingRaw('count(*) > 1')
            ->get();

        expect($clashes)->toBeEmpty();
    });
});

// ------------------------------------------------------------------
// مدرّسو المادة
// ------------------------------------------------------------------

it('يعرف كل مادة مدرّسيها ولا يخلطهم', function (): void {
    provision(['platform_mode' => 'center', 'plan_key' => 'center'])->run(function (): void {
        $fixture = seedCenterFixture();

        $arabic = Subject::create(['name' => ['ar' => 'اللغة العربية'], 'is_active' => true]);
        $other = User::create([
            'name' => 'أ. سلوى', 'email' => 'arabic@c.test', 'password' => 'password',
            'role' => 'instructor', 'status' => 'active',
        ]);
        SubjectTeacher::create([
            'subject_id' => $arabic->getKey(), 'user_id' => $other->getKey(), 'is_active' => true,
        ]);

        expect($fixture['subject']->teachers()->pluck('name')->all())->toBe(['أ. منى', 'أ. طارق'])
            ->and($arabic->teachers()->pluck('name')->all())->toBe(['أ. سلوى']);
    });
});

it('تفتح شاشتا إشغال القاعات ومدرّسي السنتر بمحتواهما', function (): void {
    $tenant = provision(['platform_mode' => 'center', 'plan_key' => 'center']);

    $tenant->run(function (): void {
        $fixture = seedCenterFixture();

        Schedule::create([
            'group_id' => $fixture['groups'][0]->getKey(),
            'room_id' => $fixture['rooms'][0]->getKey(),
            'weekday' => 6, 'starts_at' => '16:00:00', 'ends_at' => '17:30:00',
        ]);
    });

    tenancy()->initialize($tenant);
    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/rooms-occupancy?weekday=6')
        ->assertOk()
        ->assertSee('قاعة أ', false)
        ->assertSee('قاعة ب', false)
        // القاعة الخالية تُقال خالية لا تُترك بلا كلمة
        ->assertSee(__('فارغة طوال اليوم'), false);

    tenantGet($tenant, '/admin/center-teachers')
        ->assertOk()
        ->assertSee('أ. منى', false)
        ->assertSee('الرياضيات', false);
});

it('يفتح صفّ المجموعة مواعيدها لا رابطاً مكسوراً', function (): void {
    $tenant = provision(['platform_mode' => 'center', 'plan_key' => 'center']);

    $id = $tenant->run(fn (): int => (int) seedCenterFixture()['groups'][0]->getKey());

    tenancy()->initialize($tenant);
    actingAsOwner($tenant);

    // كان يشير إلى /admin/groups/{id} ولا مسار بهذا الاسم
    $url = app(GroupResource::class)
        ->recordUrl(Group::findOrFail($id), 'groups');

    expect($url)->toContain('/admin/groups/'.$id.'/slots');

    // والوجهة تفتح فعلاً لا تعيد 404
    tenantGet($tenant, parse_url($url, PHP_URL_PATH))->assertOk();
});
