<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Lms\Actions\EnrollStudent;
use App\Modules\Lms\Actions\IssueCertificate;
use App\Modules\Lms\Actions\TrackProgress;
use App\Modules\Lms\Models\Certificate;
use App\Modules\Lms\Models\CourseItem;
use App\Modules\Lms\Models\CourseSection;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\Lesson;
use App\Modules\Lms\Models\LessonProgress;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

it('lists published courses in the public catalog', function () {
    $tenant = provision();

    $tenant->run(function (): void {
        seedCourse(['title' => ['ar' => 'كورس منشور']]);
        seedCourse(['title' => ['ar' => 'كورس مسودّة'], 'status' => 'draft']);
    });

    tenantGet($tenant, '/courses')
        ->assertOk()
        ->assertSee('كورس منشور')
        ->assertDontSee('كورس مسودّة');
});

it('filters the catalog by search term', function () {
    $tenant = provision();

    $tenant->run(function (): void {
        seedCourse(['title' => ['ar' => 'أساسيات الكيمياء']]);
        seedCourse(['title' => ['ar' => 'أساسيات الفيزياء']]);
    });

    tenantGet($tenant, '/courses?q=الكيمياء')
        ->assertOk()
        ->assertSee('أساسيات الكيمياء')
        ->assertDontSee('أساسيات الفيزياء');
});

it('opens a course page with its curriculum', function () {
    $tenant = provision();
    $slug = $tenant->run(fn (): string => seedCourse()->slug);

    tenantGet($tenant, '/courses/'.$slug)
        ->assertOk()
        ->assertSee('أساسيات لارافيل')
        ->assertSee('الدرس 1');
});

it('hides a draft course from the public page', function () {
    $tenant = provision();
    $slug = $tenant->run(fn (): string => seedCourse(['status' => 'draft'])->slug);

    tenantGet($tenant, '/courses/'.$slug)->assertNotFound();
});

it('tells a visitor why the content is locked instead of hiding it silently', function () {
    $tenant = provision();
    $slug = $tenant->run(fn (): string => seedCourse()->slug);

    tenantGet($tenant, '/courses/'.$slug)
        ->assertOk()
        ->assertSee('سجّل في الكورس لفتح هذا المحتوى');
});

it('sends a guest to login rather than into the learning room', function () {
    $tenant = provision();
    $slug = $tenant->run(fn (): string => seedCourse()->slug);

    tenantGet($tenant, '/learn/'.$slug)->assertRedirectContains('/login');
});

it('refuses the learning room to a signed-in stranger', function () {
    $tenant = provision();
    $slug = $tenant->run(fn (): string => seedCourse()->slug);

    $student = $tenant->run(fn (): User => seedStudent());
    tenancy()->initialize($tenant);
    $this->actingAs($student);

    tenantGet($tenant, '/learn/'.$slug)->assertForbidden();
});

it('lets an enrolled student in and resumes where they stopped', function () {
    $tenant = provision();

    [$slug, $student, $secondItemId] = $tenant->run(function (): array {
        $course = seedCourse();
        $student = seedStudent();
        $enrollment = app(EnrollStudent::class)->handle($student, $course);
        $items = $course->items()->get();

        app(TrackProgress::class)->watch($enrollment, $items[1], 120, 120);

        return [$course->slug, $student, $items[1]->id];
    });

    tenancy()->initialize($tenant);
    $this->actingAs($student);

    tenantGet($tenant, '/learn/'.$slug)->assertOk()->assertSee('الدرس 2');
});

it('enrols a student in a free course from its page', function () {
    $tenant = provision();
    [$slug, $student] = $tenant->run(fn (): array => [seedCourse()->slug, seedStudent()]);

    tenancy()->initialize($tenant);
    $this->actingAs($student);

    tenantPost($tenant, '/courses/'.$slug.'/enroll')->assertRedirect();

    $tenant->run(fn () => expect(Enrollment::count())->toBe(1));
});

it('refuses to hand a paid course away for free', function () {
    $tenant = provision();

    [$slug, $student] = $tenant->run(fn (): array => [
        seedCourse(['enrollment_type' => 'paid', 'price_minor' => 50000])->slug,
        seedStudent(),
    ]);

    tenancy()->initialize($tenant);
    $this->actingAs($student);

    tenantPost($tenant, '/courses/'.$slug.'/enroll')->assertStatus(402);

    $tenant->run(fn () => expect(Enrollment::count())->toBe(0));
});

it('saves the watch position from the player heartbeat', function () {
    $tenant = provision();

    [$slug, $student, $itemId] = $tenant->run(function (): array {
        $course = seedCourse();
        $student = seedStudent();
        app(EnrollStudent::class)->handle($student, $course);

        return [$course->slug, $student, $course->items()->first()->id];
    });

    tenancy()->initialize($tenant);
    $this->actingAs($student);

    tenantPost($tenant, '/learn/'.$slug.'/'.$itemId.'/heartbeat', ['position' => 240, 'watched' => 240])
        ->assertOk()
        ->assertJson(['saved' => true]);

    $tenant->run(fn () => expect(
        LessonProgress::first()->last_position_seconds
    )->toBe(240));
});

it('verifies a certificate by its code without asking anyone to sign in', function () {
    $tenant = provision();

    $code = $tenant->run(function (): string {
        $course = seedCourse();
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);

        foreach ($course->items()->get() as $item) {
            app(TrackProgress::class)->complete($enrollment, $item);
        }

        return Certificate::firstOrFail()->code;
    });

    tenantGet($tenant, '/certificate/'.$code)
        ->assertOk()
        ->assertSee('شهادة سارية')
        ->assertSee('طالب مجتهد');
});

it('says plainly that an unknown code has no certificate', function () {
    $tenant = provision();

    tenantGet($tenant, '/certificate/NOPE-404')
        ->assertOk()
        ->assertSee('لا شهادة بهذا الكود');
});

it('marks a revoked certificate as invalid on its public page', function () {
    $tenant = provision();

    $code = $tenant->run(function (): string {
        $course = seedCourse();
        $enrollment = app(EnrollStudent::class)->handle(seedStudent(), $course);

        foreach ($course->items()->get() as $item) {
            app(TrackProgress::class)->complete($enrollment, $item);
        }

        $certificate = Certificate::firstOrFail();
        app(IssueCertificate::class)->revoke($certificate, 'غش في الاختبار');

        return $certificate->code;
    });

    tenantGet($tenant, '/certificate/'.$code)->assertOk()->assertSee('شهادة غير سارية');
});

it('opens every LMS admin screen for the owner', function () {
    $tenant = provision();
    $tenant->run(fn () => seedCourse());
    actingAsOwner($tenant);

    foreach (['courses', 'lessons', 'quizzes', 'questions', 'enrollments', 'certificates', 'taxonomies'] as $resource) {
        tenantGet($tenant, '/admin/'.$resource)->assertOk();
    }
});

it('builds the curriculum from the admin screen', function () {
    $tenant = provision();
    $courseId = $tenant->run(fn (): int => seedCourse()->id);
    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/courses/'.$courseId.'/curriculum')
        ->assertOk()
        ->assertSee('المقدمة');

    tenantPost($tenant, '/admin/courses/'.$courseId.'/sections', ['title' => ['ar' => 'الوحدة الثانية']])
        ->assertRedirect();

    $tenant->run(fn () => expect(
        CourseSection::where('course_id', $courseId)->count()
    )->toBe(2));
});

it('removes an item from the curriculum without deleting the lesson itself', function () {
    $tenant = provision();

    [$courseId, $itemId] = $tenant->run(function (): array {
        $course = seedCourse();

        return [$course->id, $course->items()->first()->id];
    });

    actingAsOwner($tenant);

    tenantDelete($tenant, '/admin/courses/'.$courseId.'/items/'.$itemId)->assertRedirect();

    $tenant->run(function () use ($courseId): void {
        expect(CourseItem::where('course_id', $courseId)->count())->toBe(2)
            ->and(Lesson::count())->toBe(3);
    });
});

it('refuses a curriculum item that points at nothing', function () {
    $tenant = provision();
    $courseId = $tenant->run(fn (): int => seedCourse()->id);
    actingAsOwner($tenant);

    tenantPost($tenant, '/admin/courses/'.$courseId.'/items', [
        'kind' => 'lesson',
        'itemable_id' => 999999,
    ])->assertNotFound();
});
