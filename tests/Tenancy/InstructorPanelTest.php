<?php

declare(strict_types=1);

use App\Core\Access\Ability;
use App\Core\Access\Roles;
use App\Core\Admin\Navigation;
use App\Models\User;
use App\Modules\Commerce\Models\InstructorEarning;
use App\Modules\Commerce\Models\Payout;
use App\Modules\Community\Models\Discussion;
use App\Modules\Lms\Actions\EnrollStudent;
use App\Modules\Lms\Models\Assignment;
use App\Modules\Lms\Models\AssignmentSubmission;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseItem;
use App\Modules\Lms\Models\CourseReview;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\Instructor;
use App\Modules\Lms\Models\Lesson;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

/*
 | لوحة المدرّس — البند ٣.
 |
 | البند ١ حرس الموارد المولَّدة من نواة الإدارة، وترك ثلاث شاشات
 | مكتوبة باليد بلا حصر: التصحيح، وباني المنهج، وطابور التقييمات.
 | هنا نُغلقها ونبني ما لم يكن للمدرّس أصلاً: لوحته وطلابه وأرباحه.
 */

/** مدرّسان، لكلٍّ كورس، وطالب في كورس كلٍّ منهما. */
function seedTwoInstructors(): array
{
    $mineUser = User::create([
        'name' => 'مدرّسي', 'email' => 'mine@t.test', 'password' => 'password',
        'role' => 'instructor', 'status' => 'active',
    ]);
    $theirUser = User::create([
        'name' => 'مدرّس آخر', 'email' => 'theirs@t.test', 'password' => 'password',
        'role' => 'instructor', 'status' => 'active',
    ]);

    $mine = Instructor::create(['user_id' => $mineUser->getKey(), 'approved_at' => now(), 'commission_rate' => 70]);
    $theirs = Instructor::create(['user_id' => $theirUser->getKey(), 'approved_at' => now()]);

    $myCourse = seedCourse(['slug' => 'my-course', 'title' => ['ar' => 'كورسي أنا'], 'instructor_id' => $mine->getKey()]);
    $theirCourse = seedCourse(['slug' => 'their-course', 'title' => ['ar' => 'كورس غيري'], 'instructor_id' => $theirs->getKey()]);

    $myStudent = seedStudent('mystudent@t.test');
    $myStudent->forceFill(['name' => 'طالبي أنا'])->save();
    $theirStudent = seedStudent('theirstudent@t.test');
    $theirStudent->forceFill(['name' => 'طالب غيري'])->save();

    app(EnrollStudent::class)->handle($myStudent, $myCourse, 'free');
    app(EnrollStudent::class)->handle($theirStudent, $theirCourse, 'free');

    return compact('mineUser', 'theirUser', 'mine', 'theirs', 'myCourse', 'theirCourse', 'myStudent', 'theirStudent');
}

/** واجب داخل منهج كورس بعينه. */
function seedAssignmentFor(Course $course): Assignment
{
    $assignment = Assignment::create([
        'title' => ['ar' => 'واجب '.$course->slug],
        'max_marks' => 100,
        'passing_marks' => 50,
    ]);

    CourseItem::create([
        'course_id' => $course->getKey(),
        'itemable_type' => Assignment::class,
        'itemable_id' => $assignment->getKey(),
        'position' => 50,
    ]);

    return $assignment;
}

function actAsMyInstructor($tenant): User
{
    tenancy()->initialize($tenant);
    $user = $tenant->run(fn () => User::where('email', 'mine@t.test')->firstOrFail());
    test()->actingAs($user);

    return $user;
}

// ------------------------------------------------------------------
// الثغرات التي تركها البند ١ في الشاشات المكتوبة باليد
// ------------------------------------------------------------------

it('لا يرى المدرّس في طاولة التصحيح إلا تسليمات طلابه', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $seed = seedTwoInstructors();

        foreach (['myCourse' => 'طالبي أنا', 'theirCourse' => 'طالب غيري'] as $key => $name) {
            $enrollment = Enrollment::whereHas('user', fn ($q) => $q->where('name', $name))->firstOrFail();

            AssignmentSubmission::create([
                'assignment_id' => seedAssignmentFor($seed[$key])->getKey(),
                'enrollment_id' => $enrollment->getKey(),
                'status' => 'pending',
                'submitted_at' => now(),
            ]);
        }
    });

    actAsMyInstructor($tenant);

    tenantGet($tenant, '/admin/grading')
        ->assertOk()
        ->assertSee('طالبي أنا')
        ->assertDontSee('طالب غيري');
});

it('يعيد 404 على تسليم في كورس غيره ولا يسمح بوضع درجة له', function (): void {
    $tenant = provision();

    $foreign = $tenant->run(function (): int {
        $seed = seedTwoInstructors();
        $enrollment = Enrollment::whereHas('user', fn ($q) => $q->where('name', 'طالب غيري'))->firstOrFail();

        return (int) AssignmentSubmission::create([
            'assignment_id' => seedAssignmentFor($seed['theirCourse'])->getKey(),
            'enrollment_id' => $enrollment->getKey(),
            'status' => 'pending',
            'submitted_at' => now(),
        ])->getKey();
    });

    actAsMyInstructor($tenant);

    tenantGet($tenant, '/admin/grading/submissions/'.$foreign)->assertNotFound();
    tenantPut($tenant, '/admin/grading/submissions/'.$foreign, [
        'action' => 'grade', 'marks' => 100,
    ])->assertNotFound();

    $tenant->run(fn () => expect(AssignmentSubmission::find($foreign)->status)->toBe('pending'));
});

it('يعيد 404 على منهج كورس غيره ولا يسمح بتعديله', function (): void {
    $tenant = provision();

    $foreign = $tenant->run(fn (): int => (int) seedTwoInstructors()['theirCourse']->getKey());

    actAsMyInstructor($tenant);

    tenantGet($tenant, '/admin/courses/'.$foreign.'/curriculum')->assertNotFound();
    tenantPost($tenant, '/admin/courses/'.$foreign.'/sections', [
        'title' => ['ar' => 'قسم مدسوس'],
    ])->assertNotFound();

    $tenant->run(fn () => expect(Course::find($foreign)->sections()->where('title->ar', 'قسم مدسوس')->exists())->toBeFalse());
});

it('لا يضمّ المدرّس درساً من بنك غيره إلى منهجه', function (): void {
    $tenant = provision();

    $ids = $tenant->run(function (): array {
        $seed = seedTwoInstructors();

        $foreignLesson = Lesson::create([
            'title' => ['ar' => 'درس غيري'], 'type' => 'video',
            'created_by' => $seed['theirUser']->getKey(),
        ]);

        return ['course' => (int) $seed['myCourse']->getKey(), 'lesson' => (int) $foreignLesson->getKey()];
    });

    actAsMyInstructor($tenant);

    tenantPost($tenant, '/admin/courses/'.$ids['course'].'/items', [
        'kind' => 'lesson', 'itemable_id' => $ids['lesson'],
    ])->assertNotFound();
});

it('لا يرى المدرّس في طابور التقييمات إلا تقييمات كورساته', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $seed = seedTwoInstructors();

        foreach ([['myCourse', 'تقييم كورسي'], ['theirCourse', 'تقييم كورس غيري']] as [$key, $body]) {
            CourseReview::create([
                'course_id' => $seed[$key]->getKey(),
                'user_id' => $seed['myStudent']->getKey(),
                'rating' => 5,
                'body' => $body,
                'status' => 'pending',
            ]);
        }
    });

    actAsMyInstructor($tenant);

    tenantGet($tenant, '/admin/reviews')
        ->assertOk()
        ->assertSee('تقييم كورسي')
        ->assertDontSee('تقييم كورس غيري');
});

// ------------------------------------------------------------------
// لوحته هو
// ------------------------------------------------------------------

it('يُساق المدرّس إلى لوحته لا إلى لوحة صاحب المنصّة', function (): void {
    $tenant = provision();

    $tenant->run(fn () => seedTwoInstructors());
    actAsMyInstructor($tenant);

    tenantGet($tenant, '/admin/dashboard')
        ->assertOk()
        ->assertSee(__('كورساتي'))
        // لا باقة ولا استهلاك: قرار صاحب المنصّة لا قراره
        ->assertDontSee(__('استهلاكك من الباقة'))
        ->assertDontSee(__('فريق المنصة'));
});

it('يرى صاحب المنصّة لوحته هو لا لوحة المدرّس', function (): void {
    $tenant = provision();

    tenancy()->initialize($tenant);
    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/dashboard')
        ->assertOk()
        ->assertSee(__('استهلاكك من الباقة'));
});

it('لا يرى المدرّس في شاشة الطلاب إلا طلاب كورساته', function (): void {
    $tenant = provision();

    $tenant->run(fn () => seedTwoInstructors());
    actAsMyInstructor($tenant);

    tenantGet($tenant, '/admin/students')
        ->assertOk()
        ->assertSee('طالبي أنا')
        ->assertDontSee('طالب غيري');
});

it('يعيد 404 على ملفّ طالب في كورس غيره', function (): void {
    $tenant = provision();

    $foreign = $tenant->run(function (): int {
        seedTwoInstructors();

        return (int) Enrollment::whereHas('user', fn ($q) => $q->where('name', 'طالب غيري'))->firstOrFail()->getKey();
    });

    actAsMyInstructor($tenant);

    tenantGet($tenant, '/admin/students/'.$foreign)->assertNotFound();
});

it('يفتح المدرّس ملفّ طالبه ويرى تقدّمه', function (): void {
    $tenant = provision();

    $mine = $tenant->run(function (): int {
        seedTwoInstructors();

        return (int) Enrollment::whereHas('user', fn ($q) => $q->where('name', 'طالبي أنا'))->firstOrFail()->getKey();
    });

    actAsMyInstructor($tenant);

    tenantGet($tenant, '/admin/students/'.$mine)
        ->assertOk()
        ->assertSee('طالبي أنا')
        ->assertSee(__('المنهج'));
});

// ------------------------------------------------------------------
// الأسئلة والإعلانات
// ------------------------------------------------------------------

it('لا يرى المدرّس إلا أسئلة كورساته ويردّ عليها', function (): void {
    $tenant = provision();

    $mine = $tenant->run(function (): int {
        $seed = seedTwoInstructors();

        $question = Discussion::create([
            'type' => 'question', 'course_id' => $seed['myCourse']->getKey(),
            'user_id' => $seed['myStudent']->getKey(),
            'title' => 'سؤال في كورسي', 'body' => 'كيف أبدأ؟', 'status' => 'open',
        ]);

        Discussion::create([
            'type' => 'question', 'course_id' => $seed['theirCourse']->getKey(),
            'user_id' => $seed['theirStudent']->getKey(),
            'title' => 'سؤال في كورس غيري', 'body' => 'كيف أبدأ؟', 'status' => 'open',
        ]);

        return (int) $question->getKey();
    });

    $teacher = actAsMyInstructor($tenant);

    tenantGet($tenant, '/admin/discussions')
        ->assertOk()
        ->assertSee('سؤال في كورسي')
        ->assertDontSee('سؤال في كورس غيري');

    tenantPost($tenant, '/admin/discussions/'.$mine.'/replies', [
        'body' => 'ابدأ بالدرس الأول.', 'is_answer' => '1',
    ])->assertRedirect();

    $tenant->run(function () use ($mine, $teacher): void {
        $discussion = Discussion::with('replies')->find($mine);

        expect($discussion->status)->toBe('answered')
            ->and($discussion->replies)->toHaveCount(1)
            // وسم «من المدرّس» يُحسم في الخادم لا في النموذج
            ->and($discussion->replies->first()->is_instructor)->toBeTrue()
            ->and((int) $discussion->replies->first()->user_id)->toBe((int) $teacher->getKey());
    });
});

it('يعيد 404 على سؤال في كورس غيره ولا يردّ عليه', function (): void {
    $tenant = provision();

    $foreign = $tenant->run(function (): int {
        $seed = seedTwoInstructors();

        return (int) Discussion::create([
            'type' => 'question', 'course_id' => $seed['theirCourse']->getKey(),
            'user_id' => $seed['theirStudent']->getKey(),
            'title' => 'سؤال غيري', 'body' => '؟', 'status' => 'open',
        ])->getKey();
    });

    actAsMyInstructor($tenant);

    tenantGet($tenant, '/admin/discussions/'.$foreign)->assertNotFound();
    tenantPost($tenant, '/admin/discussions/'.$foreign.'/replies', ['body' => 'ردّ مدسوس'])->assertNotFound();
});

it('ينشر المدرّس إعلاناً على كورسه ولا ينشره على كورس غيره', function (): void {
    $tenant = provision();

    $ids = $tenant->run(function (): array {
        $seed = seedTwoInstructors();

        return [
            'mine' => (int) $seed['myCourse']->getKey(),
            'theirs' => (int) $seed['theirCourse']->getKey(),
        ];
    });

    actAsMyInstructor($tenant);

    tenantPost($tenant, '/admin/announcements', [
        'course_id' => $ids['mine'], 'title' => 'إعلان مشروع', 'body' => 'موعد الاختبار غداً.',
    ])->assertRedirect();

    tenantPost($tenant, '/admin/announcements', [
        'course_id' => $ids['theirs'], 'title' => 'إعلان مدسوس', 'body' => 'لست صاحب هذا الكورس.',
    ])->assertNotFound();

    $tenant->run(function (): void {
        expect(Discussion::where('type', 'announcement')->pluck('title')->all())
            ->toBe(['إعلان مشروع']);
    });
});

// ------------------------------------------------------------------
// الأرباح وطلب السحب
// ------------------------------------------------------------------

it('لا يرى المدرّس إلا أرباحه هو', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $seed = seedTwoInstructors();

        InstructorEarning::create([
            'instructor_id' => $seed['mine']->getKey(), 'currency' => 'EGP',
            'amount_minor' => 50000, 'rate' => 70, 'status' => 'available',
        ]);
        InstructorEarning::create([
            'instructor_id' => $seed['theirs']->getKey(), 'currency' => 'EGP',
            'amount_minor' => 900000, 'rate' => 70, 'status' => 'available',
        ]);
    });

    actAsMyInstructor($tenant);

    $response = tenantGet($tenant, '/admin/earnings')->assertOk();

    // ٥٠٠ جنيه له، و٩٠٠٠ لغيره — الثاني لا يظهر بحال
    expect($response->getContent())->not->toContain('9,000');
});

it('يطلب المدرّس سحب رصيده المتاح ولا يطلب ثانياً فوقه', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $seed = seedTwoInstructors();

        InstructorEarning::create([
            'instructor_id' => $seed['mine']->getKey(), 'currency' => 'EGP',
            'amount_minor' => 50000, 'rate' => 70, 'status' => 'available',
            'available_at' => now()->subDay(),
        ]);
    });

    actAsMyInstructor($tenant);

    tenantPost($tenant, '/admin/earnings/payout', ['method' => 'instapay'])->assertRedirect();

    $tenant->run(function (): void {
        $payout = Payout::firstOrFail();

        expect((int) $payout->amount_minor)->toBe(50000)
            ->and($payout->status)->toBe('pending')
            ->and(InstructorEarning::first()->status)->toBe('paid');
    });

    // طلب ثانٍ بلا رصيد جديد لا يُفتح
    tenantPost($tenant, '/admin/earnings/payout', ['method' => 'instapay'])
        ->assertSessionHasErrors('payout');

    $tenant->run(fn () => expect(Payout::count())->toBe(1));
});

it('يمنع طلب السحب حين يقفله صاحب المنصّة', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $seed = seedTwoInstructors();

        setting()->set('commerce.payout_requests', false);

        InstructorEarning::create([
            'instructor_id' => $seed['mine']->getKey(), 'currency' => 'EGP',
            'amount_minor' => 50000, 'rate' => 70, 'status' => 'available',
            'available_at' => now()->subDay(),
        ]);
    });

    actAsMyInstructor($tenant);

    tenantPost($tenant, '/admin/earnings/payout', ['method' => 'instapay'])->assertNotFound();
    $tenant->run(fn () => expect(Payout::count())->toBe(0));
});

it('يحترم الحدّ الأدنى للسحب', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $seed = seedTwoInstructors();

        setting()->set('commerce.payout_minimum', 1000);

        InstructorEarning::create([
            'instructor_id' => $seed['mine']->getKey(), 'currency' => 'EGP',
            'amount_minor' => 50000, 'rate' => 70, 'status' => 'available',
            'available_at' => now()->subDay(),
        ]);
    });

    actAsMyInstructor($tenant);

    // الرصيد ٥٠٠ والحدّ ١٠٠٠
    tenantPost($tenant, '/admin/earnings/payout', ['method' => 'instapay'])
        ->assertSessionHasErrors('payout');

    $tenant->run(fn () => expect(Payout::count())->toBe(0));
});

// ------------------------------------------------------------------
// الإحصاءات
// ------------------------------------------------------------------

it('لا تحصي شاشة الإحصاءات إلا كورسات صاحبها', function (): void {
    $tenant = provision();

    $tenant->run(fn () => seedTwoInstructors());
    actAsMyInstructor($tenant);

    tenantGet($tenant, '/admin/statistics')
        ->assertOk()
        ->assertSee('كورسي أنا')
        ->assertDontSee('كورس غيري');
});

// ------------------------------------------------------------------
// حراسة الشاشات الجديدة
// ------------------------------------------------------------------

it('يمنع الطالب من كل شاشة في لوحة المدرّس', function (): void {
    $tenant = provision();

    $tenant->run(fn () => seedStudent('pupil@t.test'));

    tenancy()->initialize($tenant);
    test()->actingAs($tenant->run(fn () => User::where('email', 'pupil@t.test')->firstOrFail()));

    foreach (['/admin/students', '/admin/discussions', '/admin/announcements', '/admin/earnings', '/admin/statistics'] as $path) {
        expect(tenantGet($tenant, $path)->getStatusCode())->toBeIn([403, 404], $path);
    }
});

it('تحمل كل شاشة جديدة صلاحية معرَّفة في القائمة المغلقة', function (): void {
    $abilities = [
        Ability::STUDENTS_VIEW, Ability::DISCUSSIONS_MODERATE,
        Ability::ANNOUNCEMENTS_MANAGE, Ability::EARNINGS_VIEW, Ability::STATISTICS_VIEW,
    ];

    foreach ($abilities as $ability) {
        expect(Ability::all())->toContain($ability)
            ->and(Ability::scoped())->toContain($ability);
    }

    // والمدرّس يملكها كلها، والطالب لا يملك منها شيئاً
    $roles = app(Roles::class);

    foreach ($abilities as $ability) {
        expect(config('roles.abilities.instructor'))->toContain($ability)
            ->and(config('roles.abilities.student'))->not->toContain($ability);
    }

    expect($roles)->toBeInstanceOf(Roles::class);
});

/*
 | الحارس الأهم للمدرّس: القائمة لا تعرض باباً يُصفَق في وجهه.
 |
 | اختبار البند ١ يطرق قائمة صاحب المنصّة وحده، ولمّا طُرقت قائمة
 | المدرّس ظهرت سبعة عناصر تُعيد 403: التقارير والمظهر والوسائط
 | والعمولة والفوترة والإشعارات والإعدادات. صلاحية العنصر كانت
 | تُشتقّ من مورده، والعناصر المبنية على مسار مكتوب باليد بلا مورد.
 */
it('لا رابط في قائمة المدرّس يُصفَق في وجهه', function (): void {
    $tenant = provision();

    $tenant->run(fn () => seedTwoInstructors());
    actAsMyInstructor($tenant);

    $items = $tenant->run(fn (): array => collect(app(Navigation::class)->groups())
        ->flatMap(fn (array $group): array => $group['items'])
        ->reject(fn (array $item): bool => $item['locked'] || $item['url'] === null)
        ->all());

    expect($items)->not->toBeEmpty();

    foreach ($items as $item) {
        $path = parse_url($item['url'], PHP_URL_PATH) ?: '/';

        $status = test()->followingRedirects()
            ->get(tenantUrl($tenant, $path))
            ->getStatusCode();

        expect($status)->toBe(200, "رابط مكسور في قائمة المدرّس: {$item['label']} → {$path}");
    }
});

it('لا تعرض قائمة المدرّس ما لا يملكه', function (): void {
    // نمط المنصّة المتعددة: فيه موديول المجتمع فتظهر شاشة الأسئلة
    $tenant = provision(['platform_mode' => 'marketplace']);

    $tenant->run(fn () => seedTwoInstructors());
    actAsMyInstructor($tenant);

    $keys = $tenant->run(fn (): array => collect(app(Navigation::class)->groups())
        ->flatMap(fn (array $group): array => $group['items'])
        ->pluck('key')->all());

    // شاشات صاحب المنصّة: مال وناس ومفاتيح
    foreach (['orders', 'users', 'billing', 'reports', 'settings', 'notifications',
        'affiliates', 'recharge-codes', 'refunds', 'media', 'page-builder'] as $forbidden) {
        expect($keys)->not->toContain($forbidden);
    }

    // وشاشاته هو موجودة
    foreach (['dashboard', 'courses', 'students', 'discussions', 'announcements',
        'earnings', 'statistics', 'grading'] as $mine) {
        expect($keys)->toContain($mine);
    }
});
