<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Center\Models\Branch;
use App\Modules\Center\Models\Cashbox;
use App\Modules\Center\Models\Grade;
use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Room;
use App\Modules\Center\Models\Schedule;
use App\Modules\Center\Models\Session;
use App\Modules\Center\Models\Stage;
use App\Modules\Center\Models\Student;
use App\Modules\Center\Models\Subject;

/*
 | مصانع بيانات السنتر — تُستدعى دائماً داخل سياق مشترك.
 */

function seedBranch(array $overrides = []): Branch
{
    return Branch::create([
        'name' => ['ar' => 'الفرع الرئيسي', 'en' => 'Main branch'],
        'code' => 'MAIN'.random_int(10, 99),
        'is_active' => true,
        ...$overrides,
    ]);
}

function seedRoom(Branch $branch, array $overrides = []): Room
{
    return Room::create([
        'branch_id' => $branch->id,
        'name' => ['ar' => 'قاعة ١'],
        'capacity' => 30,
        ...$overrides,
    ]);
}

function seedGroup(array $overrides = []): Group
{
    $branch = $overrides['branch'] ?? seedBranch();
    unset($overrides['branch']);

    $stage = Stage::create(['name' => ['ar' => 'ثانوي']]);
    $grade = Grade::create(['stage_id' => $stage->id, 'name' => ['ar' => 'الثالث الثانوي']]);
    $subject = Subject::create(['name' => ['ar' => 'فيزياء'], 'stage_id' => $stage->id]);

    return Group::create([
        'branch_id' => $branch->id,
        'subject_id' => $subject->id,
        'grade_id' => $grade->id,
        'name' => ['ar' => 'فيزياء ٣ث — سبت'],
        'capacity' => 25,
        'currency' => (string) (tenant('currency') ?? 'EGP'),
        'price_minor' => 40000,
        'price_type' => 'monthly',
        'status' => 'open',
        ...$overrides,
    ]);
}

function seedCenterStudent(string $email = 'pupil@example.test', array $overrides = []): Student
{
    $user = User::create([
        'name' => 'يوسف حمدي', 'email' => $email,
        'password' => 'secret-password', 'role' => 'student', 'status' => 'active',
    ]);

    return Student::create([
        'user_id' => $user->id,
        'code' => Student::nextCode(),
        'status' => 'active',
        'joined_at' => now()->toDateString(),
        ...$overrides,
    ]);
}

function seedSession(Group $group, array $overrides = []): Session
{
    return Session::create([
        'group_id' => $group->id,
        'date' => now()->toDateString(),
        'starts_at' => '16:00:00',
        'ends_at' => '18:00:00',
        'status' => 'scheduled',
        ...$overrides,
    ]);
}

function seedSchedule(Group $group, array $overrides = []): Schedule
{
    return Schedule::create([
        'group_id' => $group->id,
        'weekday' => 6,      // السبت
        'starts_at' => '16:00:00',
        'ends_at' => '18:00:00',
        ...$overrides,
    ]);
}

function seedCashbox(?Branch $branch = null, array $overrides = []): Cashbox
{
    return Cashbox::create([
        'branch_id' => ($branch ?? seedBranch())->id,
        'name' => ['ar' => 'خزنة الاستقبال'],
        'currency' => (string) (tenant('currency') ?? 'EGP'),
        'opening_minor' => 0,
        'balance_minor' => 0,
        ...$overrides,
    ]);
}
