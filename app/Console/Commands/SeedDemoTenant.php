<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Onboarding\OnboardingWizard;
use App\Core\Tenancy\Actions\ProvisionTenant;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Lms\Actions\EnrollStudent;
use App\Modules\Lms\Actions\TrackProgress;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseItem;
use App\Modules\Lms\Models\CourseSection;
use App\Modules\Lms\Models\Instructor;
use App\Modules\Lms\Models\Lesson;
use App\Modules\Lms\Models\Question;
use App\Modules\Lms\Models\Quiz;
use App\Modules\Lms\Models\Taxonomy;
use Illuminate\Console\Command;
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
        });

        $this->info('تم إنشاء المشترك التجريبي.');
        $this->line('  النطاق: '.$tenant->domains->first()?->domain);
        $this->line('  الدخول: ahmed@example.test / password');
        $this->line('  النمط:  '.$tenant->platform_mode.' · الباقة: '.$tenant->plan_key);

        return self::SUCCESS;
    }

    /** محتوى تعليمي حقيقي بما يكفي لتُقاس عليه الشاشات والبوابات. */
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
    }
}
