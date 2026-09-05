<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | المراجعة الذكية: ما أخطأ فيه الطالب يعود إليه حتى يُتقنه.
 |
 | الطالب يسلّم الامتحان، ويرى درجته، ثم لا يعود إليه أبداً — فيبقى
 | ما أخطأ فيه خطأً. وهذه أكثر ميزةٍ يُسأل عنها في السوق المصري،
 | وأرخص ما يُبنى: البيانات موجودة في `quiz_answers` منذ اليوم الأول.
 |
 | ## لماذا جدولٌ مستقل لا استعلامٌ على الإجابات
 |
 | «ما أخطأ فيه» سؤالٌ سهل، لكنّ «ما لم يُتقنه بعد» ليس كذلك: يحتاج
 | عدّاداً لكل سؤال يرتفع بالخطأ وينخفض بالصواب. وحسابُه من الإجابات
 | في كل فتحة يعني ضمّ ثلاثة جداول لكل طالب — والشاشة تُفتح كل يوم.
 |
 | ## والإتقان بصوابين متتاليين لا بصوابٍ واحد
 |
 | صوابٌ واحد قد يكون تخميناً — والاختيار من أربعة يُصيب ربعه بلا
 | معرفة. والثاني بعد يومٍ يعني أنه ثبت. والعدد قابل للضبط في
 | إعدادات التعليم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();

            // من أين جاء — لتجميع المراجعة بالكورس
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();

            $table->unsignedSmallInteger('wrong_count')->default(1);
            $table->unsignedSmallInteger('streak')->default(0);
            $table->unsignedSmallInteger('seen_count')->default(0);

            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('mastered_at')->nullable();

            $table->timestamps();

            // سؤالٌ واحد لكل طالب: الخطأ الثاني يرفع العدّاد ولا يُنشئ صفّاً
            $table->unique(['user_id', 'question_id']);

            // «ما لم يُتقنه هذا الطالب» — أكثر استعلامٍ يُنفَّذ هنا
            $table->index(['user_id', 'mastered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_items');
    }
};
