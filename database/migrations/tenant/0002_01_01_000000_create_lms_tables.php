<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وثيقة 03 — نواة الـ LMS داخل قاعدة المشترك.
 *
 * النصوص المترجمة أعمدة json: صفٌّ واحد للكورس بكل لغاته،
 * فلا يُنسى صفّ ترجمة ولا تتضاعف الاستعلامات.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------- التصنيف ----------
        Schema::create('taxonomies', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 24)->index();        // category · level · tag · question_category
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('slug', 160);
            $table->foreignId('parent_id')->nullable()->constrained('taxonomies')->nullOnDelete();
            $table->string('icon', 32)->nullable();
            $table->string('color', 9)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedBigInteger('wp_term_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['type', 'slug']);
        });

        // ---------- المدرّسون ----------
        Schema::create('instructors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('headline')->nullable();
            $table->json('bio')->nullable();
            $table->json('expertise')->nullable();
            $table->string('website')->nullable();
            $table->json('social')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(70);
            $table->json('payout_method')->nullable();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('students_count')->default(0);
            $table->unsignedInteger('courses_count')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // ---------- الكورسات ----------
        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 200)->unique();
            $table->json('title');
            $table->json('excerpt')->nullable();
            $table->json('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('promo_video')->nullable();
            $table->foreignId('instructor_id')->nullable()->constrained('instructors')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('taxonomies')->nullOnDelete();
            $table->foreignId('level_id')->nullable()->constrained('taxonomies')->nullOnDelete();
            $table->string('language', 5)->default('ar');

            $table->enum('status', ['draft', 'pending', 'published', 'archived'])->default('draft')->index();
            $table->enum('visibility', ['public', 'private', 'hidden'])->default('public');
            $table->enum('enrollment_type', ['free', 'paid', 'invite'])->default('paid');

            $table->unsignedBigInteger('price_minor')->default(0);
            $table->unsignedBigInteger('compare_price_minor')->nullable();
            $table->string('currency', 3)->nullable();

            $table->unsignedTinyInteger('passing_percentage')->default(60);
            $table->boolean('certificate_enabled')->default(true);
            $table->foreignId('certificate_template_id')->nullable();

            $table->boolean('sequential')->default(false);
            $table->boolean('drip_enabled')->default(false);
            $table->enum('drip_strategy', ['by_days', 'by_date', 'by_completion'])->default('by_days');

            $table->unsignedSmallInteger('access_days')->default(0);   // 0 = مدى الحياة
            $table->unsignedInteger('max_students')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->json('requirements')->nullable();
            $table->json('outcomes')->nullable();
            $table->json('target_audience')->nullable();
            $table->json('seo')->nullable();

            $table->unsignedInteger('duration_minutes')->default(0);
            $table->unsignedInteger('lessons_count')->default(0);
            $table->unsignedInteger('students_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('ratings_count')->default(0);

            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('wp_post_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'visibility']);
        });

        Schema::create('course_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->json('title');
            $table->json('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['course_id', 'position']);
        });

        Schema::create('lessons', function (Blueprint $table): void {
            $table->id();
            $table->json('title');
            $table->json('content')->nullable();
            $table->enum('type', ['video', 'audio', 'text', 'pdf', 'slides', 'live', 'scorm', 'embed'])->default('video');
            $table->string('video_provider', 24)->nullable();
            $table->string('video_id')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->json('attachments')->nullable();
            $table->json('transcript')->nullable();
            $table->boolean('is_downloadable')->default(false);
            $table->unsignedBigInteger('wp_post_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('quizzes', function (Blueprint $table): void {
            $table->id();
            $table->json('title');
            $table->json('description')->nullable();
            $table->enum('type', ['static', 'dynamic'])->default('static');
            $table->unsignedSmallInteger('time_limit_minutes')->default(0);   // 0 = بلا وقت
            $table->unsignedTinyInteger('max_attempts')->default(0);          // 0 = بلا حد
            $table->unsignedTinyInteger('passing_percentage')->default(60);
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_answers')->default(true);
            $table->enum('show_answers', ['never', 'after_submit', 'after_pass'])->default('after_pass');
            $table->unsignedSmallInteger('questions_count')->default(0);
            $table->json('question_pool')->nullable();
            $table->boolean('negative_marking')->default(false);
            $table->unsignedSmallInteger('retake_delay_hours')->default(0);
            $table->unsignedBigInteger('wp_post_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->json('body');
            $table->enum('type', [
                'single_choice', 'multiple_choice', 'true_false', 'match',
                'sort', 'dropdown', 'fill_blank', 'short_text', 'essay', 'file_upload',
            ])->default('single_choice');
            $table->decimal('marks', 6, 2)->default(1);
            $table->decimal('negative_marks', 6, 2)->default(0);
            $table->json('explanation')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('taxonomies')->nullOnDelete();
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium')->index();
            $table->json('options')->nullable();
            $table->json('correct')->nullable();
            $table->string('media_path')->nullable();
            $table->unsignedBigInteger('wp_post_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('quiz_question', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->decimal('marks_override', 6, 2)->nullable();

            $table->unique(['quiz_id', 'question_id']);
        });

        Schema::create('assignments', function (Blueprint $table): void {
            $table->id();
            $table->json('title');
            $table->json('instructions')->nullable();
            $table->json('attachments')->nullable();
            $table->decimal('max_marks', 6, 2)->default(100);
            $table->decimal('passing_marks', 6, 2)->default(50);
            $table->unsignedSmallInteger('due_days')->default(7);
            $table->boolean('allow_late')->default(true);
            $table->unsignedSmallInteger('late_penalty_percent')->default(0);
            $table->unsignedSmallInteger('max_file_mb')->default(25);
            $table->json('allowed_extensions')->nullable();
            $table->unsignedTinyInteger('max_resubmissions')->default(1);
            $table->unsignedBigInteger('wp_post_id')->nullable()->index();
            $table->timestamps();
        });

        /*
         | جدول موحّد لعناصر المنهج: الدرس والاختبار والواجب تعيش
         | في ترتيب واحد داخل القسم، فلا نحتاج دمج ثلاث قوائم عند العرض.
         */
        Schema::create('course_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('course_sections')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('itemable_type', 32);
            $table->unsignedBigInteger('itemable_id');
            $table->boolean('is_preview')->default(false);
            $table->unsignedSmallInteger('available_after_days')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'section_id', 'position']);
            $table->index(['itemable_type', 'itemable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_items');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('quiz_question');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('course_sections');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('instructors');
        Schema::dropIfExists('taxonomies');
    }
};
