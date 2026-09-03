<?php

declare(strict_types=1);

use App\Core\Support\Money;
use App\Models\User;
use App\Modules\Center\Actions\CloseCashbox;
use App\Modules\Center\Actions\CollectPayment;
use App\Modules\Center\Actions\DetectConflicts;
use App\Modules\Center\Actions\EnrolStudent;
use App\Modules\Center\Actions\GenerateSessions;
use App\Modules\Center\Actions\IssueInvoices;
use App\Modules\Center\Actions\MonthlyReport;
use App\Modules\Center\Actions\TakeAttendance;
use App\Modules\Center\Models\Discount;
use App\Modules\Center\Models\Holiday;
use App\Modules\Center\Models\Invoice;
use App\Modules\Center\Models\Session;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

// ------------------------------------------------------------------
// كشف التعارض
// ------------------------------------------------------------------

it('accepts a slot that clashes with nothing', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $branch = seedBranch();
        $group = seedGroup(['branch' => $branch]);
        $room = seedRoom($branch);

        $conflicts = app(DetectConflicts::class)->handle([
            'group_id' => $group->id,
            'room_id' => $room->id,
            'date' => now()->toDateString(),
            'starts_at' => '16:00',
            'ends_at' => '18:00',
        ]);

        expect($conflicts)->toBe([]);
    });
});

it('catches a room booked twice at the same hour', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $branch = seedBranch();
        $room = seedRoom($branch);
        $first = seedGroup(['branch' => $branch]);
        $second = seedGroup(['branch' => $branch]);

        seedSession($first, ['room_id' => $room->id]);

        $conflicts = app(DetectConflicts::class)->handle([
            'group_id' => $second->id,
            'room_id' => $room->id,
            'date' => now()->toDateString(),
            'starts_at' => '17:00',
            'ends_at' => '19:00',
        ]);

        expect(collect($conflicts)->pluck('code'))->toContain('room');
    });
});

it('catches a teacher standing in two places', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $teacher = User::where('role', 'owner')->firstOrFail();
        $branch = seedBranch();
        $first = seedGroup(['branch' => $branch, 'teacher_id' => $teacher->id]);
        $second = seedGroup(['branch' => $branch]);

        seedSession($first, ['teacher_id' => $teacher->id]);

        $conflicts = app(DetectConflicts::class)->handle([
            'group_id' => $second->id,
            'teacher_id' => $teacher->id,
            'date' => now()->toDateString(),
            'starts_at' => '17:00',
            'ends_at' => '19:00',
        ]);

        expect(collect($conflicts)->pluck('code'))->toContain('teacher');
    });
});

it('lets two sessions share an hour in different rooms', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $branch = seedBranch();
        $roomA = seedRoom($branch, ['name' => ['ar' => 'قاعة أ']]);
        $roomB = seedRoom($branch, ['name' => ['ar' => 'قاعة ب']]);
        $first = seedGroup(['branch' => $branch]);
        $second = seedGroup(['branch' => $branch]);

        seedSession($first, ['room_id' => $roomA->id]);

        expect(app(DetectConflicts::class)->handle([
            'group_id' => $second->id,
            'room_id' => $roomB->id,
            'date' => now()->toDateString(),
            'starts_at' => '16:00',
            'ends_at' => '18:00',
        ]))->toBe([]);
    });
});

it('does not call back-to-back sessions a clash', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $branch = seedBranch();
        $room = seedRoom($branch);
        $first = seedGroup(['branch' => $branch]);
        $second = seedGroup(['branch' => $branch]);

        seedSession($first, ['room_id' => $room->id, 'starts_at' => '16:00:00', 'ends_at' => '18:00:00']);

        expect(app(DetectConflicts::class)->handle([
            'group_id' => $second->id,
            'room_id' => $room->id,
            'date' => now()->toDateString(),
            'starts_at' => '18:00',
            'ends_at' => '20:00',
        ]))->toBe([]);
    });
});

it('refuses a room too small for the group', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $branch = seedBranch();
        $room = seedRoom($branch, ['capacity' => 10]);
        $group = seedGroup(['branch' => $branch]);
        $group->forceFill(['enrolled_count' => 25])->save();

        expect(collect(app(DetectConflicts::class)->handle([
            'group_id' => $group->id,
            'room_id' => $room->id,
            'date' => now()->toDateString(),
            'starts_at' => '16:00',
            'ends_at' => '18:00',
        ]))->pluck('code'))->toContain('capacity');
    });
});

it('refuses a session on a public holiday', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $group = seedGroup();

        Holiday::create([
            'name' => ['ar' => 'عيد الفطر'],
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(2)->toDateString(),
        ]);

        expect(collect(app(DetectConflicts::class)->handle([
            'group_id' => $group->id,
            'date' => now()->toDateString(),
            'starts_at' => '16:00',
            'ends_at' => '18:00',
        ]))->pluck('code'))->toContain('holiday');
    });
});

it('rejects an end time that precedes the start', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $group = seedGroup();

        expect(collect(app(DetectConflicts::class)->handle([
            'group_id' => $group->id,
            'date' => now()->toDateString(),
            'starts_at' => '18:00',
            'ends_at' => '16:00',
        ]))->pluck('code'))->toContain('time');
    });
});

it('offers the nearest free slot instead of just saying no', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $branch = seedBranch();
        $room = seedRoom($branch);
        $first = seedGroup(['branch' => $branch]);
        $second = seedGroup(['branch' => $branch]);

        seedSession($first, ['room_id' => $room->id, 'starts_at' => '16:00:00', 'ends_at' => '18:00:00']);

        $slot = [
            'group_id' => $second->id,
            'room_id' => $room->id,
            'date' => now()->toDateString(),
            'starts_at' => '16:00',
            'ends_at' => '18:00',
        ];

        $suggestion = app(DetectConflicts::class)->suggestAlternative($slot);

        expect($suggestion)->not->toBeNull()
            ->and($suggestion['starts_at'])->toBe('18:00');
    });
});

// ------------------------------------------------------------------
// توليد الحصص
// ------------------------------------------------------------------

it('generates sessions from the weekly schedule and skips holidays', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $group = seedGroup([
            'start_date' => now()->startOfWeek(Carbon::SUNDAY)->toDateString(),
            'end_date' => now()->startOfWeek(Carbon::SUNDAY)->addWeeks(3)->toDateString(),
        ]);

        seedSchedule($group);

        // عطلة في السبت الثاني
        $secondSaturday = now()->startOfWeek(Carbon::SUNDAY)->addDays(6)->addWeek();
        Holiday::create([
            'name' => ['ar' => 'عطلة'],
            'starts_on' => $secondSaturday->toDateString(),
            'ends_on' => $secondSaturday->toDateString(),
        ]);

        $result = app(GenerateSessions::class)->handle($group);

        expect($result['created'])->toBeGreaterThan(0)
            ->and($result['holidays'])->toBe(1)
            ->and(Session::whereDate('date', $secondSaturday->toDateString())->count())->toBe(0);
    });
});

it('never duplicates a session when generation runs twice', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $group = seedGroup([
            'start_date' => now()->startOfWeek(Carbon::SUNDAY)->toDateString(),
            'end_date' => now()->startOfWeek(Carbon::SUNDAY)->addWeeks(2)->toDateString(),
        ]);
        seedSchedule($group);

        $first = app(GenerateSessions::class)->handle($group);
        $second = app(GenerateSessions::class)->handle($group);

        expect($second['created'])->toBe(0)
            ->and($second['skipped'])->toBe($first['created']);
    });
});

// ------------------------------------------------------------------
// التسجيل والخصومات
// ------------------------------------------------------------------

it('enrols a student and freezes the group price', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $group = seedGroup(['price_minor' => 40000]);
        $student = seedCenterStudent();

        $enrollment = app(EnrolStudent::class)->handle($student, $group);

        expect($enrollment->price_minor)->toBe(40000)
            ->and($enrollment->status)->toBe('active')
            ->and($group->refresh()->enrolled_count)->toBe(1);
    });
});

it('applies the student standing discount without anyone remembering it', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $group = seedGroup(['price_minor' => 40000]);
        $student = seedCenterStudent();

        Discount::create([
            'student_id' => $student->id, 'type' => 'sibling',
            'value_type' => 'percent', 'value' => 25, 'is_active' => true,
        ]);

        $enrollment = app(EnrolStudent::class)->handle($student, $group);

        expect($enrollment->discount_minor)->toBe(10000)
            ->and($enrollment->netPrice()->minor)->toBe(30000);
    });
});

it('refuses to enrol past the group capacity', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $group = seedGroup(['capacity' => 1]);

        app(EnrolStudent::class)->handle(seedCenterStudent('a@example.test'), $group);

        expect(fn () => app(EnrolStudent::class)->handle(seedCenterStudent('b@example.test'), $group->refresh()))
            ->toThrow(RuntimeException::class);
    });
});

it('frees the seat when a student drops', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $group = seedGroup(['capacity' => 1]);
        $enrollment = app(EnrolStudent::class)->handle(seedCenterStudent(), $group);

        app(EnrolStudent::class)->drop($enrollment);

        expect($group->refresh()->enrolled_count)->toBe(0)
            ->and($group->isFull())->toBeFalse();
    });
});

// ------------------------------------------------------------------
// الحضور
// ------------------------------------------------------------------

it('treats everyone as present unless marked otherwise', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $group = seedGroup();
        $present = seedCenterStudent('p@example.test');
        $absent = seedCenterStudent('q@example.test');

        app(EnrolStudent::class)->handle($present, $group);
        app(EnrolStudent::class)->handle($absent, $group->refresh());

        $session = seedSession($group);

        $summary = app(TakeAttendance::class)->handle($session, [$absent->id => 'absent']);

        expect($summary['present'])->toBe(1)
            ->and($summary['absent'])->toBe(1)
            ->and($session->refresh()->attendanceTaken())->toBeTrue();
    });
});

it('marks a student by their card code', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $group = seedGroup();
        $student = seedCenterStudent();
        app(EnrolStudent::class)->handle($student, $group);

        $session = seedSession($group, ['starts_at' => now()->format('H:i:s')]);

        $record = app(TakeAttendance::class)->mark($session, mb_strtolower($student->code), 'qr');

        expect($record->status)->toBe('present')
            ->and($record->method)->toBe('qr');
    });
});

it('refuses a card that belongs to another group', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $group = seedGroup();
        $outsider = seedCenterStudent();
        $session = seedSession($group);

        expect(fn () => app(TakeAttendance::class)->mark($session, $outsider->code))
            ->toThrow(RuntimeException::class);
    });
});

it('records a late arrival as late, not present', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        setting()->set('center.late_threshold_minutes', 10);

        $group = seedGroup();
        $student = seedCenterStudent();
        app(EnrolStudent::class)->handle($student, $group);

        // بدأت الحصة قبل نصف ساعة
        $session = seedSession($group, ['starts_at' => now()->subMinutes(30)->format('H:i:s')]);

        $record = app(TakeAttendance::class)->mark($session, $student->code);

        expect($record->status)->toBe('late')
            ->and($record->minutes_late)->toBeGreaterThanOrEqual(29);
    });
});

it('leaves an excused absence out of the attendance rate', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $group = seedGroup();
        $student = seedCenterStudent();
        app(EnrolStudent::class)->handle($student, $group);

        $first = seedSession($group, ['date' => now()->subDays(3)->toDateString()]);
        $second = seedSession($group, ['date' => now()->subDays(2)->toDateString()]);

        app(TakeAttendance::class)->handle($first, [$student->id => 'present']);
        app(TakeAttendance::class)->handle($second, [$student->id => 'excused']);

        // حصة واحدة محسوبة، حضرها: ١٠٠٪
        expect(app(TakeAttendance::class)->rateFor((int) $student->id, (int) $group->id))->toBe(100.0);
    });
});

// ------------------------------------------------------------------
// المالية
// ------------------------------------------------------------------

it('issues one invoice per student per period, never two', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $group = seedGroup(['price_minor' => 40000]);
        app(EnrolStudent::class)->handle(seedCenterStudent('x@example.test'), $group);
        app(EnrolStudent::class)->handle(seedCenterStudent('y@example.test'), $group->refresh());

        $first = app(IssueInvoices::class)->handle($group, '2026-09');
        $second = app(IssueInvoices::class)->handle($group, '2026-09');

        expect($first['issued'])->toBe(2)
            ->and($second['issued'])->toBe(0)
            ->and($second['skipped'])->toBe(2)
            ->and(Invoice::count())->toBe(2);
    });
});

it('invoices the discounted price, not the list price', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $group = seedGroup(['price_minor' => 40000]);
        $student = seedCenterStudent();

        app(EnrolStudent::class)->handle($student, $group, customDiscountMinor: 15000, reason: 'أخوة');
        app(IssueInvoices::class)->handle($group, '2026-09');

        expect(Invoice::first()->total_minor)->toBe(25000);
    });
});

it('collects a payment with a numbered receipt and moves the cashbox', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $branch = seedBranch();
        $group = seedGroup(['branch' => $branch, 'price_minor' => 40000]);
        $student = seedCenterStudent();
        $box = seedCashbox($branch);

        app(EnrolStudent::class)->handle($student, $group);
        app(IssueInvoices::class)->handle($group, '2026-09');
        $invoice = Invoice::firstOrFail();

        $payment = app(CollectPayment::class)->handle(
            $student, Money::fromMinor(40000, 'EGP'), $invoice, $box, 'cash',
        );

        expect($payment->receipt_no)->toStartWith('R-')
            ->and($invoice->refresh()->status)->toBe('paid')
            ->and($box->refresh()->balance_minor)->toBe(40000);
    });
});

it('marks an invoice partial on a part payment', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $group = seedGroup(['price_minor' => 40000]);
        $student = seedCenterStudent();

        app(EnrolStudent::class)->handle($student, $group);
        app(IssueInvoices::class)->handle($group, '2026-09');
        $invoice = Invoice::firstOrFail();

        app(CollectPayment::class)->handle($student, Money::fromMinor(15000, 'EGP'), $invoice);

        expect($invoice->refresh()->status)->toBe('partial')
            ->and($invoice->remaining()->minor)->toBe(25000);
    });
});

it('keeps transfers out of the cash drawer', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $branch = seedBranch();
        $student = seedCenterStudent();
        $box = seedCashbox($branch);

        app(CollectPayment::class)->handle(
            $student, Money::fromMinor(20000, 'EGP'), null, $box, 'transfer',
        );

        expect($box->refresh()->balance_minor)->toBe(0);
    });
});

it('demands an explanation for a cash difference', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $box = seedCashbox();
        $student = seedCenterStudent();

        app(CollectPayment::class)->handle($student, Money::fromMinor(50000, 'EGP'), null, $box, 'cash');

        expect(fn () => app(CloseCashbox::class)->handle($box->refresh(), Money::fromMinor(45000, 'EGP')))
            ->toThrow(RuntimeException::class);

        $closing = app(CloseCashbox::class)->handle(
            $box->refresh(), Money::fromMinor(45000, 'EGP'), null, 'نقص في العدّ — يُراجع غداً',
        );

        expect($closing->difference_minor)->toBe(-5000)
            ->and($closing->isBalanced())->toBeFalse();
    });
});

it('closes a balanced drawer without asking for anything', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $box = seedCashbox();
        $student = seedCenterStudent();

        app(CollectPayment::class)->handle($student, Money::fromMinor(30000, 'EGP'), null, $box, 'cash');

        expect(app(CloseCashbox::class)->handle($box->refresh(), Money::fromMinor(30000, 'EGP'))->isBalanced())
            ->toBeTrue();
    });
});

// ------------------------------------------------------------------
// التقرير الشهري
// ------------------------------------------------------------------

it('gathers attendance, marks and dues into one monthly report', function () {
    provision(['platform_mode' => 'center'])->run(function (): void {
        $group = seedGroup(['price_minor' => 40000]);
        $student = seedCenterStudent();
        app(EnrolStudent::class)->handle($student, $group);

        $session = seedSession($group, ['date' => now()->startOfMonth()->addDays(2)->toDateString()]);
        app(TakeAttendance::class)->handle($session, [$student->id => 'present']);

        app(IssueInvoices::class)->handle($group, now()->format('Y-m'));

        $report = app(MonthlyReport::class)->handle($student, now()->format('Y-m'));

        expect($report['groups'])->toHaveCount(1)
            ->and($report['groups'][0]['present'])->toBe(1)
            ->and($report['finance']['outstanding']->minor)->toBe(40000)
            ->and($report['overall_attendance'])->toBe(100.0);
    });
});
