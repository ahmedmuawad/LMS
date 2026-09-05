<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | حزم SCORM.
 |
 | `scorm` كان اسم نوعِ درسٍ في قائمة، بلا رفعٍ ولا مشغّل ولا
 | تتبّع: يختاره المدرّس فلا يجد ما يرفع به شيئاً.
 |
 | والمعيار قديم (٢٠٠٤ آخر إصدار جادّ) لكنه لا يزال ما تُصدَّر به
 | مواد الناشرين ومنصّات التأليف — ومن عنده حزمة لا يملك تحويلها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scorm_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();

            $table->string('title')->nullable();

            // 1.2 | 2004
            $table->string('version', 8)->default('1.2');

            // المجلد الذي فُكّت فيه الحزمة، والملف الذي يُفتح أولاً
            $table->string('path');
            $table->string('entry');

            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->index('lesson_id');
        });

        /*
         | حالة كل طالب في كل حزمة.
         |
         | المعيار يحفظ عشرات المفاتيح (`cmi.*`)، وأكثرها لا يُقرأ
         | أبداً. فتُحفظ الحالة كاملةً في JSON للاستئناف، وتُستخرج
         | منها الأربعة التي تُقرأ فعلاً إلى أعمدة: الحالة والدرجة
         | والموضع والزمن — فالتقارير لا تفكّ JSON لكل صف.
         */
        Schema::create('scorm_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained('scorm_packages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('lesson_status', 24)->default('not attempted');
            $table->decimal('score_raw', 6, 2)->nullable();
            $table->text('suspend_data')->nullable();
            $table->string('location')->nullable();
            $table->unsignedInteger('total_seconds')->default(0);

            $table->json('cmi')->nullable();
            $table->timestamps();

            $table->unique(['package_id', 'user_id'], 'scorm_states_owner_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scorm_states');
        Schema::dropIfExists('scorm_packages');
    }
};
