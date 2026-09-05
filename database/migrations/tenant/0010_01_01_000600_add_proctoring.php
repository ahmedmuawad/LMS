<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | مراقبة الامتحان.
 |
 | `proctoring` مفتاحٌ في الباقات بلا سطر كود. والمدرّس يمتحن
 | طلابه أونلاين وهو يعلم أنهم قد يفتحون كتاباً أو نافذة أخرى، فلا
 | يثق بالدرجة — فيعيد الامتحان ورقياً، وتضيع الميزة كلّها.
 |
 | ## ما تَعِد به هذه المراقبة
 |
 | لا تمنع الغشّ منعاً تامّاً: من أراد فتح كتابٍ ورقيّ بجواره لا
 | يمنعه متصفّح. وما تفعله أنها **تجعل ما يقع مرئياً**: خروجٌ من
 | النافذة، لصقُ نصّ، فقدُ التركيز — كلٌّ منها يُسجَّل بوقته، ويرى
 | المدرّس التقرير مع الورقة فيقرّر هو.
 |
 | والردع بالمعرفة أصدق من وعدٍ بمنعٍ لا يتحقّق.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table): void {
            $table->boolean('proctored')->default(false)->after('shuffle_answers');

            // كم مخالفةً تُنهي المحاولة تلقائياً — صفر يعني: سجّل ولا تُنهِ
            $table->unsignedTinyInteger('max_violations')->default(0)->after('proctored');
        });

        Schema::table('quiz_attempts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('violations')->default(0)->after('time_spent_seconds');
            $table->boolean('auto_submitted')->default(false)->after('violations');
        });

        Schema::create('attempt_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();

            // blur | focus | paste | copy | fullscreen_exit | tab_hidden | devtools
            $table->string('kind', 24)->index();
            $table->unsignedInteger('at_second')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['attempt_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_events');

        Schema::table('quiz_attempts', function (Blueprint $table): void {
            $table->dropColumn(['violations', 'auto_submitted']);
        });

        Schema::table('quizzes', function (Blueprint $table): void {
            $table->dropColumn(['proctored', 'max_violations']);
        });
    }
};
