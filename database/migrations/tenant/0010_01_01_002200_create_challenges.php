<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | التحديات وعجلة الحظ.
 |
 | التلعيب عندنا نصفُ مبنيّ: نقاطٌ وشاراتٌ وسلاسل ولوحة صدارة —
 | وهي كلّها تقيس ما وقع. والتحدّي يصنع ما يقع: «أتمّ خمسة دروس هذا
 | الأسبوع» هدفٌ محدّد بمهلة، وهو ما يُحرّك الطالب المتردّد.
 |
 | ## والتقدّم يُحسب من النقاط لا يُخزَّن عدّاداً
 |
 | كل ما يفعله الطالب مسجَّل في `point_entries` بقاعدته وتاريخه.
 | فعدُّ «كم درساً أتمّ هذا الأسبوع» استعلامٌ على ما هو مكتوب، لا
 | عدّادٌ ثانٍ يُحدَّث في كل فعل ثم يفترق عن مصدره.
 |
 | ويُخزَّن الإنجاز وحده: من أتمّ التحدي ومتى، ليُمنح جائزته مرّة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table): void {
            $table->id();

            $table->json('title');
            $table->json('description')->nullable();

            // قاعدة من config('gamification.rules') — مثل lesson.completed
            $table->string('rule', 64);
            $table->unsignedInteger('target')->default(5);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            // الجائزة نقاطٌ إضافية، وشارةٌ اختيارية
            $table->unsignedInteger('reward_points')->default(50);
            $table->foreignId('badge_id')->nullable()->constrained('badges')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->string('icon', 8)->nullable();
            $table->timestamps();

            $table->index(['is_active', 'ends_at']);
        });

        Schema::create('challenge_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();

            // جائزةٌ واحدة لكل طالب في كل تحدٍّ
            $table->unique(['challenge_id', 'user_id']);
        });

        /*
         | عجلة الحظ: دورةٌ في اليوم بجائزةٍ عشوائية.
         |
         | وهي ليست قماراً: لا يدفع الطالب شيئاً، ولا يخسر شيئاً —
         | أسوأ ما يقع أن يربح أقلّ. وفائدتها أنها تُعيده كل يوم،
         | وهي العادة التي يقوم عليها التعلّم لا الحماسة.
         */
        Schema::create('wheel_spins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('points');
            $table->string('label')->nullable();
            $table->date('spun_on');
            $table->timestamps();

            // دورةٌ واحدة في اليوم — يحرسها المفتاح لا الشاشة وحدها
            $table->unique(['user_id', 'spun_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wheel_spins');
        Schema::dropIfExists('challenge_completions');
        Schema::dropIfExists('challenges');
    }
};
