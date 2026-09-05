<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | نقاط التفاعل داخل الفيديو.
 |
 | `interactive_video` مفتاحٌ في الباقات بلا سطر كود. والطالب يشاهد
 | عشرين دقيقة ثم ينتقل، ولا يعرف المدرّس أفَهِم أم مرّت الصورة
 | أمامه — والسؤال في منتصف الفيديو يكشف ذلك في ثانيته لا في
 | امتحان آخر الوحدة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_moments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();

            // الثانية التي يتوقّف عندها الفيديو
            $table->unsignedInteger('at_second')->default(0);

            // question | note | link
            $table->string('kind', 16)->default('question');

            /*
             | السؤال يُشار إليه ولا يُنسَخ.
             |
             | نسخُه هنا يجعل تعديله في بنك الأسئلة لا يغيّر ما يراه
             | الطالب — نسختان تفترقان بلا أن يعلم أحد.
             */
            $table->foreignId('question_id')->nullable()->constrained('questions')->cascadeOnDelete();

            $table->text('body')->nullable();
            $table->string('url')->nullable();

            /*
             | الإلزام: يُمنع التقديم حتى يُجاب.
             |
             | ولا يُمنع الرجوع أبداً: من لم يفهم يعيد، ومنعُه من
             | الإعادة يحوّل التفاعل إلى عقوبة.
             */
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->index(['lesson_id', 'at_second']);
        });

        Schema::create('moment_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('moment_id')->constrained('video_moments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->timestamps();

            $table->unique(['moment_id', 'user_id'], 'moment_responses_owner_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moment_responses');
        Schema::dropIfExists('video_moments');
    }
};
