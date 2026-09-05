<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Lms\Actions\AnswerReviewItem;
use App\Modules\Lms\Actions\CollectForReview;
use App\Modules\Lms\Actions\EnrollStudent;
use App\Modules\Lms\Actions\GradeQuizAttempt;
use App\Modules\Lms\Actions\StartQuizAttempt;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\Question;
use App\Modules\Lms\Models\QuizAttempt;
use App\Modules\Lms\Models\ReviewItem;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

/**
 * يبني طالباً سلّم اختباراً بإجابات خاطئة، ويعيد [الطالب، الأسئلة].
 *
 * @return array{0:User, 1:Collection<int, Question>}
 */
function seedWrongAttempt(bool $correctly = false): array
{
    $course = seedCourse();
    $quiz = seedQuiz($course);
    $student = seedStudent();
    $enrollment = app(EnrollStudent::class)->handle($student, $course);

    $attempt = app(StartQuizAttempt::class)->handle($enrollment, $quiz);

    $answers = [];

    foreach (collect($attempt->snapshot ?? []) as $frozen) {
        $question = Question::find($frozen['id']);

        if ($question === null) {
            continue;
        }

        /*
         | «الاختيار المتعدّد» يُصحَّح بالمجموعة كاملةً لا بعنصرٍ
         | منها — فإجابةٌ بأوّل الصحيح خطأٌ لا صواب.
         */
        $answers[$question->id] = $correctly
            ? ($question->type === 'multiple_choice' ? $question->correct : ($question->correct[0] ?? 'x'))
            : 'إجابة خاطئة عمداً';
    }

    app(GradeQuizAttempt::class)->handle($attempt, $answers);

    return [$student, Question::whereIn('id', array_keys($answers))->get()];
}

it('files wrong answers for review and leaves correct ones out', function () {
    $tenant = provision();

    $tenant->run(function (): void {
        [$student] = seedWrongAttempt();

        $items = ReviewItem::where('user_id', $student->id)->get();

        /*
         | المقالي لا يدخل: يبقى `is_correct = null` حتى يقرأه
         | المدرّس، وإدخاله الآن مراجعةٌ لخطأٍ لم يثبت.
         */
        $manual = Question::whereIn('type', Question::MANUAL_TYPES)->count();
        $auto = Question::count() - $manual;

        expect($items)->toHaveCount($auto)
            ->and($items->every(fn (ReviewItem $i): bool => $i->wrong_count === 1))->toBeTrue()
            ->and($items->every(fn (ReviewItem $i): bool => $i->mastered_at === null))->toBeTrue();
    });
});

it('does not file anything when the student answers correctly', function () {
    $tenant = provision();

    $tenant->run(function (): void {
        [$student] = seedWrongAttempt(correctly: true);

        expect(ReviewItem::where('user_id', $student->id)->count())->toBe(0);
    });
});

it('masters an item after two correct answers and resets the streak on a wrong one', function () {
    $tenant = provision();

    $tenant->run(function (): void {
        [$student] = seedWrongAttempt();

        $item = ReviewItem::where('user_id', $student->id)
            ->whereHas('question', fn ($q) => $q->where('type', 'single_choice'))
            ->firstOrFail();

        $right = $item->question->correct[0];
        $action = app(AnswerReviewItem::class);

        $action->handle($item, $right);

        expect($item->fresh()->streak)->toBe(1)
            ->and($item->fresh()->mastered_at)->toBeNull();

        // خطأ في المنتصف يُصفّر السلسلة ولا يُنقصها
        $action->handle($item->fresh(), 'لا شيء');

        expect($item->fresh()->streak)->toBe(0)
            ->and($item->fresh()->wrong_count)->toBe(2);

        $action->handle($item->fresh(), $right);
        $action->handle($item->fresh(), $right);

        expect($item->fresh()->streak)->toBe(2)
            ->and($item->fresh()->mastered_at)->not->toBeNull();
    });
});

it('reopens a mastered item when the student gets it wrong again', function () {
    $tenant = provision();

    $tenant->run(function (): void {
        [$student] = seedWrongAttempt();

        ReviewItem::where('user_id', $student->id)->update([
            'mastered_at' => now(), 'streak' => 2,
        ]);

        expect(ReviewItem::where('user_id', $student->id)->pending()->count())->toBe(0);

        /*
         | خطأٌ ثانٍ في السؤال نفسه يفتحه من جديد.
         |
         | الإتقان ليس شهادةً دائمة: من نسي بعد شهر يُخطئ، وتركُه
         | مُتقَناً في السجلّ ومنسيّاً في رأسه أسوأ من إعادته.
         */
        $attempt = QuizAttempt::where('enrollment_id',
            Enrollment::where('user_id', $student->id)->value('id'))
            ->latest('id')->firstOrFail();

        app(CollectForReview::class)->afterAttempt($attempt);

        $reopened = ReviewItem::where('user_id', $student->id)->pending()->get();

        expect($reopened)->not->toBeEmpty()
            ->and($reopened->every(fn (ReviewItem $i): bool => $i->wrong_count >= 2))->toBeTrue();
    });
});

it('shows the student their review screen with the counts', function () {
    $tenant = provision();

    $student = $tenant->run(function () {
        [$student] = seedWrongAttempt();

        return $student;
    });

    tenancy()->initialize($tenant);
    $this->actingAs($student);

    tenantGet($tenant, '/my-review')
        ->assertOk()
        ->assertSee('مراجعتي')
        ->assertSee('بانتظار المراجعة');

    tenantGet($tenant, '/my-review/next')->assertOk();
});

it('refuses to let a student answer another student’s item', function () {
    $tenant = provision();

    [$itemId, $stranger] = $tenant->run(function (): array {
        [$student] = seedWrongAttempt();

        $item = ReviewItem::where('user_id', $student->id)->firstOrFail();

        return [$item->id, seedStudent('stranger@example.test')];
    });

    tenancy()->initialize($tenant);
    $this->actingAs($stranger);

    tenantPost($tenant, '/my-review/'.$itemId.'/answer', ['answer' => 'x'])
        ->assertNotFound();
});
