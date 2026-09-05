<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | فصول الفيديو.
 |
 | محاضرةُ ساعةٍ بلا فصول شريطٌ أسود: الطالب يريد «المسألة الثالثة»
 | فيسحب ويُخطئ ويسحب. والفصول تجعلها قائمةً يضغط فيها سطراً.
 |
 | وهي أنفع ما يكون في المراجعة قبل الامتحان — حين لا يُعاد الدرس
 | كلّه بل يُنتقى منه.
 |
 | ## جدولٌ لا عمود JSON
 |
 | الفصل يُضاف ويُحذف ويُعاد ترتيبه واحداً واحداً، وشاشةُ إدارةٍ
 | لعمود JSON تعني تحرير نصٍّ خام — يُخطئ فيه المدرّس فاصلةً فيضيع
 | العمود كلّه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_chapters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();

            $table->unsignedInteger('at_second')->default(0);
            $table->string('title');

            $table->timestamps();

            $table->index(['lesson_id', 'at_second']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_chapters');
    }
};
