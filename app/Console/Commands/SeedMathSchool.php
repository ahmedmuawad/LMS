<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Onboarding\OnboardingWizard;
use App\Core\Tenancy\Actions\ProvisionTenant;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Center\Actions\EnrolStudent;
use App\Modules\Center\Actions\GenerateSessions;
use App\Modules\Center\Actions\IssueInvoices;
use App\Modules\Center\Actions\TakeAttendance;
use App\Modules\Center\Models\Branch;
use App\Modules\Center\Models\Cashbox;
use App\Modules\Center\Models\Grade;
use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Guardian;
use App\Modules\Center\Models\Room;
use App\Modules\Center\Models\Schedule;
use App\Modules\Center\Models\Session;
use App\Modules\Center\Models\Stage;
use App\Modules\Center\Models\Student;
use App\Modules\Center\Models\Subject;
use App\Modules\Lms\Models\Instructor;
use App\Modules\Lms\Models\Question;
use App\Modules\Lms\Models\Quiz;
use App\Modules\Lms\Models\Taxonomy;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * مدرسة رياضيات حقيقية — من الصف الأول إلى الثامن.
 *
 * هذا ليس عرضاً جميلاً: هو **اختبار للنموذج نفسه**. مدرّسة واحدة
 * تُعطي أونلاين فردي، وأونلاين مجموعة، وفي بيتها، وفي سنترين لا
 * تملكهما — فإن لم يستوعب النموذج ذلك بلا التواء، فالنموذج ناقص لا
 * السيناريو غريب.
 *
 * والرياضيات مادة تكشف: أسئلتها معادلات لا جُمَل، وإجابتها خطوات لا
 * حرف واحد. من يبني منصّة تعليم بلا معادلات يبني منصّة لغات.
 */
final class SeedMathSchool extends Command
{
    protected $signature = 'demo:math-school
        {--slug=math : نطاق المشترك}
        {--fresh : يحذف المشترك القائم ويعيد بناءه}';

    protected $description = 'يزرع مثال مدرسة رياضيات كاملاً: أونلاين وبيت وسنترين وبنك أسئلة';

    /** مجموعات السنترين: [الفرع, الصف, اليوم, الساعة, العدد] */
    private const CENTER_GROUPS = [
        ['future', 3, 6, 16, 12],
        ['future', 5, 1, 17, 15],
        ['future', 7, 3, 18, 11],
        ['creative', 2, 0, 15, 10],
        ['creative', 4, 2, 16, 13],
        ['creative', 6, 4, 17, 9],
        ['creative', 8, 5, 18, 14],
    ];

    public function handle(): int
    {
        $slug = (string) $this->option('slug');

        if ($this->option('fresh')) {
            Tenant::where('slug', $slug)->get()->each->delete();
        }

        if (Tenant::where('slug', $slug)->exists()) {
            $this->error('المشترك قائم. استعمل --fresh.');

            return self::FAILURE;
        }

        $tenant = app(ProvisionTenant::class)->handle([
            'name' => 'أكاديمية الأرقام للرياضيات',
            'slug' => $slug,
            'owner_email' => 'mona@math.test',
            'owner_name' => 'أ. منى عبد العزيز',
            'plan_key' => 'professional',
            'platform_mode' => 'hybrid',
            // بذرة تجربة لا إنتاج — كلمة مرور موحّدة تُذكر في المخرَج
            'password' => 'password',
        ]);

        $tenant->run(function (): void {
            app(OnboardingWizard::class)->complete();

            $teacher = User::where('role', 'owner')->firstOrFail();
            Instructor::create([
                'user_id' => $teacher->getKey(),
                'headline' => ['ar' => 'مدرّسة رياضيات — ابتدائي وإعدادي'],
                'bio' => ['ar' => 'أُدرّس الرياضيات من الصف الأول إلى الثامن منذ اثنتي عشرة سنة.'],
                'approved_at' => now(),
                'is_verified' => true,
                'commission_rate' => 100,
            ]);

            [$stages, $grades] = $this->seedGrades();
            $subject = Subject::create([
                'name' => ['ar' => 'الرياضيات', 'en' => 'Mathematics'],
                'stage_id' => $stages['primary']->getKey(),
                'color' => '#B45309',
                'icon' => '∑',
            ]);

            $branches = $this->seedBranches();
            $this->seedQuestionBank($grades);
            $this->seedOnlineAndHome($teacher, $subject, $grades);
            $this->seedCenters($teacher, $subject, $grades, $branches);
            $this->fillAttendanceAndFees($branches);
        });

        $this->info('تم زرع مثال مدرسة الرياضيات.');
        $this->line('  النطاق: '.$tenant->domains->first()?->domain);
        $this->line('  الدخول: mona@math.test / password');
        $this->line('  الطلاب: كلمة المرور الموحّدة password');

        return self::SUCCESS;
    }

    /**
     * ابتدائي (١–٦) وإعدادي (٧–٨) — كما تُقسَّم فعلاً لا صفوفاً مسطّحة.
     *
     * @return array{0: array<string, Stage>, 1: array<int, Grade>}
     */
    private function seedGrades(): array
    {
        $stages = [
            'primary' => Stage::create(['name' => ['ar' => 'ابتدائي', 'en' => 'Primary'], 'position' => 1]),
            'prep' => Stage::create(['name' => ['ar' => 'إعدادي', 'en' => 'Preparatory'], 'position' => 2]),
        ];

        $ordinals = [1 => 'الأول', 2 => 'الثاني', 3 => 'الثالث', 4 => 'الرابع',
            5 => 'الخامس', 6 => 'السادس', 7 => 'الأول', 8 => 'الثاني'];

        $grades = [];

        foreach (range(1, 8) as $number) {
            $isPrimary = $number <= 6;

            $grades[$number] = Grade::create([
                'stage_id' => $stages[$isPrimary ? 'primary' : 'prep']->getKey(),
                'name' => [
                    'ar' => $ordinals[$number].' '.($isPrimary ? 'ابتدائي' : 'إعدادي'),
                    'en' => 'Grade '.$number,
                ],
                'position' => $number,
            ]);
        }

        return [$stages, $grades];
    }

    /** @return array<string, Branch> */
    private function seedBranches(): array
    {
        $branches = [
            'future' => Branch::create([
                'name' => ['ar' => 'سنتر فيوتشر', 'en' => 'Future Center'],
                'code' => 'FUT', 'phone' => '0223110011', 'is_active' => true,
            ]),
            'creative' => Branch::create([
                'name' => ['ar' => 'سنتر جيل مبدع', 'en' => 'Creative Generation Center'],
                'code' => 'CRE', 'phone' => '0223220022', 'is_active' => true,
            ]),
        ];

        foreach ($branches as $key => $branch) {
            foreach (['قاعة ١', 'قاعة ٢'] as $index => $name) {
                Room::create([
                    'branch_id' => $branch->getKey(),
                    'name' => ['ar' => $name],
                    'capacity' => 20,
                ]);
            }

            Cashbox::create([
                'branch_id' => $branch->getKey(),
                'name' => ['ar' => 'خزنة '.($key === 'future' ? 'فيوتشر' : 'جيل مبدع')],
                'currency' => 'EGP', 'opening_minor' => 0, 'balance_minor' => 0,
            ]);
        }

        return $branches;
    }

    /**
     * بنك أسئلة رياضيات: معادلات وخطوات حلّ بثلاث درجات صعوبة.
     *
     * @param  array<int, Grade>  $grades
     */
    private function seedQuestionBank(array $grades): void
    {
        $categories = [];

        foreach (['الجبر', 'الهندسة', 'الكسور', 'المعادلات'] as $position => $name) {
            $categories[$name] = Taxonomy::create([
                'type' => 'question_category',
                'slug' => 'math-'.($position + 1),
                'name' => ['ar' => $name],
                'position' => $position,
            ]);
        }

        foreach ($this->questionBank() as $row) {
            Question::create([
                'body' => ['ar' => $row['body']],
                'type' => $row['type'] ?? 'single_choice',
                'difficulty' => $row['difficulty'],
                'category_id' => $categories[$row['category']]->getKey(),
                'options' => $row['options'] ?? null,
                'correct' => $row['correct'] ?? null,
                'marks' => $row['marks'] ?? 1,
                'steps' => ['ar' => $row['steps']],
                'explanation' => ['ar' => $row['why'] ?? ''],
            ]);
        }

        // اختبار يُبنى من البنك بخلطة: سهل يُطمئن، ومتوسط يفرز، وصعب يكشف
        Quiz::create([
            'title' => ['ar' => 'اختبار الرياضيات الشهري'],
            'description' => ['ar' => 'يُسحب من بنك الأسئلة، فلا يرى طالبان الورقة نفسها.'],
            'type' => 'dynamic',
            'questions_count' => 10,
            'question_pool' => ['easy' => 5, 'medium' => 3, 'hard' => 2],
            'time_limit_minutes' => 45,
            'max_attempts' => 2,
            'passing_percentage' => 60,
            'shuffle_questions' => true,
            'shuffle_answers' => true,
            'show_answers' => 'after_submit',
        ]);
    }

    /**
     * أسئلة حقيقية بمعادلات TeX وخطوات حلّ — لا نصّ عيّنة.
     *
     * @return list<array<string, mixed>>
     */
    private function questionBank(): array
    {
        return [
            [
                'category' => 'الجبر', 'difficulty' => 'easy',
                'body' => 'أوجد قيمة $x$ في المعادلة: $x + 7 = 12$',
                'options' => ['a' => '$x = 5$', 'b' => '$x = 19$', 'c' => '$x = 7$', 'd' => '$x = 12$'],
                'correct' => ['a'],
                'steps' => implode("\n", ['نطرح $7$ من الطرفين: $x + 7 - 7 = 12 - 7$', 'نُبسّط: $x = 5$', 'نتحقّق: $5 + 7 = 12$ ✓']),
                'why' => 'ما نفعله بطرف نفعله بالآخر — هذا هو ميزان المعادلة.',
            ],
            [
                'category' => 'الكسور', 'difficulty' => 'easy',
                'body' => 'احسب: $\\frac{1}{4} + \\frac{1}{2}$',
                'options' => ['a' => '$\\frac{3}{4}$', 'b' => '$\\frac{2}{6}$', 'c' => '$\\frac{1}{6}$', 'd' => '$\\frac{2}{3}$'],
                'correct' => ['a'],
                'steps' => implode("\n", ['نوحّد المقامات: $\frac{1}{2} = \frac{2}{4}$', 'نجمع البسطين: $\frac{1}{4} + \frac{2}{4} = \frac{3}{4}$']),
                'why' => 'لا تُجمع الكسور إلا بمقام واحد — جمع المقامات خطأ شائع.',
            ],
            [
                'category' => 'الهندسة', 'difficulty' => 'easy',
                'body' => 'مساحة مستطيل طوله $8\\,\\text{cm}$ وعرضه $5\\,\\text{cm}$ تساوي:',
                'options' => ['a' => '$40\\,\\text{cm}^2$', 'b' => '$13\\,\\text{cm}^2$', 'c' => '$26\\,\\text{cm}^2$', 'd' => '$45\\,\\text{cm}^2$'],
                'correct' => ['a'],
                'steps' => implode("\n", ['المساحة $= الطول \times العرض$', '$= 8 \times 5 = 40\,\text{cm}^2$']),
                'why' => 'الجواب بالسنتيمتر المربّع لا بالسنتيمتر — الوحدة جزء من الإجابة.',
            ],
            [
                'category' => 'الجبر', 'difficulty' => 'easy',
                'body' => 'إذا كان $y = 3$ فأوجد قيمة $2y + 4$',
                'options' => ['a' => '$10$', 'b' => '$7$', 'c' => '$9$', 'd' => '$12$'],
                'correct' => ['a'],
                'steps' => implode("\n", ['نعوّض: $2(3) + 4$', '$= 6 + 4$', '$= 10$']),
            ],
            [
                'category' => 'الكسور', 'difficulty' => 'easy',
                'body' => 'أيّ الكسور التالية أكبر؟',
                'options' => ['a' => '$\\frac{3}{4}$', 'b' => '$\\frac{2}{3}$', 'c' => '$\\frac{1}{2}$', 'd' => '$\\frac{5}{8}$'],
                'correct' => ['a'],
                'steps' => implode("\n", ['نوحّد المقامات على $24$:', '$\frac{3}{4} = \frac{18}{24}$ · $\frac{2}{3} = \frac{16}{24}$', '$\frac{1}{2} = \frac{12}{24}$ · $\frac{5}{8} = \frac{15}{24}$', 'أكبرها $\frac{18}{24} = \frac{3}{4}$']),
            ],
            [
                'category' => 'المعادلات', 'difficulty' => 'easy',
                'body' => 'حلّ: $5x = 45$',
                'options' => ['a' => '$x = 9$', 'b' => '$x = 40$', 'c' => '$x = 50$', 'd' => '$x = 8$'],
                'correct' => ['a'],
                'steps' => implode("\n", ['نقسم الطرفين على $5$: $\frac{5x}{5} = \frac{45}{5}$', '$x = 9$']),
            ],
            [
                'category' => 'المعادلات', 'difficulty' => 'medium',
                'body' => 'حلّ المعادلة: $3x - 5 = 2x + 4$',
                'options' => ['a' => '$x = 9$', 'b' => '$x = 1$', 'c' => '$x = -9$', 'd' => '$x = 5$'],
                'correct' => ['a'], 'marks' => 2,
                'steps' => implode("\n", ['نجمع الحدود المتشابهة: $3x - 2x = 4 + 5$', '$x = 9$', 'نتحقّق: $3(9) - 5 = 22$ و $2(9) + 4 = 22$ ✓']),
                'why' => 'المجهول في طرف والأعداد في الطرف الآخر — ثم نُبسّط.',
            ],
            [
                'category' => 'الهندسة', 'difficulty' => 'medium',
                'body' => 'في مثلث قائم الزاوية، الضلعان القائمان $3$ و $4$. ما طول الوتر؟',
                'options' => ['a' => '$5$', 'b' => '$7$', 'c' => '$12$', 'd' => '$\\sqrt{7}$'],
                'correct' => ['a'], 'marks' => 2,
                'steps' => implode("\n", ['بنظرية فيثاغورس: $c^2 = a^2 + b^2$', '$c^2 = 3^2 + 4^2 = 9 + 16 = 25$', '$c = \sqrt{25} = 5$']),
                'why' => 'الوتر أطول أضلاع المثلث القائم دائماً — فإجابة أصغر من $4$ خطأ بلا حساب.',
            ],
            [
                'category' => 'الجبر', 'difficulty' => 'medium',
                'body' => 'بسّط المقدار: $2(x + 3) - 4x$',
                'options' => ['a' => '$-2x + 6$', 'b' => '$6x + 6$', 'c' => '$-2x + 3$', 'd' => '$2x - 6$'],
                'correct' => ['a'], 'marks' => 2,
                'steps' => implode("\n", ['نفكّ القوس: $2x + 6 - 4x$', 'نجمع المتشابه: $(2x - 4x) + 6$', '$= -2x + 6$']),
            ],
            [
                'category' => 'الكسور', 'difficulty' => 'medium',
                'body' => 'احسب: $\\frac{2}{3} \\times \\frac{9}{4}$',
                'options' => ['a' => '$\\frac{3}{2}$', 'b' => '$\\frac{11}{12}$', 'c' => '$\\frac{18}{7}$', 'd' => '$\\frac{8}{27}$'],
                'correct' => ['a'], 'marks' => 2,
                'steps' => implode("\n", ['نضرب البسط في البسط والمقام في المقام: $\frac{2 \times 9}{3 \times 4} = \frac{18}{12}$', 'نختصر بالقسمة على $6$: $\frac{3}{2}$']),
            ],
            [
                'category' => 'المعادلات', 'difficulty' => 'hard',
                'body' => 'أوجد جذرَي المعادلة: $x^2 - 5x + 6 = 0$',
                'type' => 'multiple_choice',
                'options' => ['a' => '$x = 2$', 'b' => '$x = 3$', 'c' => '$x = -2$', 'd' => '$x = 6$'],
                'correct' => ['a', 'b'], 'marks' => 3,
                'steps' => implode("\n", ['نحلّل إلى عاملين حاصل ضربهما $6$ ومجموعهما $-5$: هما $-2$ و $-3$', '$(x - 2)(x - 3) = 0$', 'إذاً $x - 2 = 0$ أو $x - 3 = 0$', '$x = 2$ أو $x = 3$']),
                'why' => 'حاصل الضرب صفر يعني أن أحد العاملين صفر — هذه هي الفكرة كلها.',
            ],
            [
                'category' => 'الهندسة', 'difficulty' => 'hard',
                'body' => 'دائرة نصف قطرها $7\\,\\text{cm}$. احسب مساحتها بالتقريب لأقرب عدد صحيح $(\\pi \\approx \\frac{22}{7})$',
                'options' => ['a' => '$154\\,\\text{cm}^2$', 'b' => '$44\\,\\text{cm}^2$', 'c' => '$49\\,\\text{cm}^2$', 'd' => '$22\\,\\text{cm}^2$'],
                'correct' => ['a'], 'marks' => 3,
                'steps' => implode("\n", ['المساحة $= \pi r^2$', '$= \frac{22}{7} \times 7^2 = \frac{22}{7} \times 49$', '$= 22 \times 7 = 154\,\text{cm}^2$']),
                'why' => 'من يخلط بين المساحة والمحيط يحصل على $44$ — والفرق أن المساحة تربّع نصف القطر.',
            ],
            [
                'category' => 'الجبر', 'difficulty' => 'hard',
                'body' => 'حلّ المتباينة: $\\frac{2x - 1}{3} \\geq 5$',
                'options' => ['a' => '$x \\geq 8$', 'b' => '$x \\leq 8$', 'c' => '$x \\geq 7$', 'd' => '$x > 8$'],
                'correct' => ['a'], 'marks' => 3,
                'steps' => implode("\n", ['نضرب الطرفين في $3$: $2x - 1 \geq 15$', 'نضيف $1$: $2x \geq 16$', 'نقسم على $2$: $x \geq 8$']),
                'why' => 'الضرب في عدد موجب لا يقلب إشارة المتباينة — والقسمة على سالب تقلبها.',
            ],
        ];
    }

    /**
     * الأونلاين والبيت: ١٤ فردي أونلاين، ومجموعة أونلاين بخمسة،
     * وتسعة فردي في البيت.
     *
     * الفردي مجموعة سعتها واحد — لا كيان ثالث: الحصة والحضور
     * والفاتورة تعمل كما هي، ولو صنعنا للفردي جدولاً خاصاً لصار
     * لكل شاشة فرعان.
     *
     * @param  array<int, Grade>  $grades
     */
    private function seedOnlineAndHome(User $teacher, Subject $subject, array $grades): void
    {
        $onlinePrivate = [
            ['أحمد سامي', 1], ['ملك حسام', 2], ['زياد عمرو', 2], ['جنى محمود', 3],
            ['يحيى شريف', 3], ['ريتاج أيمن', 4], ['عمر ياسر', 4], ['نور خالد', 5],
            ['مروان هشام', 5], ['تالة إسلام', 6], ['سيف الدين', 6], ['لُجين وليد', 7],
            ['آسر مصطفى', 7], ['رقيّة عادل', 8],
        ];

        foreach ($onlinePrivate as $index => [$name, $gradeNumber]) {
            $group = $this->makeGroup([
                'name' => ['ar' => 'أونلاين فردي — '.$name],
                'venue' => 'online', 'kind' => 'private', 'capacity' => 1,
                'price_minor' => 60000, 'price_type' => 'per_session',
                'meeting_url' => 'https://meet.example.test/'.($index + 1).'-private',
            ], $teacher, $subject, $grades[$gradeNumber]);

            $this->makeSchedule($group, weekday: $index % 7, hour: 15 + ($index % 4));

            $student = $this->makeStudent($name, 'online'.($index + 1), $grades[$gradeNumber], null);
            app(EnrolStudent::class)->handle($student, $group->refresh());
        }

        $onlineGroup = $this->makeGroup([
            'name' => ['ar' => 'أونلاين مجموعة — رابع ابتدائي'],
            'venue' => 'online', 'kind' => 'group', 'capacity' => 8,
            'price_minor' => 35000, 'price_type' => 'monthly',
            'meeting_url' => 'https://meet.example.test/g4-online',
        ], $teacher, $subject, $grades[4]);

        $this->makeSchedule($onlineGroup, weekday: 2, hour: 19);

        foreach (['هنا عصام', 'كنزي رامي', 'أدهم فادي', 'سلمى نبيل', 'يوسف ماهر'] as $index => $name) {
            $student = $this->makeStudent($name, 'og'.($index + 1), $grades[4], null);
            app(EnrolStudent::class)->handle($student, $onlineGroup->refresh());
        }

        $homePrivate = [
            ['فريدة تامر', 1], ['بدر أنور', 2], ['حور محمد', 3], ['كريم صبري', 4],
            ['ميرنا أشرف', 5], ['طه إبراهيم', 6], ['جودي رأفت', 7], ['مصطفى علاء', 8],
            ['ليان عصام', 8],
        ];

        foreach ($homePrivate as $index => [$name, $gradeNumber]) {
            $group = $this->makeGroup([
                'name' => ['ar' => 'في البيت — '.$name],
                'venue' => 'home', 'kind' => 'private', 'capacity' => 1,
                'price_minor' => 80000, 'price_type' => 'per_session',
                'location' => 'منزل الطالب — يُتّفق على العنوان مع ولي الأمر',
            ], $teacher, $subject, $grades[$gradeNumber]);

            $this->makeSchedule($group, weekday: ($index + 3) % 7, hour: 16 + ($index % 3));

            $student = $this->makeStudent($name, 'home'.($index + 1), $grades[$gradeNumber], null);
            app(EnrolStudent::class)->handle($student, $group->refresh());
        }
    }

    /**
     * السنتران: فيوتشر ٣ مجموعات، وجيل مبدع ٤ — لمراحل مختلفة.
     *
     * @param  array<int, Grade>  $grades
     * @param  array<string, Branch>  $branches
     */
    private function seedCenters(User $teacher, Subject $subject, array $grades, array $branches): void
    {
        $counter = 0;

        foreach (self::CENTER_GROUPS as [$branchKey, $gradeNumber, $weekday, $hour, $size]) {
            $branch = $branches[$branchKey];
            $grade = $grades[$gradeNumber];

            $group = $this->makeGroup([
                'name' => ['ar' => (string) $grade->name.' — '.$branch->name],
                'venue' => 'branch', 'kind' => 'group', 'capacity' => 20,
                'price_minor' => 30000, 'price_type' => 'monthly',
                'branch_id' => $branch->getKey(),
            ], $teacher, $subject, $grade);

            $this->makeSchedule($group, $weekday, $hour, Room::where('branch_id', $branch->getKey())->inRandomOrder()->value('id'));

            foreach (range(1, $size) as $n) {
                $counter++;

                $student = $this->makeStudent(
                    $this->pupilName($counter),
                    'c'.$counter,
                    $grade,
                    $branch,
                );

                app(EnrolStudent::class)->handle($student, $group->refresh());
            }
        }
    }

    /** @param  array<string, mixed>  $attributes */
    private function makeGroup(array $attributes, User $teacher, Subject $subject, Grade $grade): Group
    {
        return Group::create([
            'subject_id' => $subject->getKey(),
            'grade_id' => $grade->getKey(),
            'teacher_id' => $teacher->getKey(),
            'currency' => 'EGP',
            'status' => 'running',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'color' => '#B45309',
            ...$attributes,
        ]);
    }

    private function makeSchedule(Group $group, int $weekday, int $hour, ?int $roomId = null): void
    {
        Schedule::create([
            'group_id' => $group->getKey(),
            'room_id' => $roomId,
            'weekday' => $weekday,
            'starts_at' => sprintf('%02d:00:00', $hour),
            'ends_at' => sprintf('%02d:30:00', $hour + 1),
        ]);

        app(GenerateSessions::class)->handle(
            $group,
            now()->startOfWeek(Carbon::SUNDAY)->subWeeks(2),
            now()->addWeeks(2),
        );
    }

    private function makeStudent(string $name, string $handle, Grade $grade, ?Branch $branch): Student
    {
        $user = User::create([
            'name' => $name,
            'email' => $handle.'@math.test',
            'phone' => '011'.str_pad((string) (crc32($handle) % 100000000), 8, '0', STR_PAD_LEFT),
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $student = Student::create([
            'user_id' => $user->getKey(),
            'code' => Student::nextCode(),
            'branch_id' => $branch?->getKey(),
            'stage_id' => $grade->stage_id,
            'grade_id' => $grade->getKey(),
            'joined_at' => now()->subMonths(random_int(1, 6))->toDateString(),
            'status' => 'active',
        ]);

        // ولي أمر لكل طالب: بوابة ولي الأمر بلا أولياء أمور شاشة فارغة
        $guardian = Guardian::create([
            'name' => 'ولي أمر '.$name,
            'relation' => random_int(0, 1) === 1 ? 'الأب' : 'الأم',
            'phone' => '010'.str_pad((string) (crc32('g'.$handle) % 100000000), 8, '0', STR_PAD_LEFT),
        ]);

        $guardian->students()->attach($student->getKey(), ['is_primary' => true]);

        return $student;
    }

    private function pupilName(int $n): string
    {
        $first = ['محمد', 'مريم', 'علي', 'فاطمة', 'حسن', 'زينب', 'إبراهيم', 'خديجة',
            'عبد الرحمن', 'أسماء', 'طارق', 'هند', 'سامي', 'رنا', 'باسل', 'دُعاء'];
        $last = ['السيد', 'عبد الله', 'فتحي', 'رضوان', 'الشناوي', 'حجازي', 'مرسي', 'زكي'];

        return $first[$n % count($first)].' '.$last[intdiv($n, count($first)) % count($last)];
    }

    /** @param  array<string, Branch>  $branches */
    private function fillAttendanceAndFees(array $branches): void
    {
        foreach (Group::all() as $group) {
            app(IssueInvoices::class)->handle($group, now()->format('Y-m'));
        }

        foreach (Session::whereDate('date', '<', now())->with('group')->get() as $session) {
            $statuses = [];

            foreach ($session->group->enrollments()->active()->pluck('student_id') as $studentId) {
                // الأونلاين يُسجَّل «أونلاين» لا «حاضر»: التمييز يُغيّر التقرير
                $statuses[$studentId] = match (true) {
                    random_int(1, 10) > 9 => 'absent',
                    $session->group->isOnline() => 'online',
                    default => 'present',
                };
            }

            if ($statuses !== []) {
                app(TakeAttendance::class)->handle($session, $statuses);
            }
        }
    }
}
