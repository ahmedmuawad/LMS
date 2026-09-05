<?php

declare(strict_types=1);

use App\Modules\Center\Actions\RecordMeetingAttendance;
use App\Modules\Center\Actions\SelfCheckIn;
use App\Modules\Center\Models\Attendance;
use App\Modules\Center\Models\CenterEnrollment;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;

/*
| التسجيل الذاتي بالكود المتغيّر، والحضور من الاجتماع.
|
| والاختبار هنا لأن الخطأ يُنتج سجلّاً يكذب: كودٌ لا ينتهي يُسجّل
| غائباً حاضراً، وسجلُّ حضورٍ كاذب تُبنى عليه إنذاراتٌ لأولياء
| الأمور وخصوماتٌ من رواتب.
*/

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);

    tenancy()->initialize(provision());

    $this->group = seedGroup();
    $this->session = seedSession($this->group, ['starts_at' => now()->format('H:i:s')]);
    $this->student = seedCenterStudent();

    CenterEnrollment::create([
        'group_id' => $this->group->id,
        'student_id' => $this->student->id,
        'status' => 'active',
        'started_at' => now()->toDateString(),
    ]);
});

it('marks the student present from a fresh code', function () {
    $checkIn = app(SelfCheckIn::class);

    $result = $checkIn->handle($checkIn->token($this->session), $this->student->user);

    expect($result['status'])->toBe('present')
        ->and(Attendance::where('session_id', $this->session->id)->first())
        ->method->toBe('self');
});

it('rejects a code from an earlier slot so a screenshot cannot travel', function () {
    $checkIn = app(SelfCheckIn::class);

    // كودٌ وُلد قبل دقيقتين — أي ستّ شرائح مضت
    $old = $checkIn->token($this->session, now()->subMinutes(2));

    expect(fn () => $checkIn->handle($old, $this->student->user))
        ->toThrow(RuntimeException::class);
});

it('still accepts the previous slot so a slow scan is not punished', function () {
    $checkIn = app(SelfCheckIn::class);

    $token = $checkIn->token($this->session, now()->subSeconds(SelfCheckIn::SLOT));

    expect($checkIn->handle($token, $this->student->user)['status'])->toBe('present');
});

it('refuses a tampered signature', function () {
    $checkIn = app(SelfCheckIn::class);

    $token = $checkIn->token($this->session);
    $forged = substr($token, 0, -1).(str_ends_with($token, 'a') ? 'b' : 'a');

    expect(fn () => $checkIn->handle($forged, $this->student->user))
        ->toThrow(RuntimeException::class);
});

it('refuses a student who is not in the group', function () {
    $outsider = seedCenterStudent('outsider@example.test');

    $checkIn = app(SelfCheckIn::class);

    expect(fn () => $checkIn->handle($checkIn->token($this->session), $outsider->user))
        ->toThrow(RuntimeException::class);
});

it('marks late when the student scans after the grace window', function () {
    $checkIn = app(SelfCheckIn::class);

    $late = now()->addMinutes(25);

    $result = $checkIn->handle($checkIn->token($this->session, $late), $this->student->user, $late);

    expect($result['status'])->toBe('late');
});

it('never overrides what the teacher recorded by hand', function () {
    Attendance::create([
        'session_id' => $this->session->id,
        'student_id' => $this->student->id,
        'status' => 'excused',
        'method' => 'manual',
    ]);

    $checkIn = app(SelfCheckIn::class);
    $checkIn->handle($checkIn->token($this->session), $this->student->user);

    expect(Attendance::where('session_id', $this->session->id)->first())
        ->status->toBe('excused')->method->toBe('manual');
});

it('records an online attendance when the student joins the meeting', function () {
    $status = app(RecordMeetingAttendance::class)->handle($this->session, $this->student->user);

    expect($status)->toBe('online')
        ->and(Attendance::where('session_id', $this->session->id)->first())
        ->method->toBe('meeting');
});

it('records nothing for someone who is not a student of the group', function () {
    $outsider = seedCenterStudent('teacher-ish@example.test');

    expect(app(RecordMeetingAttendance::class)->handle($this->session, $outsider->user))->toBeNull();
});
