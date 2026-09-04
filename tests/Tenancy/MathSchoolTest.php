<?php

declare(strict_types=1);

use App\Core\Admin\Fields\ChoicesField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Resources\Lms\QuestionResource;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Center\Models\Branch;
use App\Modules\Center\Models\Grade;
use App\Modules\Center\Models\Group;
use App\Modules\Lms\Actions\EnrollStudent;
use App\Modules\Lms\Actions\StartQuizAttempt;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\Question;
use App\Modules\Lms\Models\Quiz;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

/*
 | مثال مدرسة الرياضيات.
 |
 | السيناريو ليس عرضاً: مدرّسة تُعطي أونلاين فردياً ومجموعةً وفي
 | بيتها وفي سنترين لا تملكهما، ومادتها معادلات لا جُمَل. كل ما
 | كشفه من نقص مكتوب هنا حتى لا يعود.
 */

// ------------------------------------------------------------------
// الخلل الذي كشفه السيناريو: التحرير كان يمحو الترجمات
// ------------------------------------------------------------------

it('يفتح الحقل المترجَم بقيمته لا فارغاً', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $question = Question::create([
            'body' => ['ar' => 'أوجد $x$', 'en' => 'Find $x$'],
            'type' => 'single_choice',
        ]);

        $value = TranslatableField::make('body')->valueFor($question);

        /*
         | `getAttribute` على حقل مترجَم يُعيد نصّ لغة العرض — وهو
         | صواب في العرض وكارثة في التحرير: الشاشة كانت تفتح فارغة،
         | فمن حفظ بعد تعديل الصعوبة وحدها محا نصّ السؤال بلغتيه.
         */
        expect($value)->toBeArray()
            ->and($value['ar'] ?? null)->toBe('أوجد $x$')
            ->and($value['en'] ?? null)->toBe('Find $x$');
    });
});

it('لا يمحو حفظُ شاشةِ التحرير عنوانَ الكورس بلغاته', function (): void {
    $tenant = provision();

    $id = $tenant->run(fn (): int => (int) seedCourse([
        'slug' => 'algebra', 'title' => ['ar' => 'الجبر', 'en' => 'Algebra'],
    ])->getKey());

    tenancy()->initialize($tenant);
    actingAsOwner($tenant);

    // نقرأ الشاشة كما يقرأها المتصفّح، ثم نُرسل ما فيها كما يُرسله
    tenantGet($tenant, '/admin/courses/'.$id.'/edit')
        ->assertOk()
        ->assertSee('الجبر', false)
        ->assertSee('Algebra', false);

    $tenant->run(function () use ($id): void {
        $course = Course::find($id);

        expect($course->getTranslations('title'))
            ->toBe(['ar' => 'الجبر', 'en' => 'Algebra']);
    });
});

// ------------------------------------------------------------------
// الخيارات والإجابة: بنك أسئلة يُكتب من شاشته
// ------------------------------------------------------------------

it('يحفظ الخيارات وإجابتها الصحيحة من شاشة واحدة', function (): void {
    $tenant = provision();

    tenancy()->initialize($tenant);
    actingAsOwner($tenant);

    tenantPost($tenant, '/admin/questions', [
        'body' => ['ar' => 'حلّ: $5x = 45$'],
        'type' => 'single_choice',
        'difficulty' => 'easy',
        'marks' => 1,
        'options' => [
            ['key' => 'a', 'text' => '$x = 9$', 'correct' => '1'],
            ['key' => 'b', 'text' => '$x = 40$', 'correct' => ''],
            ['key' => 'c', 'text' => '', 'correct' => ''],
        ],
        'steps' => ['ar' => implode("\n", ['نقسم على $5$', '$x = 9$'])],
    ])->assertRedirect();

    $tenant->run(function (): void {
        $question = Question::latest('id')->firstOrFail();

        // الخيار الفارغ لا يُحفظ، والصواب يشير إلى مفتاح قائم
        expect($question->options)->toBe(['a' => '$x = 9$', 'b' => '$x = 40$'])
            ->and($question->correct)->toBe(['a'])
            ->and($question->getTranslation('steps', 'ar'))->toContain('$x = 9$')
            ->and($question->grade('a'))->toBeTrue()
            ->and($question->grade('b'))->toBeFalse();
    });
});

it('لا يترك حقلُ الخيارات إجابةً صحيحة تشير إلى خيار محذوف', function (): void {
    $input = [
        ['key' => 'a', 'text' => 'أول', 'correct' => ''],
        ['key' => 'b', 'text' => '', 'correct' => '1'],
        ['key' => 'c', 'text' => 'ثالث', 'correct' => '1'],
    ];

    $field = ChoicesField::make('options');

    expect($field->fill($input))->toBe(['a' => 'أول', 'c' => 'ثالث'])
        ->and(ChoicesField::correctFrom($input))->toBe(['c']);
});

// ------------------------------------------------------------------
// الاختبار المولَّد بخلطة صعوبات
// ------------------------------------------------------------------

it('يبني الاختبار بخلطة صعوبات لا بمستوى واحد', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        foreach (['easy' => 8, 'medium' => 6, 'hard' => 4] as $difficulty => $count) {
            foreach (range(1, $count) as $n) {
                Question::create([
                    'body' => ['ar' => $difficulty.'-'.$n],
                    'type' => 'single_choice',
                    'difficulty' => $difficulty,
                    'options' => ['a' => '1', 'b' => '2'],
                    'correct' => ['a'],
                ]);
            }
        }

        $quiz = Quiz::create([
            'title' => ['ar' => 'شهري'],
            'type' => 'dynamic',
            'questions_count' => 10,
            'question_pool' => ['easy' => 5, 'medium' => 3, 'hard' => 2],
        ]);

        $mix = $quiz->questionsForAttempt()->groupBy('difficulty')->map->count();

        expect($quiz->questionsForAttempt())->toHaveCount(10)
            ->and($mix['easy'])->toBe(5)
            ->and($mix['medium'])->toBe(3)
            ->and($mix['hard'])->toBe(2);

        // ورقتان لا تتطابقان — أضعف ما يمكن فعله ضد الغش
        $first = $quiz->questionsForAttempt()->pluck('id')->sort()->values()->all();
        $second = $quiz->questionsForAttempt()->pluck('id')->sort()->values()->all();

        expect($first)->not->toBe($second);
    });
});

it('يُعلن نقص البنك قبل أن يكتشفه الطلاب في ورقة ناقصة', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        Question::create(['body' => ['ar' => 'س'], 'type' => 'essay', 'difficulty' => 'hard']);

        $quiz = Quiz::create([
            'title' => ['ar' => 'ناقص'],
            'type' => 'dynamic',
            'question_pool' => ['hard' => 5],
        ]);

        expect($quiz->poolShortfall())
            ->toBe([['difficulty' => 'hard', 'wanted' => 5, 'available' => 1]]);
    });
});

it('لا تحمل لقطة المحاولة الإجابة الصحيحة', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $course = seedCourse();
        $quiz = seedQuiz($course);
        $student = seedStudent('pupil@math.test');
        $enrollment = app(EnrollStudent::class)->handle($student, $course, 'free');

        $attempt = app(StartQuizAttempt::class)->handle($enrollment, $quiz);

        foreach ($attempt->snapshot as $frozen) {
            // الحلّ في اللقطة يعني تسريبه لمن يفتح أدوات المتصفّح وهو يمتحن
            expect($frozen)->not->toHaveKey('correct')
                ->and($frozen)->toHaveKey('steps');
        }
    });
});

// ------------------------------------------------------------------
// المجموعة: مكان وشكل
// ------------------------------------------------------------------

it('يقبل النموذج مجموعة أونلاين بلا فرع', function (): void {
    $tenant = provision(['platform_mode' => 'teacher', 'plan_key' => 'professional']);

    $tenant->run(function (): void {
        $group = Group::create([
            'name' => ['ar' => 'أونلاين فردي — أحمد'],
            'venue' => 'online', 'kind' => 'private', 'capacity' => 1,
            'currency' => 'EGP', 'price_minor' => 60000, 'price_type' => 'per_session',
            'meeting_url' => 'https://meet.example.test/x',
            'status' => 'running',
        ]);

        // الفرع كان إلزامياً، والمجموعة الأونلاين لا فرع لها
        expect($group->branch_id)->toBeNull()
            ->and($group->isOnline())->toBeTrue()
            ->and($group->isPrivate())->toBeTrue()
            ->and($group->venueLabel())->toBe('أونلاين');
    });
});

it('يذكر الدرسُ في البيت عنوانَه لا كلمة «البيت» وحدها', function (): void {
    $tenant = provision(['platform_mode' => 'teacher', 'plan_key' => 'professional']);

    $tenant->run(function (): void {
        $group = Group::create([
            'name' => ['ar' => 'في البيت — سلمى'],
            'venue' => 'home', 'kind' => 'private', 'capacity' => 1,
            'currency' => 'EGP', 'status' => 'running',
            'location' => 'المعادي — شارع ٩',
        ]);

        expect($group->venueLabel())->toBe('المعادي — شارع ٩');
    });
});

// ------------------------------------------------------------------
// السيناريو كاملاً كما وصفه صاحب المشروع
// ------------------------------------------------------------------

it('يزرع مثال مدرسة الرياضيات بالأعداد المطلوبة', function (): void {
    $this->artisan('demo:math-school', ['--slug' => 'mathtest', '--fresh' => true])
        ->assertSuccessful();

    $tenant = Tenant::where('slug', 'mathtest')->firstOrFail();

    $tenant->run(function (): void {
        expect(Grade::count())->toBe(8);

        $count = fn (string $venue, string $kind): int => (int) Group::where('venue', $venue)
            ->where('kind', $kind)->sum('enrolled_count');

        expect($count('online', 'private'))->toBe(14)
            ->and($count('online', 'group'))->toBe(5)
            ->and($count('home', 'private'))->toBe(9);

        /*
         | السنتران ليسا فرعين: المدرّسة تُدرّس فيهما ولا تملكهما.
         | فلا صفّ في center_branches، والمكان اسم على المجموعة.
         */
        $byCenter = Group::where('venue', 'center')
            ->selectRaw('location, count(*) as total')
            ->groupBy('location')->pluck('total', 'location');

        expect((int) $byCenter['سنتر فيوتشر'])->toBe(3)
            ->and((int) $byCenter['سنتر جيل مبدع'])->toBe(4)
            ->and(Group::whereNotNull('branch_id')->count())->toBe(0)
            ->and(Branch::count())->toBe(0);

        // لكل مجموعة حصص مولَّدة — لا مجموعة بلا حصة يُؤخذ فيها حضور
        expect(Group::doesntHave('sessions')->count())->toBe(0);

        // بنك الأسئلة يغطّي الخلطة التي يطلبها الاختبار
        expect(Quiz::first()->poolShortfall())->toBe([]);
    });

    $tenant->delete();
});

// ------------------------------------------------------------------
// محرّر المعادلات المرئي
// ------------------------------------------------------------------

it('يعرض لوحة الرموز في شاشة السؤال ولا يعرضها حيث لا معادلة', function (): void {
    $tenant = provision();

    tenancy()->initialize($tenant);
    actingAsOwner($tenant);

    // بنك الأسئلة يقبل معادلات فتظهر اللوحة
    tenantGet($tenant, '/admin/questions/create')
        ->assertOk()
        ->assertSee('math-palette', false)
        ->assertSee(__('محرّر المعادلات'), false);

    // الكوبونات لا معادلة فيها — واللوحة عبءٌ بلا معنى
    tenantGet($tenant, '/admin/coupons/create')
        ->assertOk()
        ->assertDontSee('math-palette', false);
});

it('يضع علامة المعادلات على الحقل الذي أعلنها وحده', function (): void {
    // النموذج يبني خياراته من جداول المشترك، فلا يُقرأ خارج سياقه
    provision()->run(function (): void {
        $fields = collect(app(QuestionResource::class)->form())
            ->flatMap(fn ($section) => $section->getFields())
            ->keyBy(fn ($field) => $field->name);

        // نصّ السؤال وخطواته وشرحه معادلات، ودرجته رقم
        expect($fields['body']->acceptsMath())->toBeTrue()
            ->and($fields['steps']->acceptsMath())->toBeTrue()
            ->and($fields['explanation']->acceptsMath())->toBeTrue()
            ->and($fields['marks']->acceptsMath())->toBeFalse()
            ->and($fields['difficulty']->acceptsMath())->toBeFalse();
    });
});

it('يحمل كل رمز في اللوحة صياغته ومعاينته واسمه', function (): void {
    $groups = config('math-symbols.groups', []);

    expect($groups)->not->toBeEmpty();

    foreach ($groups as $key => $group) {
        expect($group)->toHaveKeys(['label', 'icon', 'symbols'], "المجموعة [{$key}]")
            ->and($group['symbols'])->not->toBeEmpty("المجموعة [{$key}] بلا رموز");

        foreach ($group['symbols'] as $index => $symbol) {
            expect($symbol)->toHaveKeys(['tex', 'preview', 'label'], "الرمز [{$key}.{$index}]")
                ->and(trim($symbol['tex']))->not->toBe('')
                ->and(trim($symbol['label']))->not->toBe('');
        }
    }

    foreach (config('math-symbols.templates', []) as $index => $template) {
        expect($template)->toHaveKeys(['tex', 'preview', 'label'], "القالب [{$index}]")
            // القالب معادلة كاملة بعلامتيها، والرمز جزءٌ منها
            ->and(trim($template['tex']))->toStartWith('$');
    }
});

it('لا يبتلع تعريفُ الرمز شرطةً مائلة', function (): void {
    /*
     | صياغة TeX مليئة بـ`\\`، والنصّ ذو العلامة الواحدة في PHP
     | يبتلع واحدة من كل اثنتين. فـ`a\\\\c` صارت `a\\c` في أيقونة
     | المصفوفة، وعجز المحرّك عن رسمها — ولا يظهر ذلك في أي اختبار
     | PHP، إنما مربّعاً أحمر أمام المدرّس.
     */
    $all = collect(config('math-symbols.groups', []))
        ->flatMap(fn (array $group): array => [
            ['tex' => $group['icon'], 'label' => 'أيقونة '.$group['label']],
            ...$group['symbols'],
        ])
        ->concat(config('math-symbols.templates', []));

    foreach ($all as $symbol) {
        foreach ([$symbol['tex'], $symbol['preview'] ?? ''] as $tex) {
            // فاصل السطر في TeX شرطتان لا واحدة
            expect($tex)->not->toMatch('/(?<!\\\\)\\\\(?![a-zA-Z\\\\{}\\[\\],;:!\\s%&_^$#])/',
                "صياغة مكسورة في [{$symbol['label']}]: {$tex}");
        }
    }
});
