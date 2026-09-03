<?php

declare(strict_types=1);

use App\Modules\Lms\Actions\EnrollStudent;
use App\Modules\Lms\Actions\GradeQuizAttempt;
use App\Modules\Lms\Actions\StartQuizAttempt;
use App\Modules\Lms\Actions\TrackProgress;
use App\Modules\Lms\Models\Certificate;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\Question;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

// ------------------------------------------------------------------
// النماذج والترجمة
// ------------------------------------------------------------------

it('serves a translated field in the display language', function () {
    provision()->run(function (): void {
        $course = seedCourse();

        app()->setLocale('ar');
        expect($course->title)->toBe('أساسيات لارافيل');

        app()->setLocale('en');
        expect($course->fresh()->title)->toBe('Laravel Basics');
    });
});

it('falls back to the default language rather than showing nothing', function () {
    provision()->run(function (): void {
        $course = seedCourse();

        app()->setLocale('en');
        // النبذة مكتوبة بالعربية وحدها
        expect($course->excerpt)->toBe('من الصفر إلى أول تطبيق');
    });
});

// ------------------------------------------------------------------
// التسجيل
// ------------------------------------------------------------------

it('enrolls a student once, no matter how many times they ask', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $student = seedStudent();

        app(EnrollStudent::class)->handle($student, $course, 'free');
        app(EnrollStudent::class)->handle($student, $course, 'free');

        expect(Enrollment::where('user_id', $student->id)->count())->toBe(1)
            ->and(Course::find($course->id)->students_count)->toBe(1);
    });
});

it('sets an access deadline only when the course has one', function () {
    provision()->run(function (): void {
        $lifetime = seedCourse(['access_days' => 0]);
        $limited = seedCourse(['access_days' => 30]);
        $student = seedStudent();

        expect(app(EnrollStudent::class)->handle($student, $lifetime)->expires_at)->toBeNull()
            ->and(app(EnrollStudent::class)->handle($student, $limited)->expires_at)->not->toBeNull();
    });
});

it('keeps a finished student record after access expires', function () {
    provision()->run(function (): void {
        $course = seedCourse(['access_days' => 30]);
        $student = seedStudent();

        $enrollment = app(EnrollStudent::class)->handle($student, $course);
        $enrollment->forceFill(['expires_at' => now()->subDay(), 'progress_percent' => 100])->save();

        expect($enrollment->hasAccess())->toBeFalse()
            ->and($enrollment->isExpired())->toBeTrue()
            ->and($enrollment->progress_percent)->toBe(100);
    });
});

it('refuses to enrol beyond the seat limit', function () {
    provision()->run(function (): void {
        $course = seedCourse(['max_students' => 1]);

        app(EnrollStudent::class)->handle(seedStudent('a@example.test'), $course, 'free');

        expect(fn () => app(EnrollStudent::class)->handle(seedStudent('b@example.test'), $course->refresh(), 'free'))
            ->toThrow(RuntimeException::class);
    });
});

// ------------------------------------------------------------------
// التقدّم
// ------------------------------------------------------------------

it('counts progress across every curriculum item, not lessons alone', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);
        $items = $course->items()->get();

        app(TrackProgress::class)->complete($enrollment, $items[0]);

        expect($enrollment->refresh()->progress_percent)->toBe(33);
    });
});

it('marks the enrollment complete and issues the certificate at the end', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);

        foreach ($course->items()->get() as $item) {
            app(TrackProgress::class)->complete($enrollment, $item);
        }

        $enrollment->refresh();

        expect($enrollment->progress_percent)->toBe(100)
            ->and($enrollment->status)->toBe('completed')
            ->and(Certificate::where('enrollment_id', $enrollment->id)->count())->toBe(1);
    });
});

it('issues one certificate however often completion is recalculated', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);

        foreach ($course->items()->get() as $item) {
            app(TrackProgress::class)->complete($enrollment, $item);
        }

        app(TrackProgress::class)->recalculate($enrollment->refresh());
        app(TrackProgress::class)->recalculate($enrollment->refresh());

        expect(Certificate::count())->toBe(1);
    });
});

it('never lets a rewind erase what was already watched', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);
        $item = $course->items()->first();

        app(TrackProgress::class)->watch($enrollment, $item, 300, 300);
        $progress = app(TrackProgress::class)->watch($enrollment, $item, 10, 5);

        expect($progress->watched_seconds)->toBe(300)
            ->and($progress->last_position_seconds)->toBe(10);
    });
});

// ------------------------------------------------------------------
// الاختبارات
// ------------------------------------------------------------------

it('freezes the questions into the attempt', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $quiz = seedQuiz($course);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);

        $attempt = app(StartQuizAttempt::class)->handle($enrollment, $quiz);

        expect($attempt->snapshot)->toHaveCount(3)
            ->and((float) $attempt->max_score)->toBe(10.0);
    });
});

it('keeps an old attempt readable after the question is edited', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $quiz = seedQuiz($course);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);

        $attempt = app(StartQuizAttempt::class)->handle($enrollment, $quiz);
        $frozen = $attempt->snapshot[0]['body']['ar'];

        Question::find($attempt->snapshot[0]['id'])->update(['body' => ['ar' => 'سؤال مختلف تماماً']]);

        expect($attempt->fresh()->snapshot[0]['body']['ar'])->toBe($frozen);
    });
});

it('resumes an open attempt instead of starting a second one', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $quiz = seedQuiz($course);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);

        $first = app(StartQuizAttempt::class)->handle($enrollment, $quiz);
        $second = app(StartQuizAttempt::class)->handle($enrollment, $quiz);

        expect($second->id)->toBe($first->id);
    });
});

it('stops the student once attempts run out', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $quiz = seedQuiz($course, ['max_attempts' => 1]);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);

        $attempt = app(StartQuizAttempt::class)->handle($enrollment, $quiz);
        app(GradeQuizAttempt::class)->handle($attempt, []);

        expect(fn () => app(StartQuizAttempt::class)->handle($enrollment, $quiz))
            ->toThrow(RuntimeException::class);
    });
});

it('grades the machine-markable answers and leaves the essay pending', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $quiz = seedQuiz($course);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);
        $attempt = app(StartQuizAttempt::class)->handle($enrollment, $quiz);

        $ids = collect($attempt->snapshot)->pluck('id');

        $graded = app(GradeQuizAttempt::class)->handle($attempt, [
            $ids[0] => 'a',
            $ids[1] => ['a', 'b'],
            $ids[2] => 'الوسيط يعترض الطلب قبل وصوله للمتحكّم.',
        ]);

        expect((float) $graded->score)->toBe(4.0)
            ->and($graded->status)->toBe('submitted')
            ->and($graded->awaitsGrading())->toBeTrue()
            ->and($graded->passed)->toBeFalse();
    });
});

it('never gives a zero to an essay nobody has read', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $quiz = seedQuiz($course);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);
        $attempt = app(StartQuizAttempt::class)->handle($enrollment, $quiz);
        $ids = collect($attempt->snapshot)->pluck('id');

        $graded = app(GradeQuizAttempt::class)->handle($attempt, [$ids[2] => 'إجابة مطوّلة']);

        expect($graded->answers()->where('question_id', $ids[2])->first()->is_correct)->toBeNull()
            ->and($graded->status)->toBe('submitted');
    });
});

it('closes the attempt and passes the student once the essay is marked', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $quiz = seedQuiz($course);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);
        $attempt = app(StartQuizAttempt::class)->handle($enrollment, $quiz);
        $ids = collect($attempt->snapshot)->pluck('id');

        app(GradeQuizAttempt::class)->handle($attempt, [$ids[0] => 'a', $ids[1] => ['a', 'b'], $ids[2] => 'شرح']);

        $essayAnswer = $attempt->refresh()->answers()->where('question_id', $ids[2])->firstOrFail();
        $final = app(GradeQuizAttempt::class)->gradeAnswer($essayAnswer, 6.0);

        expect((float) $final->score)->toBe(10.0)
            ->and($final->status)->toBe('graded')
            ->and($final->passed)->toBeTrue();
    });
});

it('requires the whole set for a multiple choice answer', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $quiz = seedQuiz($course);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);
        $attempt = app(StartQuizAttempt::class)->handle($enrollment, $quiz);
        $ids = collect($attempt->snapshot)->pluck('id');

        $graded = app(GradeQuizAttempt::class)->handle($attempt, [$ids[1] => ['a']]);

        expect($graded->answers()->where('question_id', $ids[1])->first()->is_correct)->toBeFalse();
    });
});

it('applies negative marking without dropping the score below zero', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $quiz = seedQuiz($course, ['negative_marking' => true]);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);
        $attempt = app(StartQuizAttempt::class)->handle($enrollment, $quiz);
        $ids = collect($attempt->snapshot)->pluck('id');

        $graded = app(GradeQuizAttempt::class)->handle($attempt, [$ids[0] => 'b']);

        expect((float) $graded->score)->toBe(0.0);
    });
});

it('accepts an Arabic answer written without diacritics or hamza', function () {
    provision()->run(function (): void {
        $question = Question::create([
            'body' => ['ar' => 'من كتب «الأيام»؟'],
            'type' => 'short_text',
            'correct' => ['طه حسين'],
            'marks' => 1,
        ]);

        expect($question->grade('طَهَ حُسَين'))->toBeTrue()
            ->and($question->grade('طه   حسين '))->toBeTrue()
            ->and($question->grade('نجيب محفوظ'))->toBeFalse();
    });
});

it('completes the curriculum item only when the quiz is passed', function () {
    provision()->run(function (): void {
        $course = seedCourse();
        $quiz = seedQuiz($course, ['passing_percentage' => 40]);
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);
        $attempt = app(StartQuizAttempt::class)->handle($enrollment, $quiz);
        $ids = collect($attempt->snapshot)->pluck('id');

        // إجابة خاطئة على كل شيء: العنصر يبقى غير مكتمل
        app(GradeQuizAttempt::class)->handle($attempt, [$ids[0] => 'b', $ids[1] => ['c']]);

        $item = $quiz->items()->first();

        expect($enrollment->refresh()->progress()->where('item_id', $item->id)->where('status', 'completed')->exists())
            ->toBeFalse();
    });
});
