<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | مسارات التعلّم وتتبّع المهارات.
 |
 | ## المسار غير الكورس
 |
 | الكورس مادّةٌ واحدة، والمسار رحلةٌ عبر كورسات: «الثانوية العامة —
 | رياضيات» ثلاثة كورسات بترتيب. والطالب اليوم يشتريها مفرّقةً ولا
 | يعرف أيّها أوّلاً، والمدرّس يشرح الترتيب في منشورٍ يضيع.
 |
 | وهو ما يفتح سوق الشركات كذلك: التدريب المؤسسي يُباع مساراً لا
 | كورساً.
 |
 | ## والمهارة غير الكورس أيضاً
 |
 | الدرجة تقول «٦٠٪» ولا تقول أين الضعف. والمهارة تقول: «الجبر ٩٠٪
 | والهندسة ٤٠٪» — فيعرف الطالب ما يراجع، ويعرف المدرّس ما يعيد
 | شرحه للصفّ كلّه.
 |
 | ## وتُقاس من الأسئلة لا تُدخَل يدوياً
 |
 | المهارة تُربَط بأسئلة البنك، وإتقانها يُحسب من إجابات الطالب
 | عليها. ولو أُدخلت يدوياً لصارت رأياً لا قياساً — ولاحتاجت وقتاً
 | من المدرّس لا يملكه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_paths', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();

            $table->json('title');
            $table->json('description')->nullable();
            $table->string('cover_path')->nullable();

            $table->string('status', 16)->default('draft');
            $table->boolean('is_public')->default(true);

            /*
             | التسلسل الإجباري: لا يُفتح كورسٌ حتى يُتمّ ما قبله.
             |
             | وهو خيارٌ لا افتراض: مسارُ مراجعةٍ قد يُفتح كلّه دفعةً،
             | ومسارُ بناءٍ لا يصحّ إلا بالترتيب.
             */
            $table->boolean('is_sequential')->default(true);

            $table->unsignedInteger('courses_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'is_public']);
        });

        Schema::create('learning_path_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('path_id')->constrained('learning_paths')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['path_id', 'course_id']);
            $table->index(['path_id', 'position']);
        });

        Schema::create('learning_path_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('path_id')->constrained('learning_paths')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['path_id', 'user_id']);
        });

        Schema::create('skills', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->json('name');
            $table->json('description')->nullable();

            // حدّ الإتقان: نسبةُ الصواب التي تُعدّ إتقاناً
            $table->unsignedTinyInteger('mastery_percent')->default(70);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        /*
         | المهارة تُربَط بالسؤال لا بالدرس.
         |
         | الدرس يُشرَح فيه شيء، والسؤال يُقاس به. وربطُها بالدرس
         | يجعل «الإتقان» مشاهدةً لا إجابة — والمشاهدة لا تدلّ.
         */
        Schema::create('question_skill', function (Blueprint $table): void {
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();

            $table->primary(['question_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_skill');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('learning_path_enrollments');
        Schema::dropIfExists('learning_path_items');
        Schema::dropIfExists('learning_paths');
    }
};
