<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * التسجيل والتقدّم والتقييم.
 *
 * التسجيل منفصل عن الطلب عمداً: قد يأتي من هدية أو منحة أو
 * اشتراك أو استيراد، لا من شراء فقط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->enum('source', ['purchase', 'manual', 'bundle', 'subscription', 'import', 'free', 'code'])
                ->default('manual');
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->enum('status', ['active', 'completed', 'expired', 'suspended', 'refunded'])
                ->default('active')->index();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('last_item_id')->nullable();
            $table->decimal('grade', 5, 2)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            // أثقل استعلام في أي LMS: «هل هذا الطالب مسجّل في هذا الكورس؟»
            $table->unique(['user_id', 'course_id']);
            $table->index(['course_id', 'status']);
        });

        Schema::create('lesson_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('course_items')->cascadeOnDelete();
            $table->enum('status', ['not_started', 'in_progress', 'completed'])->default('not_started');
            $table->unsignedInteger('watched_seconds')->default(0);
            $table->unsignedInteger('last_position_seconds')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'item_id']);
        });

        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_no')->default(1);
            $table->enum('status', ['in_progress', 'submitted', 'graded'])->default('in_progress')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->decimal('score', 8, 2)->default(0);
            $table->decimal('max_score', 8, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->boolean('passed')->default(false);
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('evaluated_at')->nullable();

            /*
             | نسخة من الأسئلة وقت المحاولة. بدونها يفسد سجلّ الطالب
             | حين يعدّل المدرّس السؤال بعد شهر، ولا تعود المراجعة ممكنة.
             */
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'quiz_id', 'attempt_no']);
        });

        Schema::create('quiz_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->json('answer')->nullable();
            $table->boolean('is_correct')->nullable();       // null = بانتظار تصحيح بشري
            $table->decimal('marks_awarded', 6, 2)->default(0);
            $table->json('instructor_note')->nullable();
            $table->timestamps();

            $table->unique(['attempt_id', 'question_id']);
        });

        Schema::create('assignment_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_no')->default(1);
            $table->longText('content')->nullable();
            $table->json('files')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('is_late')->default(false);
            $table->enum('status', ['pending', 'graded', 'resubmit'])->default('pending')->index();
            $table->decimal('marks', 6, 2)->nullable();
            $table->json('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            /*
             | الاسم صريح لا مولَّد: اسم MySQL للمعرّف يقف عند ٦٤ محرفاً،
             | والمولَّد هنا `assignment_submissions_enrollment_id_
             | assignment_id_attempt_no_unique` سبعة وستون — فيسقط
             | تجهيز كل مشترك على MySQL. وSQLite لا تحدّ الاسم، فلم
             | يظهر العطل محلياً ولا في الاختبارات.
             */
            $table->unique(['enrollment_id', 'assignment_id', 'attempt_no'], 'submissions_attempt_unique');
        });

        Schema::create('certificate_templates', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->json('design')->nullable();
            $table->string('background_path')->nullable();
            $table->enum('page_size', ['a4', 'letter'])->default('a4');
            $table->enum('orientation', ['landscape', 'portrait'])->default('landscape');
            $table->string('locale', 5)->default('ar');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();      // يُستخدم في صفحة التحقق العامة
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('certificate_templates')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->json('data')->nullable();          // نسخة مجمّدة: اسم الطالب والكورس والدرجة وقت الإصدار
            $table->timestamps();

            $table->index(['user_id', 'course_id']);
        });

        Schema::create('course_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_reviews');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('certificate_templates');
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('lesson_progress');
        Schema::dropIfExists('enrollments');
    }
};
