<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Lms\Actions\EnrollStudent;
use App\Modules\Lms\Models\CourseItem;
use App\Modules\Lms\Models\H5pPackage;
use App\Modules\Lms\Models\Lesson;
use App\Modules\Lms\Models\XapiStatement;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

/**
 * يبني ملفّ ‎.h5p‎ حقيقياً على القرص.
 *
 * @param  array<string, string>  $entries  المسار داخل الحزمة => محتواه
 */
function h5pArchive(array $entries): UploadedFile
{
    $path = sys_get_temp_dir().'/'.Str::random(12).'.h5p';

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($entries as $name => $body) {
        $zip->addFromString($name, $body);
    }

    $zip->close();

    return new UploadedFile($path, 'package.h5p', 'application/zip', null, true);
}

/** حزمة صالحة صغيرة */
function validH5p(): UploadedFile
{
    return h5pArchive([
        'h5p.json' => json_encode([
            'title' => 'بطاقات المراجعة',
            'mainLibrary' => 'H5P.Flashcards',
            'preloadedDependencies' => [['machineName' => 'H5P.Flashcards', 'majorVersion' => 1, 'minorVersion' => 5]],
        ], JSON_UNESCAPED_UNICODE),
        'content/content.json' => '{"cards":[]}',
        'H5P.Flashcards-1.5/library.json' => '{"machineName":"H5P.Flashcards"}',
    ]);
}

/** درسٌ داخل كورس، ومعرّفاه */
function h5pLesson(): array
{
    $course = seedCourse();

    $lesson = Lesson::create(['title' => ['ar' => 'درس تفاعلي'], 'type' => 'h5p']);

    CourseItem::create([
        'course_id' => $course->id,
        'section_id' => $course->sections()->first()?->id,
        'itemable_type' => Lesson::class,
        'itemable_id' => $lesson->id,
        'position' => 9,
    ]);

    return [$course, $lesson];
}

it('uploads a package, extracts it and points the lesson at it', function () {
    $tenant = provision(['plan_key' => 'professional']);

    [$lessonId, $owner] = $tenant->run(function (): array {
        [, $lesson] = h5pLesson();

        return [$lesson->id, User::where('role', 'owner')->firstOrFail()];
    });

    tenancy()->initialize($tenant);
    $this->actingAs($owner);

    tenantPost($tenant, '/admin/lessons/'.$lessonId.'/h5p', ['package' => validH5p()])
        ->assertRedirect();

    $package = H5pPackage::where('lesson_id', $lessonId)->first();

    expect($package)->not->toBeNull()
        ->and($package->title)->toBe('بطاقات المراجعة')
        ->and($package->main_library)->toBe('H5P.Flashcards')
        ->and($package->kindLabel())->toBe('Flashcards')
        ->and(File::exists(storage_path('app/public/'.$package->path.'/content/content.json')))->toBeTrue()
        ->and(Lesson::find($lessonId)->type)->toBe('h5p');

    // والشاشة تعرض ما رُفع: شاشةٌ لا تُفتح كأن الرفع لم يقع
    tenantGet($tenant, '/admin/lessons/'.$lessonId.'/h5p')
        ->assertOk()
        ->assertSee('بطاقات المراجعة')
        ->assertSee('Flashcards');

    // وتبويب النشاط في التقارير يُفتح ولو لم تصل عبارةٌ بعد
    tenantGet($tenant, '/admin/reports?tab=activity')->assertOk();

    File::deleteDirectory(storage_path('app/public/'.$package->path));
});

it('refuses an archive with no h5p.json', function () {
    $tenant = provision(['plan_key' => 'professional']);

    [$lessonId, $owner] = $tenant->run(function (): array {
        [, $lesson] = h5pLesson();

        return [$lesson->id, User::where('role', 'owner')->firstOrFail()];
    });

    tenancy()->initialize($tenant);
    $this->actingAs($owner);

    tenantPost($tenant, '/admin/lessons/'.$lessonId.'/h5p', [
        'package' => h5pArchive(['readme.txt' => 'مرحباً']),
    ])->assertSessionHasErrors('package');

    expect(H5pPackage::count())->toBe(0);
});

it('refuses a package that writes outside its folder', function () {
    $tenant = provision(['plan_key' => 'professional']);

    [$lessonId, $owner] = $tenant->run(function (): array {
        [, $lesson] = h5pLesson();

        return [$lesson->id, User::where('role', 'owner')->firstOrFail()];
    });

    tenancy()->initialize($tenant);
    $this->actingAs($owner);

    tenantPost($tenant, '/admin/lessons/'.$lessonId.'/h5p', [
        'package' => h5pArchive([
            'h5p.json' => '{"title":"x","mainLibrary":"H5P.X"}',
            'content/content.json' => '{}',
            '../../../../hacked.txt' => 'ملكتُ الخادم',
        ]),
    ])->assertSessionHasErrors('package');

    expect(H5pPackage::count())->toBe(0)
        ->and(File::exists(base_path('hacked.txt')))->toBeFalse();
});

it('refuses a package carrying an executable file', function () {
    $tenant = provision(['plan_key' => 'professional']);

    [$lessonId, $owner] = $tenant->run(function (): array {
        [, $lesson] = h5pLesson();

        return [$lesson->id, User::where('role', 'owner')->firstOrFail()];
    });

    tenancy()->initialize($tenant);
    $this->actingAs($owner);

    tenantPost($tenant, '/admin/lessons/'.$lessonId.'/h5p', [
        'package' => h5pArchive([
            'h5p.json' => '{"title":"x","mainLibrary":"H5P.X"}',
            'content/content.json' => '{}',
            'content/shell.php' => '<?php echo 1;',
        ]),
    ])->assertSessionHasErrors('package');

    expect(H5pPackage::count())->toBe(0);
});

it('refuses results from someone who is not enrolled', function () {
    $tenant = provision(['plan_key' => 'professional']);

    [$packageId, $stranger] = $tenant->run(function (): array {
        [, $lesson] = h5pLesson();

        $package = H5pPackage::create([
            'lesson_id' => $lesson->id, 'title' => 'x', 'path' => 'h5p/none', 'size' => 1,
        ]);

        return [$package->id, seedStudent('stranger@example.test')];
    });

    tenancy()->initialize($tenant);
    $this->actingAs($stranger);

    tenantPostJson($tenant, '/h5p/'.$packageId.'/xapi', [
        'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/completed'],
    ])->assertForbidden();

    expect(XapiStatement::count())->toBe(0);
});

it('records a result for an enrolled student under their own name', function () {
    $tenant = provision(['plan_key' => 'professional']);

    [$packageId, $student] = $tenant->run(function (): array {
        [$course, $lesson] = h5pLesson();

        $package = H5pPackage::create([
            'lesson_id' => $lesson->id, 'title' => 'بطاقات', 'path' => 'h5p/none', 'size' => 1,
        ]);

        $student = seedStudent();
        app(EnrollStudent::class)->handle($student, $course);

        return [$package->id, $student];
    });

    tenancy()->initialize($tenant);
    $this->actingAs($student);

    tenantPostJson($tenant, '/h5p/'.$packageId.'/xapi', [
        // الفاعل مزوَّر عمداً: يجب أن يُتجاهَل ويُكتب صاحب الجلسة
        'actor' => ['mbox' => 'mailto:owner@example.test'],
        'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/completed'],
        'object' => ['id' => 'http://example.com/whatever'],
        'result' => [
            'score' => ['raw' => 3, 'max' => 5],
            'completion' => true,
            'success' => true,
            'duration' => 'PT1M30S',
        ],
    ])->assertOk();

    $statement = XapiStatement::firstOrFail();

    expect($statement->user_id)->toBe($student->id)
        ->and($statement->verb)->toBe('completed')
        ->and($statement->object_id)->toBe('h5p:'.$packageId)
        ->and((float) $statement->result_score)->toBe(60.0)
        ->and($statement->duration_seconds)->toBe(90)
        ->and($statement->result_completion)->toBeTrue();
});

it('files a sub-question under the package without overwriting its result', function () {
    $tenant = provision(['plan_key' => 'professional']);

    [$packageId, $student] = $tenant->run(function (): array {
        [$course, $lesson] = h5pLesson();

        $package = H5pPackage::create([
            'lesson_id' => $lesson->id, 'title' => 'بطاقات', 'path' => 'h5p/none', 'size' => 1,
        ]);

        $student = seedStudent();
        app(EnrollStudent::class)->handle($student, $course);

        return [$package->id, $student];
    });

    tenancy()->initialize($tenant);
    $this->actingAs($student);

    tenantPostJson($tenant, '/h5p/'.$packageId.'/xapi', [
        'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/answered'],
        'object' => ['id' => 'http://example.com/sub-1'],
        'context' => ['contextActivities' => ['parent' => [['id' => 'http://example.com/root']]]],
        'result' => ['score' => ['scaled' => 1]],
    ])->assertOk();

    $statement = XapiStatement::firstOrFail();

    expect($statement->object_id)->toStartWith('h5p:'.$packageId.'/')
        ->and($statement->object_id)->not->toBe('h5p:'.$packageId);
});

it('keeps one row when the same statement id arrives twice', function () {
    $tenant = provision(['plan_key' => 'professional']);

    [$packageId, $student] = $tenant->run(function (): array {
        [$course, $lesson] = h5pLesson();

        $package = H5pPackage::create([
            'lesson_id' => $lesson->id, 'title' => 'بطاقات', 'path' => 'h5p/none', 'size' => 1,
        ]);

        $student = seedStudent();
        app(EnrollStudent::class)->handle($student, $course);

        return [$package->id, $student];
    });

    tenancy()->initialize($tenant);
    $this->actingAs($student);

    $payload = [
        'id' => '3f2d1b6e-0e6a-4f4a-9f1e-2c5d7a8b9c01',
        'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/completed'],
        'result' => ['score' => ['scaled' => 0.5]],
    ];

    tenantPostJson($tenant, '/h5p/'.$packageId.'/xapi', $payload)->assertOk();
    tenantPostJson($tenant, '/h5p/'.$packageId.'/xapi', $payload)->assertOk();

    expect(XapiStatement::count())->toBe(1);
});

it('locks the upload screen when the plan does not carry H5P', function () {
    $tenant = provision(['plan_key' => 'growth']);

    [$lessonId, $owner] = $tenant->run(function (): array {
        [, $lesson] = h5pLesson();

        return [$lesson->id, User::where('role', 'owner')->firstOrFail()];
    });

    tenancy()->initialize($tenant);
    $this->actingAs($owner);

    tenantGet($tenant, '/admin/lessons/'.$lessonId.'/h5p')->assertStatus(402);
});
