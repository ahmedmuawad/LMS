<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Onboarding\OnboardingWizard;
use App\Core\Tenancy\Actions\ProvisionTenant;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Center\Actions\CollectPayment;
use App\Modules\Center\Actions\EnrolStudent;
use App\Modules\Center\Actions\GenerateSessions;
use App\Modules\Center\Actions\IssueInvoices;
use App\Modules\Center\Actions\TakeAttendance;
use App\Modules\Center\Models\Branch as CenterBranch;
use App\Modules\Center\Models\Cashbox as CenterCashbox;
use App\Modules\Center\Models\Grade as CenterGrade;
use App\Modules\Center\Models\Group as CenterGroup;
use App\Modules\Center\Models\Guardian as CenterGuardian;
use App\Modules\Center\Models\Invoice as CenterInvoice;
use App\Modules\Center\Models\Room as CenterRoom;
use App\Modules\Center\Models\Schedule as CenterSchedule;
use App\Modules\Center\Models\Session as CenterSession;
use App\Modules\Center\Models\Stage as CenterStage;
use App\Modules\Center\Models\Student as CenterStudent;
use App\Modules\Center\Models\Subject as CenterSubject;
use App\Modules\Commerce\Models\InstructorEarning;
use App\Modules\Community\Actions\PostDiscussion;
use App\Modules\Community\Actions\SubmitReview;
use App\Modules\Community\Models\Discussion;
use App\Modules\Content\Actions\InstallSystemPages;
use App\Modules\Content\Models\Form;
use App\Modules\Content\Models\Page;
use App\Modules\Content\Models\Post;
use App\Modules\Content\Models\Redirect;
use App\Modules\Gamification\Actions\AwardBadges;
use App\Modules\Growth\Models\Affiliate;
use App\Modules\Growth\Models\AffiliateConversion;
use App\Modules\Growth\Models\Campaign;
use App\Modules\Growth\Models\CampaignStep;
use App\Modules\Lms\Actions\EnrollStudent;
use App\Modules\Lms\Actions\TrackProgress;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseItem;
use App\Modules\Lms\Models\CourseSection;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\Instructor;
use App\Modules\Lms\Models\Lesson;
use App\Modules\Lms\Models\Question;
use App\Modules\Lms\Models\Quiz;
use App\Modules\Lms\Models\Taxonomy;
use App\Modules\Services\Models\Availability;
use App\Modules\Services\Models\Booking;
use App\Modules\Services\Models\Service;
use App\Modules\Services\Models\ServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * مشترك تجريبي للتطوير وفحوص الواجهة الآلية.
 *   php artisan demo:tenant --mode=center
 */
final class SeedDemoTenant extends Command
{
    protected $signature = 'demo:tenant
        {--slug=demo : النطاق الفرعي}
        {--mode=marketplace : نمط المنصة}
        {--plan=growth : الباقة}
        {--fresh : احذف المشترك القائم بنفس النطاق أولاً}
        {--onboarding : اتركه في معالج التهيئة بدل إكماله}';

    protected $description = 'ينشئ مشتركاً تجريبياً ببيانات واقعية';

    public function handle(ProvisionTenant $provision): int
    {
        $slug = (string) $this->option('slug');

        if ($existing = Tenant::where('slug', $slug)->first()) {
            if (! $this->option('fresh')) {
                $this->warn("المشترك [{$slug}] موجود بالفعل. استخدم --fresh لإعادة إنشائه.");

                return self::SUCCESS;
            }

            $existing->delete();
        }

        $tenant = $provision->handle([
            'name' => 'أكاديمية معوّض',
            'slug' => $slug,
            'owner_email' => 'ahmed@example.test',
            'owner_name' => 'أحمد معوّض',
            'plan_key' => (string) $this->option('plan'),
            'platform_mode' => (string) $this->option('mode'),
            'delivery_mode' => 'blended',
            'password' => 'password',
        ]);

        $tenant->run(function (): void {
            if (! $this->option('onboarding')) {
                app(OnboardingWizard::class)->complete();
            }

            $people = [
                ['سارة عبد الرحمن', 'sara@t.test', 'active', true, '01000000001'],
                ['يوسف حمدي', 'youssef@t.test', 'pending', false, '01000000002'],
                ['منة الله طارق', 'mennah@t.test', 'suspended', false, '01000000003'],
                ['عمر السيد', 'omar@t.test', 'active', true, '01000000004'],
                ['هبة صلاح', 'heba@t.test', 'active', false, '01000000005'],
                ['كريم مصطفى', 'karim@t.test', 'active', true, '01000000006'],
                ['نورهان أشرف', 'nourhan@t.test', 'active', false, '01000000007'],
            ];

            foreach ($people as [$name, $email, $status, $legacy, $phone]) {
                DB::table('users')->insert([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    // كلمة مرور موحّدة للتجربة والفحوص — بيئة تطوير لا إنتاج
                    'password' => Hash::make('password'),
                    'status' => $status,
                    'legacy_hash' => $legacy,
                    'email_verified_at' => $legacy ? now() : null,
                    'last_seen_at' => now()->subDays(random_int(0, 20)),
                    'created_at' => now()->subDays(random_int(1, 90)),
                    'updated_at' => now(),
                ]);
            }

            $this->seedLms();
            $this->seedContent();
            $this->seedServices();
            $this->seedCommunity();
            $this->seedGrowth();

            if (tenant()->managesCenter()) {
                $this->seedCenter();
            }
        });

        $this->info('تم إنشاء المشترك التجريبي.');
        $this->line('  النطاق: '.$tenant->domains->first()?->domain);
        $this->line('  الدخول: ahmed@example.test / password');
        $this->line('  النمط:  '.$tenant->platform_mode.' · الباقة: '.$tenant->plan_key);

        return self::SUCCESS;
    }

    /** سنتر بحصص وحضور وأقساط — لتُقاس عليه الشاشات بحالة حقيقية. */
    private function seedCenter(): void
    {
        $branch = CenterBranch::create([
            'name' => ['ar' => 'الفرع الرئيسي', 'en' => 'Main branch'],
            'code' => 'MAIN', 'phone' => '0223456789', 'is_active' => true,
        ]);

        $rooms = collect(['قاعة أ', 'قاعة ب'])->map(fn (string $name): CenterRoom => CenterRoom::create([
            'branch_id' => $branch->id, 'name' => ['ar' => $name], 'capacity' => 30,
        ]));

        $stage = CenterStage::create(['name' => ['ar' => 'ثانوي', 'en' => 'Secondary'], 'position' => 1]);
        $grade = CenterGrade::create(['stage_id' => $stage->id, 'name' => ['ar' => 'الثالث الثانوي'], 'position' => 3]);

        $cashbox = CenterCashbox::create([
            'branch_id' => $branch->id, 'name' => ['ar' => 'خزنة الاستقبال'],
            'currency' => 'EGP', 'opening_minor' => 0, 'balance_minor' => 0,
        ]);

        $teacher = DB::table('users')->where('role', 'owner')->value('id');
        $subjects = ['فيزياء' => '#1F6FEB', 'كيمياء' => '#15803D', 'رياضيات' => '#B45309'];
        $weekday = 6;

        foreach ($subjects as $subjectName => $color) {
            $subject = CenterSubject::create([
                'name' => ['ar' => $subjectName], 'stage_id' => $stage->id, 'color' => $color,
            ]);

            $group = CenterGroup::create([
                'branch_id' => $branch->id,
                'subject_id' => $subject->id,
                'grade_id' => $grade->id,
                'teacher_id' => $teacher,
                'name' => ['ar' => $subjectName.' ٣ث — '.CenterSchedule::WEEKDAYS[$weekday]],
                'capacity' => 25,
                'currency' => 'EGP',
                'price_minor' => 40000,
                'price_type' => 'monthly',
                'status' => 'running',
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->addMonths(3)->toDateString(),
                'color' => $color,
            ]);

            CenterSchedule::create([
                'group_id' => $group->id,
                'room_id' => $rooms->random()->id,
                'weekday' => $weekday,
                'starts_at' => sprintf('%02d:00:00', 14 + array_search($subjectName, array_keys($subjects), true) * 2),
                'ends_at' => sprintf('%02d:00:00', 16 + array_search($subjectName, array_keys($subjects), true) * 2),
            ]);

            app(GenerateSessions::class)->handle($group, now()->startOfWeek(Carbon::SUNDAY), now()->addWeeks(3));

            $weekday = $weekday === 6 ? 1 : 3;
        }

        // طلاب بأولياء أمور وحضور وأقساط
        $names = ['يوسف حمدي', 'سلمى أحمد', 'مازن خالد', 'ليلى سمير', 'آدم طارق', 'حبيبة وائل'];
        $groups = CenterGroup::all();

        foreach ($names as $index => $name) {
            $user = User::create([
                'name' => $name,
                'email' => 'pupil'.($index + 1).'@t.test',
                'phone' => '0102000'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'password' => Hash::make('password'),
                'role' => 'student',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            $student = CenterStudent::create([
                'user_id' => $user->id,
                'code' => CenterStudent::nextCode(),
                'branch_id' => $branch->id,
                'stage_id' => $stage->id,
                'grade_id' => $grade->id,
                'school' => 'مدرسة النيل الثانوية',
                'joined_at' => now()->subMonths(2)->toDateString(),
                'status' => 'active',
            ]);

            $guardian = CenterGuardian::create([
                'name' => 'ولي أمر '.$name,
                'relation' => 'الأب',
                'phone' => '0100100'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'whatsapp' => '2010010'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
            ]);

            $guardian->students()->attach($student->id, ['is_primary' => true]);

            foreach ($groups->random(min(2, $groups->count())) as $group) {
                app(EnrolStudent::class)->handle($student, $group->refresh());
            }
        }

        foreach ($groups as $group) {
            app(IssueInvoices::class)->handle($group, now()->format('Y-m'));
        }

        // حضور الحصص الماضية
        foreach (CenterSession::whereDate('date', '<', now())->get() as $session) {
            $statuses = [];

            foreach ($session->group->enrollments()->active()->pluck('student_id') as $studentId) {
                $statuses[$studentId] = random_int(1, 10) > 8 ? 'absent' : 'present';
            }

            app(TakeAttendance::class)->handle($session, $statuses);
        }

        // بعض التحصيل حتى تظهر الخزنة والمتأخرات معاً
        foreach (CenterInvoice::limit(4)->get() as $invoice) {
            app(CollectPayment::class)->handle(
                $invoice->student,
                $invoice->total(),
                $invoice,
                $cashbox,
                'cash',
            );
        }
    }

    /** محتوى تعليمي حقيقي بما يكفي لتُقاس عليه الشاشات والبوابات. */
    /** نمو: مسوّق بنقرات وتحويلات، وتسلسل سلة متروكة جاهز. */
    private function seedGrowth(): void
    {
        setting()->setMany([
            'growth.affiliates_enabled' => true,
            'growth.affiliates_default_rate' => 15,
        ]);

        $marketer = User::where('email', 'karim@t.test')->first();

        if ($marketer !== null) {
            $affiliate = Affiliate::create([
                'user_id' => $marketer->getKey(),
                'code' => 'karim101',
                'status' => 'active',
                'approved_at' => now(),
                'payout_method' => 'bank',
                'clicks_count' => 142,
                'conversions_count' => 9,
                'earned_minor' => 187000,
                'paid_minor' => 90000,
            ]);

            foreach (range(1, 4) as $month) {
                AffiliateConversion::create([
                    'affiliate_id' => $affiliate->getKey(),
                    'currency' => (string) (tenant('currency') ?? 'EGP'),
                    'amount_minor' => 149900,
                    'commission_minor' => 22485,
                    'status' => $month === 1 ? 'pending' : 'approved',
                    'matured_at' => now()->subMonths($month)->addDays(14),
                    'created_at' => now()->subMonths($month),
                    'updated_at' => now()->subMonths($month),
                ]);
            }
        }

        $campaign = Campaign::create([
            'key' => 'abandoned-cart',
            'name' => ['ar' => 'استعادة السلة المتروكة', 'en' => 'Abandoned cart recovery'],
            'trigger' => 'cart_abandoned',
            'status' => 'active',
        ]);

        // ساعة ثم يوم ثم ثلاثة: الإلحاح يقلّ والرسالة تتغيّر
        foreach ([
            [1, 60, 'commerce.abandoned_cart'],
            [2, 1440, 'commerce.abandoned_cart'],
            [3, 4320, 'commerce.abandoned_cart'],
        ] as [$position, $minutes, $event]) {
            CampaignStep::create([
                'campaign_id' => $campaign->getKey(),
                'position' => $position - 1,
                'delay_minutes' => $minutes,
                'event' => $event,
                'is_active' => true,
            ]);
        }

        $idle = Campaign::create([
            'key' => 'idle-learner',
            'name' => ['ar' => 'إعادة الخاملين', 'en' => 'Re-engage idle learners'],
            'trigger' => 'course_idle',
            'status' => 'active',
        ]);

        CampaignStep::create([
            'campaign_id' => $idle->getKey(),
            'position' => 0,
            'delay_minutes' => 60,
            'event' => 'lms.idle_reminder',
            'is_active' => true,
        ]);
    }

    /** مجتمع بأسئلة وتقييمات ونقاط — لتُقاس عليه الشاشات بحالة حقيقية. */
    private function seedCommunity(): void
    {
        app(AwardBadges::class)->install();

        $course = Course::where('slug', 'php-from-zero')->firstOrFail();
        $student = User::where('email', 'sara@t.test')->first();
        $peer = User::where('email', 'omar@t.test')->first();
        $owner = User::where('role', 'owner')->first();

        if ($student === null || $owner === null) {
            return;
        }

        $ask = app(PostDiscussion::class);

        $answered = $ask->ask($student, $course, [
            'title' => 'ما الفرق بين include و require؟',
            'body' => 'قرأت أن الاثنين يُدرجان ملفاً، فمتى أستعمل كلاً منهما؟',
        ]);

        $reply = $ask->reply($owner, $answered, [
            'body' => "الفرق في التعامل مع الفشل: require يوقف التنفيذ إن لم يجد الملف، وinclude يكمل بتحذير.\n\nالقاعدة العملية: ما لا يعمل البرنامج بدونه require، وما هو إضافة اختيارية include.",
        ]);

        $ask->accept($student, $reply);

        if ($peer !== null) {
            app(EnrollStudent::class)->handle($peer, $course, 'free');
        }

        $ask->ask($peer ?? $student, $course, [
            'title' => 'أفضل محرّر للبدء؟',
            'body' => 'أستعمل Notepad حالياً وأشعر أنني أضيّع وقتاً. بم تنصحون؟',
        ]);

        // تقييمات: منشور ومعلَّق — ليظهر طابور المراجعة بحالة حقيقية
        $reviews = app(SubmitReview::class);
        $enrollment = Enrollment::where('user_id', $student->getKey())
            ->where('course_id', $course->getKey())->first();

        if ($enrollment !== null) {
            $review = $reviews->forCourse($student, $course, [
                'rating' => 5,
                'body' => 'شرح عملي وواضح، والمشروع في النهاية ثبّت كل ما سبق.',
            ]);

            $reviews->moderate($review, 'approved', 'شكراً لكِ — بالتوفيق في الكورس التالي.');
        }

        if ($peer !== null) {
            $reviews->forCourse($peer, $course, [
                'rating' => 4,
                'body' => 'ممتاز، وكنت أتمنى تمارين أكثر على الفصل الثالث.',
            ]);
        }
    }

    /** محتوى: صفحات إلزامية ومدونة ونموذج تواصل وتحويل رابط قديم. */
    private function seedContent(): void
    {
        app(InstallSystemPages::class)->handle();

        $landing = Page::create([
            'slug' => 'start-here',
            'title' => ['ar' => 'ابدأ من هنا', 'en' => 'Start here'],
            'status' => 'published',
            'published_at' => now(),
            'blocks' => [
                [
                    'type' => 'hero',
                    'content' => [
                        'heading' => ['ar' => 'تعلّم البرمجة بالعربية', 'en' => 'Learn to code in Arabic'],
                        'subheading' => ['ar' => 'كورسات عملية تبني فيها مشروعاً كاملاً، لا محاضرات نظرية.'],
                        'cta_label' => ['ar' => 'تصفّح الكورسات', 'en' => 'Browse courses'],
                        'cta_url' => '/courses',
                        'align' => 'center',
                    ],
                    'settings' => ['background' => 'primary', 'width' => 'wide'],
                ],
                [
                    'type' => 'features',
                    'content' => [
                        'heading' => ['ar' => 'لماذا نحن؟'],
                        'items' => "مشروع في كل كورس\nتصحيح بشري للواجبات\nشهادة قابلة للتحقق",
                        'columns' => '3',
                    ],
                    'settings' => ['width' => 'wide'],
                ],
                [
                    'type' => 'courses',
                    'content' => ['heading' => ['ar' => 'أحدث الكورسات'], 'source' => 'latest', 'limit' => 3, 'columns' => '3'],
                    'settings' => ['background' => 'sunken'],
                ],
                [
                    'type' => 'faq',
                    'content' => [
                        'heading' => ['ar' => 'أسئلة متكرّرة'],
                        'items' => "هل الشهادة معتمدة؟|شهادتنا قابلة للتحقق برقمها على الموقع.\nكم مدة الوصول للكورس؟|سنة كاملة من تاريخ الشراء.",
                        'schema' => true,
                    ],
                    'settings' => ['width' => 'narrow'],
                ],
            ],
        ]);

        $category = Taxonomy::firstOrCreate(
            ['type' => 'category', 'slug' => 'articles'],
            ['name' => ['ar' => 'مقالات', 'en' => 'Articles'], 'position' => 2],
        );

        $author = User::where('role', 'owner')->first();

        $posts = [
            ['kayfa-tabda', 'كيف تبدأ في البرمجة من الصفر', 'How to start programming'],
            ['akhtaa-shaia', 'خمسة أخطاء شائعة عند تعلّم لارافيل', 'Five common Laravel mistakes'],
            ['mashroo-awal', 'مشروعك الأول: من الفكرة إلى النشر', 'Your first project'],
        ];

        foreach ($posts as $index => [$slug, $ar, $en]) {
            $post = Post::create([
                'slug' => $slug,
                'title' => ['ar' => $ar, 'en' => $en],
                'excerpt' => ['ar' => 'خلاصة عملية مبنية على تجربة تدريس آلاف الطلاب.'],
                'body' => ['ar' => "لا تبدأ بحفظ الأوامر.\n\nابدأ بمشكلة صغيرة تريد حلّها، ثم ابحث عن الأداة التي تحلّها. الطريق العكسي — أن تتعلّم الأداة ثم تبحث لها عن مشكلة — هو ما يجعل معظم الناس يتوقّفون في الشهر الثاني."],
                'category_id' => $category->id,
                'author_id' => $author?->getKey(),
                'status' => 'published',
                'published_at' => now()->subDays(20 - $index * 6),
                'views_count' => random_int(40, 900),
                'featured' => $index === 0,
            ]);

            $post->forceFill(['reading_minutes' => $post->estimateReadingMinutes()])->save();
        }

        $first = Post::where('slug', 'kayfa-tabda')->firstOrFail();

        $first->comments()->createMany([
            [
                'user_id' => User::where('email', 'sara@t.test')->value('id'),
                'body' => 'مقال عملي فعلاً. النقطة الثانية غيّرت طريقتي في المذاكرة.',
                'status' => 'approved',
            ],
            [
                'author_name' => 'زائر',
                'author_email' => 'guest@example.test',
                'body' => 'هل تنصح بالبدء بلغة بعينها؟',
                'status' => 'pending',
            ],
        ]);

        Form::create([
            'key' => 'contact',
            'name' => ['ar' => 'اتصل بنا', 'en' => 'Contact us'],
            'fields' => [
                ['name' => 'name', 'label' => ['ar' => 'الاسم', 'en' => 'Name'], 'type' => 'text', 'required' => true],
                ['name' => 'email', 'label' => ['ar' => 'البريد', 'en' => 'Email'], 'type' => 'email', 'required' => true],
                ['name' => 'message', 'label' => ['ar' => 'رسالتك', 'en' => 'Message'], 'type' => 'textarea', 'required' => true],
            ],
            'success_message' => ['ar' => 'وصلتنا رسالتك، نردّ خلال يوم عمل.'],
            'notify_email' => 'ahmed@example.test',
        ]);

        // رابط من الموقع القديم: شرط ترحيل لا رفاهية
        Redirect::create(['from' => '/old-blog/start-here', 'to' => '/'.$landing->slug, 'code' => 301]);
    }

    /** خدمات تُباع بالوقت: استشارة وجلسة تقوية ومراجعة ملف. */
    private function seedServices(): void
    {
        $owner = User::where('role', 'owner')->first();

        $services = [
            ['consultation', 'استشارة مسار مهني', 'Career consultation', 'appointment', 50000, 45],
            ['code-review', 'مراجعة كود مشروعك', 'Code review', 'delivery', 120000, 0],
            ['private-session', 'جلسة تقوية خاصة', 'Private session', 'appointment', 35000, 60],
        ];

        foreach ($services as [$slug, $ar, $en, $type, $price, $duration]) {
            $service = Service::create([
                'slug' => $slug,
                'title' => ['ar' => $ar, 'en' => $en],
                'excerpt' => ['ar' => 'جلسة مركّزة تخرج منها بخطة مكتوبة لا بانطباع.'],
                'description' => ['ar' => "نبدأ بأسئلة عن وضعك الحالي، ثم نضع خطة أسبوعية قابلة للتنفيذ.\n\nتصلك الخطة مكتوبة بعد الجلسة."],
                'type' => $type,
                'currency' => (string) (tenant('currency') ?? 'EGP'),
                'price_minor' => $price,
                'price_type' => 'fixed',
                'duration_minutes' => $duration ?: 60,
                'buffer_minutes' => $type === 'appointment' ? 15 : 0,
                'lead_hours' => 12,
                'cancel_hours' => 24,
                'max_per_slot' => 1,
                'delivery_days' => $type === 'delivery' ? 3 : 0,
                'requirements' => ['ما الذي جرّبته حتى الآن؟', 'ما هدفك خلال ستة أشهر؟'],
                'deliverables' => ['خطة أسبوعية مكتوبة', 'قائمة مصادر مختارة'],
                'location' => 'online',
                'status' => 'published',
            ]);

            if ($owner === null || $type !== 'appointment') {
                continue;
            }

            $provider = ServiceProvider::create([
                'service_id' => $service->getKey(),
                'user_id' => $owner->getKey(),
                'is_active' => true,
            ]);

            // أحد إلى خميس، من العاشرة إلى الرابعة
            foreach ([0, 1, 2, 3, 4] as $weekday) {
                Availability::create([
                    'provider_id' => $provider->getKey(),
                    'weekday' => $weekday,
                    'starts_at' => '10:00:00',
                    'ends_at' => '16:00:00',
                ]);
            }
        }

        $service = Service::where('slug', 'consultation')->firstOrFail();
        $provider = $service->providers()->first();
        $student = User::where('email', 'sara@t.test')->first();

        if ($provider !== null && $student !== null) {
            Booking::create([
                'reference' => 'BK-'.now()->format('Ym').'-0001',
                'service_id' => $service->getKey(),
                'provider_id' => $provider->getKey(),
                'user_id' => $student->getKey(),
                'customer_name' => $student->name,
                'customer_email' => $student->email,
                'date' => now()->addDays(3)->toDateString(),
                'starts_at' => '11:00:00',
                'ends_at' => '11:45:00',
                'timezone' => tenant('timezone'),
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'currency' => $service->currency,
                'price_minor' => $service->price_minor,
            ]);
        }
    }

    private function seedLms(): void
    {
        $category = Taxonomy::create([
            'type' => 'category', 'slug' => 'programming',
            'name' => ['ar' => 'البرمجة', 'en' => 'Programming'], 'position' => 1,
        ]);

        $level = Taxonomy::create([
            'type' => 'level', 'slug' => 'beginner',
            'name' => ['ar' => 'مبتدئ', 'en' => 'Beginner'], 'position' => 1,
        ]);

        $instructor = Instructor::create([
            'user_id' => DB::table('users')->where('role', 'owner')->value('id'),
            'headline' => ['ar' => 'مطوّر ويب ومدرّب'],
            'bio' => ['ar' => 'أُدرّس البرمجة منذ عشر سنوات.'],
            'approved_at' => now(),
            'is_verified' => true,
        ]);

        $courses = [
            ['php-from-zero', 'PHP من الصفر', 'PHP from zero', 0, 'free'],
            ['laravel-in-practice', 'لارافيل عملياً', 'Laravel in practice', 149900, 'paid'],
            ['js-modern', 'جافاسكربت الحديثة', 'Modern JavaScript', 99900, 'paid'],
        ];

        foreach ($courses as $index => [$slug, $ar, $en, $price, $type]) {
            $course = Course::create([
                'slug' => $slug,
                'title' => ['ar' => $ar, 'en' => $en],
                'excerpt' => ['ar' => 'كورس عملي تبني فيه مشروعاً كاملاً خطوة بخطوة.'],
                'description' => ['ar' => 'نبدأ من الصفر ونصل إلى مشروع يعمل. كل درس يبني على ما قبله، وفي نهايته تطبيق عملي.'],
                'instructor_id' => $instructor->id,
                'category_id' => $category->id,
                'level_id' => $level->id,
                'status' => 'published',
                'visibility' => 'public',
                'enrollment_type' => $type,
                'price_minor' => $price,
                'compare_price_minor' => $price > 0 ? (int) ($price * 1.5) : null,
                'currency' => 'EGP',
                'outcomes' => ['بناء تطبيق كامل', 'فهم أساسيات اللغة', 'التعامل مع قواعد البيانات'],
                'requirements' => ['حاسوب واتصال بالإنترنت', 'لا حاجة لخبرة سابقة'],
                'published_at' => now()->subDays(30 - $index * 7),
                'access_days' => $index === 0 ? 0 : 365,
            ]);

            $section = CourseSection::create([
                'course_id' => $course->id,
                'title' => ['ar' => 'المقدمة والتجهيز', 'en' => 'Getting started'],
                'position' => 0,
            ]);

            foreach (range(1, 4) as $n) {
                $lesson = Lesson::create([
                    'title' => ['ar' => "الدرس {$n}: أساسيات", 'en' => "Lesson {$n}"],
                    'content' => ['ar' => 'شرح الدرس ومصادره ونقاطه الرئيسية.'],
                    'type' => 'video',
                    'duration_seconds' => 480 + $n * 120,
                ]);

                CourseItem::create([
                    'course_id' => $course->id,
                    'section_id' => $section->id,
                    'itemable_type' => Lesson::class,
                    'itemable_id' => $lesson->id,
                    'position' => $n,
                    'is_preview' => $n === 1,
                ]);
            }

            $quiz = Quiz::create([
                'title' => ['ar' => 'اختبار المقدمة', 'en' => 'Intro quiz'],
                'description' => ['ar' => 'أسئلة قصيرة على ما سبق.'],
                'time_limit_minutes' => 15,
                'max_attempts' => 3,
                'passing_percentage' => 60,
            ]);

            $question = Question::create([
                'body' => ['ar' => 'ما امتداد ملف PHP؟', 'en' => 'What is the PHP file extension?'],
                'type' => 'single_choice',
                'options' => ['a' => '.php', 'b' => '.html', 'c' => '.js'],
                'correct' => ['a'],
                'marks' => 1,
                'difficulty' => 'easy',
            ]);

            $quiz->questions()->attach($question->id, ['position' => 0]);

            CourseItem::create([
                'course_id' => $course->id,
                'section_id' => $section->id,
                'itemable_type' => Quiz::class,
                'itemable_id' => $quiz->id,
                'position' => 5,
            ]);

            $course->forceFill([
                'lessons_count' => 5,
                'duration_minutes' => 44,
                'students_count' => random_int(12, 340),
                'rating_avg' => round(random_int(40, 50) / 10, 1),
                'ratings_count' => random_int(3, 40),
            ])->save();
        }

        // طالب مسجّل بتقدّم جزئي — لتظهر شاشة غرفة التعلّم بحالة حقيقية
        $student = User::where('email', 'sara@t.test')->first();
        $first = Course::where('slug', 'php-from-zero')->firstOrFail();

        if ($student !== null) {
            $enrollment = app(EnrollStudent::class)->handle($student, $first, 'free');
            app(TrackProgress::class)->complete($enrollment, $first->items()->first());
        }

        $this->seedSecondInstructor();
    }

    /**
     * مدرّس ثانٍ بكورسه وطلابه وأرباحه.
     *
     * بغيره كان المدرّس الوحيد في البذرة هو صاحب المنصّة نفسه، فلا
     * تُختبر لوحة المدرّس ولا يظهر حصر النطاق: من يملك كل شيء لا
     * يكشف خللاً في حصر من لا يملك إلا كورسه.
     */
    private function seedSecondInstructor(): void
    {
        $user = User::create([
            'name' => 'نور الدين حسن',
            'email' => 'nour@t.test',
            'phone' => '01000000010',
            'password' => Hash::make('password'),
            'role' => 'instructor',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $instructor = Instructor::create([
            'user_id' => $user->id,
            'headline' => ['ar' => 'مهندس واجهات أمامية'],
            'bio' => ['ar' => 'أُدرّس جافاسكربت وأدواتها.'],
            'approved_at' => now(),
            'commission_rate' => 70,
        ]);

        $course = Course::where('slug', 'js-modern')->first();

        if ($course === null) {
            return;
        }

        $course->forceFill(['instructor_id' => $instructor->id])->save();

        foreach (['omar@t.test', 'karim@t.test', 'heba@t.test'] as $email) {
            $pupil = User::where('email', $email)->first();

            if ($pupil !== null) {
                app(EnrollStudent::class)->handle($pupil, $course, 'free');
            }
        }

        // سؤال معلّق وإعلان منشور — لتفتح شاشتاهما بمحتوى لا بفراغ
        $asker = User::where('email', 'omar@t.test')->first();

        if ($asker !== null) {
            Discussion::create([
                'type' => 'question', 'course_id' => $course->id, 'user_id' => $asker->id,
                'title' => 'ما الفرق بين let و const؟',
                'body' => 'التبس عليّ الأمر في الدرس الثالث.',
                'status' => 'open',
            ]);
        }

        Discussion::create([
            'type' => 'announcement', 'course_id' => $course->id, 'user_id' => $user->id,
            'title' => 'موعد الجلسة المباشرة',
            'body' => 'الجلسة المباشرة الأحد القادم الساعة الثامنة مساءً.',
            'status' => 'open', 'is_pinned' => true,
        ]);

        // أرباح بثلاث حالات — لتظهر طبقات الرصيد كما هي في الواقع
        foreach ([
            ['pending', 42000, null],
            ['available', 87500, '-3 days'],
            ['paid', 120000, '-30 days'],
        ] as [$status, $amount, $matured]) {
            InstructorEarning::create([
                'instructor_id' => $instructor->id,
                'currency' => 'EGP',
                'amount_minor' => $amount,
                'rate' => 70,
                'status' => $status,
                'available_at' => $matured === null ? now()->addDays(14) : now()->modify($matured),
                'created_at' => now()->subDays(random_int(1, 40)),
            ]);
        }
    }
}
