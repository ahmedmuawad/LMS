<?php

declare(strict_types=1);

use App\Modules\Lms\Actions\EnrollStudent;
use App\Modules\Lms\Actions\GradeAssignment;
use App\Modules\Lms\Actions\GradeQuizAttempt;
use App\Modules\Lms\Actions\StartQuizAttempt;
use App\Modules\Lms\Actions\SubmitAssignment;
use App\Modules\Lms\Models\Assignment;
use App\Modules\Lms\Models\AssignmentSubmission;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseItem;
use App\Modules\Lms\Models\Lesson;
use App\Modules\Lms\Models\QuizAttempt;
use App\Modules\Lms\VideoUrl;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

function seedAssignment(Course $course, array $overrides = []): Assignment
{
    $assignment = Assignment::create([
        'title' => ['ar' => 'واجب الوحدة الأولى'],
        'instructions' => ['ar' => 'اكتب تطبيقاً صغيراً واشرح خطواتك.'],
        'max_marks' => 20,
        'passing_marks' => 10,
        'due_days' => 7,
        'allow_late' => true,
        'late_penalty_percent' => 25,
        'max_resubmissions' => 1,
        ...$overrides,
    ]);

    CourseItem::create([
        'course_id' => $course->id,
        'itemable_type' => Assignment::class,
        'itemable_id' => $assignment->id,
        'position' => 50,
    ]);

    return $assignment;
}

it('accepts a submission and marks it pending', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $assignment = seedAssignment($course);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);

        $submission = app(SubmitAssignment::class)->handle($enrollment, $assignment, 'إجابتي المفصّلة');

        expect($submission->status)->toBe('pending')
            ->and($submission->is_late)->toBeFalse()
            ->and($submission->attempt_no)->toBe(1);
    });
});

it('refuses an empty submission', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $assignment = seedAssignment($course);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);

        expect(fn () => app(SubmitAssignment::class)->handle($enrollment, $assignment, null, []))
            ->toThrow(RuntimeException::class);
    });
});

it('flags a late submission instead of silently accepting it', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $assignment = seedAssignment($course);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);
        $enrollment->forceFill(['started_at' => now()->subDays(30)])->save();

        expect(app(SubmitAssignment::class)->handle($enrollment->refresh(), $assignment, 'متأخر')->is_late)
            ->toBeTrue();
    });
});

it('turns a late submission away when the assignment forbids it', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $assignment = seedAssignment($course, ['allow_late' => false]);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);
        $enrollment->forceFill(['started_at' => now()->subDays(30)])->save();

        expect(fn () => app(SubmitAssignment::class)->handle($enrollment->refresh(), $assignment, 'متأخر'))
            ->toThrow(RuntimeException::class);
    });
});

it('docks the late penalty from what the student actually earned', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $assignment = seedAssignment($course);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);
        $enrollment->forceFill(['started_at' => now()->subDays(30)])->save();

        $submission = app(SubmitAssignment::class)->handle($enrollment->refresh(), $assignment, 'متأخر');
        $graded = app(GradeAssignment::class)->handle($submission, 16.0);

        // ١٦ ناقص ٢٥٪ = ١٢
        expect((float) $graded->marks)->toBe(12.0)
            ->and($graded->passed())->toBeTrue();
    });
});

it('refuses a mark above the assignment maximum', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $assignment = seedAssignment($course);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);
        $submission = app(SubmitAssignment::class)->handle($enrollment, $assignment, 'إجابة');

        expect(fn () => app(GradeAssignment::class)->handle($submission, 999))
            ->toThrow(InvalidArgumentException::class);
    });
});

it('completes the curriculum item only when the assignment passes', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $assignment = seedAssignment($course);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);
        $item = $assignment->items()->first();

        $failing = app(SubmitAssignment::class)->handle($enrollment, $assignment, 'إجابة ناقصة');
        app(GradeAssignment::class)->handle($failing, 4.0);

        expect($enrollment->progress()->where('item_id', $item->id)->where('status', 'completed')->exists())
            ->toBeFalse();
    });
});

it('lets the student resubmit once the instructor asks', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $assignment = seedAssignment($course);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);

        $first = app(SubmitAssignment::class)->handle($enrollment, $assignment, 'محاولة أولى');
        app(GradeAssignment::class)->requestResubmission($first, ['ar' => 'أعد الجزء الثاني']);

        $second = app(SubmitAssignment::class)->handle($enrollment, $assignment, 'محاولة ثانية');

        expect($second->attempt_no)->toBe(2)
            ->and(AssignmentSubmission::count())->toBe(2);
    });
});

it('stops resubmissions once they run out', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $assignment = seedAssignment($course, ['max_resubmissions' => 0]);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);

        app(SubmitAssignment::class)->handle($enrollment, $assignment, 'الأولى');

        expect(fn () => app(SubmitAssignment::class)->handle($enrollment, $assignment, 'الثانية'))
            ->toThrow(RuntimeException::class);
    });
});

// ------------------------------------------------------------------
// شاشات التصحيح
// ------------------------------------------------------------------

it('gathers everything awaiting a human on one table', function () {
    $tenant = provision();

    $tenant->run(function (): void {
        $course = seedCourse();
        $quiz = seedQuiz($course);
        $assignment = seedAssignment($course);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);

        $attempt = app(StartQuizAttempt::class)->handle($enrollment, $quiz);
        $ids = collect($attempt->snapshot)->pluck('id');
        app(GradeQuizAttempt::class)->handle($attempt, [$ids[2] => 'مقال طويل']);

        app(SubmitAssignment::class)->handle($enrollment, $assignment, 'إجابتي');
    });

    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/grading')
        ->assertOk()
        ->assertSee('اختبار الوحدة الأولى')
        ->assertSee('واجب الوحدة الأولى');
});

it('marks an essay from the grading screen and closes the attempt', function () {
    $tenant = provision();

    [$attemptId, $answerId] = $tenant->run(function (): array {
        $course = seedCourse();
        $quiz = seedQuiz($course);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);
        $attempt = app(StartQuizAttempt::class)->handle($enrollment, $quiz);
        $ids = collect($attempt->snapshot)->pluck('id');

        app(GradeQuizAttempt::class)->handle($attempt, [$ids[0] => 'a', $ids[1] => ['a', 'b'], $ids[2] => 'شرح']);

        return [$attempt->id, $attempt->refresh()->answers()->whereNull('is_correct')->firstOrFail()->id];
    });

    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/grading/attempts/'.$attemptId)->assertOk();

    tenantPut($tenant, '/admin/grading/attempts/'.$attemptId.'/answers/'.$answerId, [
        'marks' => 6,
        'note' => 'إجابة وافية.',
    ])->assertRedirect();

    $tenant->run(function () use ($attemptId): void {
        $attempt = QuizAttempt::find($attemptId);

        expect($attempt->status)->toBe('graded')
            ->and($attempt->passed)->toBeTrue();
    });
});

it('refuses a mark above the question maximum', function () {
    $tenant = provision();

    [$attemptId, $answerId] = $tenant->run(function (): array {
        $course = seedCourse();
        $quiz = seedQuiz($course);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);
        $attempt = app(StartQuizAttempt::class)->handle($enrollment, $quiz);
        $ids = collect($attempt->snapshot)->pluck('id');

        app(GradeQuizAttempt::class)->handle($attempt, [$ids[2] => 'شرح']);

        return [$attempt->id, $attempt->refresh()->answers()->whereNull('is_correct')->firstOrFail()->id];
    });

    actingAsOwner($tenant);

    tenantPut($tenant, '/admin/grading/attempts/'.$attemptId.'/answers/'.$answerId, ['marks' => 999])
        ->assertSessionHasErrors('marks');
});

it('grades an assignment from the grading screen', function () {
    $tenant = provision();

    $submissionId = $tenant->run(function (): int {
        $course = seedCourse();
        $assignment = seedAssignment($course);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);

        return app(SubmitAssignment::class)->handle($enrollment, $assignment, 'إجابتي')->id;
    });

    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/grading/submissions/'.$submissionId)->assertOk();

    tenantPut($tenant, '/admin/grading/submissions/'.$submissionId, [
        'action' => 'grade',
        'marks' => 18,
        'feedback' => 'ممتاز، انتبه للتنسيق.',
    ])->assertRedirect();

    $tenant->run(fn () => expect(AssignmentSubmission::find($submissionId)->status)->toBe('graded'));
});

// ------------------------------------------------------------------
// رابط الفيديو
// ------------------------------------------------------------------

it('signs the bunny url so a copied link dies quickly', function () {
    provision()->run(function (): void {
        setting()->set('integrations.video_pull_zone', 'vz.b-cdn.net');
        setting()->set('integrations.video_token_key', 'super-secret');

        $lesson = Lesson::create([
            'title' => ['ar' => 'درس محمي'], 'type' => 'video',
            'video_provider' => 'bunny', 'video_id' => 'abc-123',
        ]);

        $url = app(VideoUrl::class)->for($lesson, 42);

        expect($url)->toContain('vz.b-cdn.net/abc-123/playlist.m3u8')
            ->toContain('token=')
            ->toContain('expires=')
            ->toContain('user=42')
            ->not->toContain('super-secret');
    });
});

it('gives no url at all when the video keys are missing', function () {
    provision()->run(function (): void {
        $lesson = Lesson::create([
            'title' => ['ar' => 'درس'], 'type' => 'video',
            'video_provider' => 'bunny', 'video_id' => 'abc-123',
        ]);

        expect(app(VideoUrl::class)->for($lesson, 1))->toBeNull();
    });
});

it('gives a different signature to each student', function () {
    provision()->run(function (): void {
        setting()->set('integrations.video_pull_zone', 'vz.b-cdn.net');
        setting()->set('integrations.video_token_key', 'super-secret');

        $lesson = Lesson::create([
            'title' => ['ar' => 'درس'], 'type' => 'video',
            'video_provider' => 'bunny', 'video_id' => 'abc-123',
        ]);

        expect(app(VideoUrl::class)->for($lesson, 1))
            ->not->toBe(app(VideoUrl::class)->for($lesson, 2));
    });
});
