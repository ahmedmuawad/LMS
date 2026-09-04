<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Onboarding\OnboardingWizard;
use App\Core\Tenancy\Actions\ProvisionTenant;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Center\Actions\CollectPayment;
use App\Modules\Center\Actions\DetectConflicts;
use App\Modules\Center\Actions\EnrolStudent;
use App\Modules\Center\Actions\GenerateSessions;
use App\Modules\Center\Actions\IssueInvoices;
use App\Modules\Center\Actions\TakeAttendance;
use App\Modules\Center\Models\Branch;
use App\Modules\Center\Models\Cashbox;
use App\Modules\Center\Models\Grade;
use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Guardian;
use App\Modules\Center\Models\Invoice;
use App\Modules\Center\Models\Room;
use App\Modules\Center\Models\Schedule;
use App\Modules\Center\Models\Session;
use App\Modules\Center\Models\Stage;
use App\Modules\Center\Models\Student;
use App\Modules\Center\Models\Subject;
use App\Modules\Center\Models\SubjectTeacher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * سنتر تعليمي حقيقي — قاعات ومواد ومدرّسون لكل مادة.
 *
 * السنتر لا يُدار بمجموعة ومدرّس: فيه ستّ قاعات وخمس مواد، ولكل
 * مادة ثلاثة مدرّسين، ولكل مدرّس مواعيده وطلبته. والقيد الحاكم
 * واحد: **القاعة لا تُحجز مرتين في الوقت نفسه** — وهذه البذرة
 * تبني جدولاً كاملاً يحترمه، وتفشل صراحةً إن خرقته.
 */
final class SeedCenterSchool extends Command
{
    protected $signature = 'demo:center-school
        {--slug=sanad : نطاق المشترك}
        {--fresh : يحذف المشترك القائم ويعيد بناءه}';

    protected $description = 'يزرع سنتراً كاملاً: قاعات ومواد ومدرّسون ومواعيد بلا تعارض';

    /** المواد ولون كلٍّ منها ومدرّسوها. */
    private const SUBJECTS = [
        'الرياضيات' => ['#B45309', ['أ. منى عبد العزيز', 'أ. طارق شعبان', 'أ. هالة فؤاد']],
        'الفيزياء' => ['#1F6FEB', ['أ. سامي الديب', 'أ. نادية رزق']],
        'الكيمياء' => ['#15803D', ['أ. وليد عاشور', 'أ. إيمان صادق']],
        'اللغة الإنجليزية' => ['#7C3AED', ['أ. مايكل نبيل', 'أ. دينا حلمي', 'أ. شريف مجدي']],
        'اللغة العربية' => ['#BE123C', ['أ. عبد الرحمن قاسم', 'أ. سلوى الحسيني']],
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
            'name' => 'سنتر سند التعليمي',
            'slug' => $slug,
            'owner_email' => 'admin@sanad.test',
            'owner_name' => 'إدارة سنتر سند',
            'plan_key' => 'center',
            'platform_mode' => 'center',
            'password' => 'password',
        ]);

        $clashes = 0;

        $tenant->run(function () use (&$clashes): void {
            app(OnboardingWizard::class)->complete();

            $branch = $this->seedBranch();
            $rooms = $this->seedRooms($branch);
            [$stages, $grades] = $this->seedGrades();
            $subjects = $this->seedSubjects($stages, $branch);

            $clashes = $this->seedGroupsAndSlots($branch, $rooms, $grades, $subjects);

            $this->seedStudents($branch, $grades);
            $this->seedOperations($branch);
        });

        $this->info('تم زرع سنتر سند.');
        $this->line('  النطاق: '.$tenant->domains->first()?->domain);
        $this->line('  الدخول: admin@sanad.test / password');
        $this->line('  المدرّسون والطلاب: كلمة المرور الموحّدة password');

        if ($clashes > 0) {
            // البذرة لا تكذب: جدولٌ فيه تعارض يجب أن يُقال لا أن يُزرَع
            $this->warn("  تُخطّيت {$clashes} موعداً لتعارض — الجدول أضيق من أن يسعها.");
        }

        return self::SUCCESS;
    }

    private function seedBranch(): Branch
    {
        $branch = Branch::create([
            'name' => ['ar' => 'سنتر سند — المقر الرئيسي', 'en' => 'Sanad Center'],
            'code' => 'SND', 'phone' => '0225550101', 'whatsapp' => '201005550101',
            'is_active' => true,
        ]);

        Cashbox::create([
            'branch_id' => $branch->getKey(),
            'name' => ['ar' => 'خزنة الاستقبال'],
            'currency' => 'EGP', 'opening_minor' => 0, 'balance_minor' => 0,
        ]);

        return $branch;
    }

    /** @return Collection<int, Room> */
    private function seedRooms(Branch $branch)
    {
        $rooms = [
            ['قاعة النيل', 24, ['سبورة ذكية', 'بروجكتور']],
            ['قاعة الأمل', 20, ['سبورة', 'مكيّف']],
            ['قاعة المستقبل', 18, ['بروجكتور']],
            ['قاعة الإبداع', 16, ['سبورة ذكية']],
            ['معمل الفيزياء', 14, ['أجهزة معمل', 'حوض']],
            ['معمل الكيمياء', 12, ['شفّاط', 'أجهزة معمل']],
        ];

        return collect($rooms)->map(fn (array $row): Room => Room::create([
            'branch_id' => $branch->getKey(),
            'name' => ['ar' => $row[0]],
            'capacity' => $row[1],
            'equipment' => $row[2],
            'is_active' => true,
        ]));
    }

    /** @return array{0: array<string, Stage>, 1: array<int, Grade>} */
    private function seedGrades(): array
    {
        $stages = [
            'prep' => Stage::create(['name' => ['ar' => 'إعدادي', 'en' => 'Preparatory'], 'position' => 1]),
            'secondary' => Stage::create(['name' => ['ar' => 'ثانوي', 'en' => 'Secondary'], 'position' => 2]),
        ];

        $names = [
            1 => ['prep', 'الأول الإعدادي'], 2 => ['prep', 'الثاني الإعدادي'], 3 => ['prep', 'الثالث الإعدادي'],
            4 => ['secondary', 'الأول الثانوي'], 5 => ['secondary', 'الثاني الثانوي'], 6 => ['secondary', 'الثالث الثانوي'],
        ];

        $grades = [];

        foreach ($names as $position => [$stage, $name]) {
            $grades[$position] = Grade::create([
                'stage_id' => $stages[$stage]->getKey(),
                'name' => ['ar' => $name],
                'position' => $position,
            ]);
        }

        return [$stages, $grades];
    }

    /**
     * كل مادة ومدرّسوها — والإسناد صريح لا مستنتَج.
     *
     * @param  array<string, Stage>  $stages
     * @return array<string, array{subject: Subject, teachers: list<User>}>
     */
    private function seedSubjects(array $stages, Branch $branch): array
    {
        $out = [];
        $index = 0;

        foreach (self::SUBJECTS as $name => [$color, $teacherNames]) {
            $subject = Subject::create([
                'name' => ['ar' => $name],
                'stage_id' => $stages['secondary']->getKey(),
                'color' => $color,
                'is_active' => true,
            ]);

            $teachers = [];

            foreach ($teacherNames as $teacherName) {
                $index++;

                $teacher = User::create([
                    'name' => $teacherName,
                    'email' => 'teacher'.$index.'@sanad.test',
                    'phone' => '0100'.str_pad((string) (5550000 + $index), 7, '0', STR_PAD_LEFT),
                    'password' => Hash::make('password'),
                    'role' => 'instructor',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

                SubjectTeacher::create([
                    'subject_id' => $subject->getKey(),
                    'user_id' => $teacher->getKey(),
                    'branch_id' => $branch->getKey(),
                    'share_percent' => 60,
                    'is_active' => true,
                ]);

                $teachers[] = $teacher;
            }

            $out[$name] = ['subject' => $subject, 'teachers' => $teachers];
        }

        return $out;
    }

    /**
     * لكل مدرّس مجموعتان بموعدين — والقاعة تُختار بالفحص لا بالحظّ.
     *
     * هذا قلب السيناريو: نطلب من `DetectConflicts` قبل كل موعد، فلا
     * تُحجز قاعة مرتين. وما لا يجد قاعة يُعَدّ ويُقال في المخرَج.
     *
     * @param  Collection<int, Room>  $rooms
     * @param  array<int, Grade>  $grades
     * @param  array<string, array{subject: Subject, teachers: list<User>}>  $subjects
     */
    private function seedGroupsAndSlots(Branch $branch, $rooms, array $grades, array $subjects): int
    {
        $conflicts = app(DetectConflicts::class);
        $skipped = 0;
        $seed = 0;

        // معامل الفيزياء والكيمياء لموادّهما، والباقي قاعات عامة
        $preferred = [
            'الفيزياء' => $rooms->where('name', 'معمل الفيزياء')->values(),
            'الكيمياء' => $rooms->where('name', 'معمل الكيمياء')->values(),
        ];

        $general = $rooms->whereNotIn('name', ['معمل الفيزياء', 'معمل الكيمياء'])->values();

        foreach ($subjects as $name => $row) {
            foreach ($row['teachers'] as $teacher) {
                foreach ([0, 1] as $slotIndex) {
                    $seed++;

                    $grade = $grades[($seed % 6) + 1];

                    $group = Group::create([
                        'branch_id' => $branch->getKey(),
                        'subject_id' => $row['subject']->getKey(),
                        'grade_id' => $grade->getKey(),
                        'teacher_id' => $teacher->getKey(),
                        'name' => ['ar' => $name.' — '.$grade->name.' — '.$teacher->name],
                        'venue' => 'branch',
                        'kind' => 'group',
                        'capacity' => 18,
                        'currency' => 'EGP',
                        'price_minor' => 35000,
                        'price_type' => 'monthly',
                        'status' => 'running',
                        'start_date' => now()->startOfMonth()->toDateString(),
                        'end_date' => now()->addMonths(3)->toDateString(),
                        'color' => $row['subject']->color,
                    ]);

                    $placed = $this->placeSlot(
                        $conflicts,
                        $group,
                        ($preferred[$name] ?? $general)->isEmpty() ? $general : ($preferred[$name] ?? $general),
                        $general,
                        $seed,
                        $slotIndex,
                    );

                    if (! $placed) {
                        $skipped++;
                    }
                }
            }
        }

        return $skipped;
    }

    /**
     * يبحث عن (يوم × ساعة × قاعة) خالية ويحجزها.
     *
     * البحث مقصود: جدولة يدوية في بذرة تُنتج تعارضاً عند أول تغيير
     * في الأرقام، وتُخفي أن الفحص يعمل أصلاً.
     *
     * @param  Collection<int, Room>  $first
     * @param  Collection<int, Room>  $fallback
     */
    private function placeSlot(DetectConflicts $conflicts, Group $group, $first, $fallback, int $seed, int $slotIndex): bool
    {
        $candidates = $first->concat($fallback)->unique('id');

        // نبدأ من يوم وساعة مختلفين لكل مجموعة فلا يزدحم أول الجدول
        $days = [(($seed * 2) + $slotIndex * 3) % 7, ...range(0, 6)];
        $hours = [14 + (($seed + $slotIndex) % 6), ...range(14, 20)];

        foreach ($days as $weekday) {
            foreach ($hours as $hour) {
                foreach ($candidates as $room) {
                    $slot = [
                        'group_id' => (int) $group->getKey(),
                        'room_id' => (int) $room->getKey(),
                        'teacher_id' => (int) $group->teacher_id,
                        'weekday' => (int) $weekday,
                        'starts_at' => sprintf('%02d:00', $hour),
                        'ends_at' => sprintf('%02d:30', $hour + 1),
                    ];

                    if ($conflicts->forSchedule($slot) !== []) {
                        continue;
                    }

                    Schedule::create([
                        'group_id' => $group->getKey(),
                        'room_id' => $room->getKey(),
                        'weekday' => $weekday,
                        'starts_at' => $slot['starts_at'].':00',
                        'ends_at' => $slot['ends_at'].':00',
                    ]);

                    return true;
                }
            }
        }

        return false;
    }

    /** @param  array<int, Grade>  $grades */
    private function seedStudents(Branch $branch, array $grades): void
    {
        $groups = Group::with('grade')->get();
        $counter = 0;

        foreach ($groups as $group) {
            foreach (range(1, random_int(8, 15)) as $n) {
                $counter++;

                $user = User::create([
                    'name' => $this->pupilName($counter),
                    'email' => 'pupil'.$counter.'@sanad.test',
                    'phone' => '011'.str_pad((string) (crc32('p'.$counter) % 100000000), 8, '0', STR_PAD_LEFT),
                    'password' => Hash::make('password'),
                    'role' => 'student',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

                $student = Student::create([
                    'user_id' => $user->getKey(),
                    'code' => Student::nextCode(),
                    'branch_id' => $branch->getKey(),
                    'stage_id' => $group->grade?->stage_id,
                    'grade_id' => $group->grade_id,
                    'school' => 'مدرسة '.['النيل', 'الأورمان', 'الحرية', 'طيبة'][$counter % 4].' الرسمية',
                    'joined_at' => now()->subMonths(random_int(1, 8))->toDateString(),
                    'status' => 'active',
                ]);

                $guardian = Guardian::create([
                    'name' => 'ولي أمر '.$user->name,
                    'relation' => $counter % 2 === 0 ? 'الأب' : 'الأم',
                    'phone' => '010'.str_pad((string) (crc32('g'.$counter) % 100000000), 8, '0', STR_PAD_LEFT),
                ]);

                $guardian->students()->attach($student->getKey(), ['is_primary' => true]);

                app(EnrolStudent::class)->handle($student, $group->refresh());
            }
        }
    }

    private function seedOperations(Branch $branch): void
    {
        foreach (Group::all() as $group) {
            app(GenerateSessions::class)->handle(
                $group,
                now()->startOfWeek(Carbon::SUNDAY)->subWeeks(2),
                now()->addWeeks(2),
            );

            app(IssueInvoices::class)->handle($group, now()->format('Y-m'));
        }

        foreach (Session::whereDate('date', '<', now())->with('group')->get() as $session) {
            $statuses = [];

            foreach ($session->group->enrollments()->active()->pluck('student_id') as $studentId) {
                $statuses[$studentId] = match (random_int(1, 20)) {
                    1, 2 => 'absent',
                    3 => 'late',
                    default => 'present',
                };
            }

            if ($statuses !== []) {
                app(TakeAttendance::class)->handle($session, $statuses);
            }
        }

        // تحصيل جزئي: الخزنة والمتأخرات تظهران معاً لا إحداهما
        $cashbox = Cashbox::where('branch_id', $branch->getKey())->firstOrFail();

        foreach (Invoice::limit(30)->get() as $invoice) {
            app(CollectPayment::class)->handle($invoice->student, $invoice->total(), $invoice, $cashbox, 'cash');
        }
    }

    private function pupilName(int $n): string
    {
        $first = ['أحمد', 'سارة', 'محمود', 'نور', 'خالد', 'مريم', 'يوسف', 'حبيبة',
            'عمر', 'ملك', 'مصطفى', 'جنى', 'زياد', 'رودينا', 'كريم', 'لينا'];
        $last = ['عبد الحميد', 'شلبي', 'الغزالي', 'عوض', 'بدوي', 'الطوخي', 'صبحي', 'منصور'];

        return $first[$n % count($first)].' '.$last[intdiv($n, count($first)) % count($last)];
    }
}
