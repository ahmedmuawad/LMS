<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Actions\PostDiscussion;
use App\Modules\Community\Actions\SubmitReview;
use App\Modules\Community\Models\Discussion;
use App\Modules\Community\Models\DiscussionReply;
use App\Modules\Gamification\Actions\AwardBadges;
use App\Modules\Gamification\Actions\AwardPoints;
use App\Modules\Gamification\Models\Badge;
use App\Modules\Gamification\Models\LearningStreak;
use App\Modules\Gamification\Models\PointEntry;
use App\Modules\Lms\Actions\EnrollStudent;
use App\Modules\Lms\Actions\TrackProgress;
use App\Modules\Lms\Models\CourseReview;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

/*
 | المجتمع: الأسئلة والردود وقبول الإجابة والتصويت.
 */

it('يمنع غير المسجّل من طرح سؤال في الكورس', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $course = seedCourse();
        $outsider = seedStudent('outsider@t.test');

        expect(fn () => app(PostDiscussion::class)->ask($outsider, $course, [
            'title' => 'سؤال من الخارج', 'body' => 'هل أستطيع السؤال بلا تسجيل؟',
        ]))->toThrow(RuntimeException::class);
    });
});

it('يسمح للمسجّل بالسؤال ويُخبر المدرّس', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $course = seedCourse();
        $student = seedStudent();
        app(EnrollStudent::class)->handle($student, $course, 'free');

        $discussion = app(PostDiscussion::class)->ask($student, $course, [
            'title' => 'سؤال عن الدرس الثاني', 'body' => 'لم أفهم الجزء الأخير من الشرح.',
        ]);

        expect($discussion->status)->toBe('open')
            ->and($discussion->type)->toBe('question');
    });
});

it('يقبل الإجابة ويقفل السؤال ويمنح صاحبها نقاطاً', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $course = seedCourse();
        $student = seedStudent();
        $helper = seedStudent('helper@t.test');

        app(EnrollStudent::class)->handle($student, $course, 'free');
        app(EnrollStudent::class)->handle($helper, $course, 'free');

        $post = app(PostDiscussion::class);

        $discussion = $post->ask($student, $course, ['title' => 'سؤالي هنا', 'body' => 'تفاصيل السؤال كاملة.']);
        $reply = $post->reply($helper, $discussion, ['body' => 'الجواب هو كذا وكذا.']);

        $post->accept($student, $reply);

        expect($discussion->refresh()->status)->toBe('answered')
            ->and($reply->refresh()->is_answer)->toBeTrue()
            ->and(PointEntry::where('user_id', $helper->getKey())->where('rule', 'answer.accepted')->exists())->toBeTrue();
    });
});

it('لا يقبل الإجابة إلا صاحب السؤال أو المدرّس', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $course = seedCourse();
        $student = seedStudent();
        $stranger = seedStudent('stranger@t.test');

        app(EnrollStudent::class)->handle($student, $course, 'free');
        app(EnrollStudent::class)->handle($stranger, $course, 'free');

        $post = app(PostDiscussion::class);
        $discussion = $post->ask($student, $course, ['title' => 'سؤالي هنا', 'body' => 'تفاصيل السؤال كاملة.']);
        $reply = $post->reply($stranger, $discussion, ['body' => 'جواب.']);

        expect(fn () => $post->accept($stranger, $reply))->toThrow(RuntimeException::class);
    });
});

it('لا تُقبل إلا إجابة واحدة: القبول الجديد يرفع القديم', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $course = seedCourse();
        $student = seedStudent();
        $a = seedStudent('a@t.test');
        $b = seedStudent('b@t.test');

        foreach ([$student, $a, $b] as $user) {
            app(EnrollStudent::class)->handle($user, $course, 'free');
        }

        $post = app(PostDiscussion::class);
        $discussion = $post->ask($student, $course, ['title' => 'سؤالي هنا', 'body' => 'تفاصيل السؤال كاملة.']);

        $first = $post->reply($a, $discussion, ['body' => 'جواب أول.']);
        $second = $post->reply($b, $discussion, ['body' => 'جواب ثانٍ أدقّ.']);

        $post->accept($student, $first);
        $post->accept($student, $second);

        expect($first->refresh()->is_answer)->toBeFalse()
            ->and($second->refresh()->is_answer)->toBeTrue()
            ->and(DiscussionReply::where('is_answer', true)->count())->toBe(1);
    });
});

it('يصوّت مرة واحدة والضغط ثانيةً يسحب الصوت', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $course = seedCourse();
        $student = seedStudent();
        app(EnrollStudent::class)->handle($student, $course, 'free');

        $post = app(PostDiscussion::class);
        $discussion = $post->ask($student, $course, ['title' => 'سؤالي هنا', 'body' => 'تفاصيل السؤال كاملة.']);

        expect($post->vote($student, $discussion))->toBe(1)
            ->and($post->vote($student, $discussion))->toBe(0)
            ->and($post->vote($student, $discussion))->toBe(1);
    });
});

it('يمنع الردّ على نقاش مغلق', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $course = seedCourse();
        $student = seedStudent();
        app(EnrollStudent::class)->handle($student, $course, 'free');

        $post = app(PostDiscussion::class);
        $discussion = $post->ask($student, $course, ['title' => 'سؤالي هنا', 'body' => 'تفاصيل السؤال كاملة.']);
        $discussion->forceFill(['status' => 'closed'])->save();

        expect(fn () => $post->reply($student, $discussion, ['body' => 'ردّ متأخر.']))
            ->toThrow(RuntimeException::class);
    });
});

/*
 | التقييمات.
 */

it('يرفض تقييم من لم يسجّل في الكورس', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $course = seedCourse();
        $outsider = seedStudent('outsider@t.test');

        expect(fn () => app(SubmitReview::class)->forCourse($outsider, $course, ['rating' => 5]))
            ->toThrow(RuntimeException::class);
    });
});

it('يرفض التقييم المسجَّل يدوياً حين تُشترط الشراء', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('community.who_can_review', 'purchased');

        $course = seedCourse();
        $student = seedStudent();
        app(EnrollStudent::class)->handle($student, $course, 'manual');

        expect(fn () => app(SubmitReview::class)->forCourse($student, $course, ['rating' => 5]))
            ->toThrow(RuntimeException::class);
    });
});

it('يحجز التقييم للمراجعة ولا يدخل المتوسط قبل نشره', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('community.moderate_reviews', true);

        $course = seedCourse();
        $student = seedStudent();
        app(EnrollStudent::class)->handle($student, $course, 'free');

        $review = app(SubmitReview::class)->forCourse($student, $course, ['rating' => 5, 'body' => 'ممتاز.']);

        expect($review->status)->toBe('pending')
            ->and((float) $course->refresh()->rating_avg)->toBe(0.0);

        app(SubmitReview::class)->moderate($review, 'approved');

        expect((float) $course->refresh()->rating_avg)->toBe(5.0)
            ->and((int) $course->ratings_count)->toBe(1);
    });
});

it('ينشر التقييم العالي تلقائياً حين يُفعّل الخيار', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('community.moderate_reviews', true);
        setting()->set('community.auto_approve_high', true);

        $course = seedCourse();
        $high = seedStudent('high@t.test');
        $low = seedStudent('low@t.test');

        app(EnrollStudent::class)->handle($high, $course, 'free');
        app(EnrollStudent::class)->handle($low, $course, 'free');

        $reviews = app(SubmitReview::class);

        expect($reviews->forCourse($high, $course, ['rating' => 5])->status)->toBe('approved')
            ->and($reviews->forCourse($low, $course, ['rating' => 2])->status)->toBe('pending');
    });
});

it('يرفض تقييماً خارج نطاق النجوم', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $course = seedCourse();
        $student = seedStudent();
        app(EnrollStudent::class)->handle($student, $course, 'free');

        expect(fn () => app(SubmitReview::class)->forCourse($student, $course, ['rating' => 9]))
            ->toThrow(RuntimeException::class);
    });
});

it('يحدّث تقييم صاحبه ولا يضيف ثانياً', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('community.moderate_reviews', false);

        $course = seedCourse();
        $student = seedStudent();
        app(EnrollStudent::class)->handle($student, $course, 'free');

        $reviews = app(SubmitReview::class);
        $reviews->forCourse($student, $course, ['rating' => 3]);
        $reviews->forCourse($student, $course, ['rating' => 5]);

        expect(CourseReview::count())->toBe(1)
            ->and((float) $course->refresh()->rating_avg)->toBe(5.0);
    });
});

/*
 | التحفيز.
 */

it('لا يمنح نقاط نفس المصدر مرتين', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $course = seedCourse();
        $student = seedStudent();
        $enrollment = app(EnrollStudent::class)->handle($student, $course, 'free');
        $item = $course->items()->first();

        $progress = app(TrackProgress::class);
        $progress->complete($enrollment, $item);
        $progress->uncomplete($enrollment, $item);
        $progress->complete($enrollment, $item);

        expect(PointEntry::where('user_id', $student->getKey())
            ->where('rule', 'lesson.completed')->count())->toBe(1);
    });
});

it('يحترم السقف اليومي للقاعدة بلا مصدر', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $student = seedStudent();
        $points = app(AwardPoints::class);

        // question.asked سقفها ثلاث مرات يومياً
        foreach (range(1, 5) as $i) {
            $points->handle($student, 'question.asked');
        }

        expect(PointEntry::where('user_id', $student->getKey())
            ->where('rule', 'question.asked')->count())->toBe(3);
    });
});

it('لا يمنح نقاطاً حين تُصفَّر قيمة القاعدة', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('gamification.points.question.asked', 0);

        $student = seedStudent();
        app(AwardPoints::class)->handle($student, 'question.asked');

        expect(PointEntry::count())->toBe(0);
    });
});

it('يرفع المستوى بمنحنى متباعد لا خطّي', function (): void {
    expect(LearningStreak::levelFor(0))->toBe(1)
        ->and(LearningStreak::levelFor(50))->toBe(2)
        ->and(LearningStreak::levelFor(200))->toBe(3)
        ->and(LearningStreak::levelFor(450))->toBe(4);
});

it('يبني التتابع اليومي ولا يزيده مرتين في اليوم', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $student = seedStudent();
        $points = app(AwardPoints::class);

        $points->touchStreak($student);
        $points->touchStreak($student);

        expect(LearningStreak::where('user_id', $student->getKey())->value('current_days'))->toBe(1);
    });
});

it('يكسر التتابع بعد يوم غياب ويحفظ الأطول', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $student = seedStudent();

        LearningStreak::create([
            'user_id' => $student->getKey(),
            'current_days' => 9, 'longest_days' => 9,
            'last_active_on' => now()->subDays(3),
        ]);

        app(AwardPoints::class)->touchStreak($student);

        $streak = LearningStreak::where('user_id', $student->getKey())->first();

        expect((int) $streak->current_days)->toBe(1)
            ->and((int) $streak->longest_days)->toBe(9);
    });
});

it('يمنح الشارة عند بلوغ شرطها ولا يكرّرها', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        app(AwardBadges::class)->install();

        $course = seedCourse();
        $student = seedStudent();
        $enrollment = app(EnrollStudent::class)->handle($student, $course, 'free');

        app(TrackProgress::class)->complete($enrollment, $course->items()->first());

        $badge = Badge::where('key', 'first-lesson')->firstOrFail();

        expect($student->badges()->whereKey($badge->getKey())->exists())->toBeTrue();

        app(AwardBadges::class)->handle($student);

        expect($student->badges()->whereKey($badge->getKey())->count())->toBe(1);
    });
});

it('ينشئ الشارات الافتراضية عند التهيئة ولا يكرّرها', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        // التهيئة أنشأتها فعلاً — والإعادة لا تضيف شيئاً
        $existing = Badge::count();

        expect($existing)->toBe(count(config('gamification.badges')))
            ->and(app(AwardBadges::class)->install())->toBe(0)
            ->and(Badge::count())->toBe($existing);
    });
});

/*
 | الشاشات.
 */

it('يعرض قائمة النقاش وموضوعه', function (): void {
    $tenant = provision();

    $id = $tenant->run(function (): int {
        $course = seedCourse();
        $student = seedStudent();
        app(EnrollStudent::class)->handle($student, $course, 'free');

        return (int) app(PostDiscussion::class)->ask($student, $course, [
            'title' => 'سؤال يظهر في القائمة', 'body' => 'تفاصيل السؤال كاملة هنا.',
        ])->getKey();
    });

    tenantGet($tenant, '/discussions')->assertOk()->assertSee('سؤال يظهر في القائمة');
    tenantGet($tenant, '/discussions/'.$id)->assertOk()->assertSee('تفاصيل السؤال كاملة هنا');
});

it('يطرح سؤالاً ويردّ عليه من الشاشة', function (): void {
    $tenant = provision();

    $slug = $tenant->run(function (): string {
        $course = seedCourse();
        $owner = User::where('role', 'owner')->firstOrFail();
        app(EnrollStudent::class)->handle($owner, $course, 'free');

        return $course->slug;
    });

    actingAsOwner($tenant);

    tenantPost($tenant, '/courses/'.$slug.'/discussions', [
        'title' => 'سؤال من الشاشة', 'body' => 'هذا نصّ السؤال بتفاصيله.',
    ])->assertRedirect();

    $id = $tenant->run(fn (): int => (int) Discussion::firstOrFail()->getKey());

    tenantPost($tenant, '/discussions/'.$id.'/replies', ['body' => 'ردّ من الشاشة.'])
        ->assertRedirect();

    $tenant->run(fn () => expect(DiscussionReply::count())->toBe(1));
});

it('يفتح شاشة تقدّمي ولوحة الصدارة', function (): void {
    $tenant = provision();

    $tenant->run(fn () => app(AwardBadges::class)->install());

    actingAsOwner($tenant);

    tenantGet($tenant, '/my-progress')->assertOk()->assertSee('الشارات');
    tenantGet($tenant, '/leaderboard')->assertOk()->assertSee('ترتيبك');
});

it('يخفي لوحة الصدارة حين تُعطّل', function (): void {
    $tenant = provision();

    $tenant->run(fn () => setting()->set('gamification.leaderboard', false));

    actingAsOwner($tenant);

    tenantGet($tenant, '/leaderboard')->assertNotFound();
});

it('يراجع التقييم من اللوحة فيدخل المتوسط', function (): void {
    $tenant = provision();

    $reviewId = $tenant->run(function (): int {
        setting()->set('community.moderate_reviews', true);

        $course = seedCourse();
        $student = seedStudent();
        app(EnrollStudent::class)->handle($student, $course, 'free');

        return (int) app(SubmitReview::class)->forCourse($student, $course, ['rating' => 4])->getKey();
    });

    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/reviews')->assertOk();

    tenantPut($tenant, '/admin/reviews/course/'.$reviewId, [
        'status' => 'approved', 'reply' => 'شكراً لك.',
    ])->assertRedirect();

    $tenant->run(function (): void {
        $review = CourseReview::findOrFail(CourseReview::value('id'));

        expect($review->status)->toBe('approved')
            ->and($review->reply)->toBe('شكراً لك.')
            ->and((float) $review->course->refresh()->rating_avg)->toBe(4.0);
    });
});
