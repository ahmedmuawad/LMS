<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | محادثات المساعد الدراسي.
 |
 | `ai_assistant` مفتاحٌ في الباقات بلا سطر كود. والمساعد يجيب الطالب
 | عن درسه من مادّة الدرس نفسها.
 |
 | ## ولماذا تُحفظ المحادثة
 |
 | الطالب يسأل ثم يغلق الصفحة ويعود، ومحادثةٌ تضيع بالتحديث تجعله
 | يعيد السؤال — فيدفع المشترك ثمن الطلب مرّتين. وحفظُها يجعل
 | المساعد يذكر ما قيل، فلا يشرح المصطلح من أوّله في كل رسالة.
 |
 | وهي مادّة المشترك لا مادّتنا: تعيش في قاعدته، وتُحذف بحذف صاحبها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->cascadeOnDelete();

            // student | assistant
            $table->string('role', 16);
            $table->text('body');

            $table->timestamps();

            $table->index(['user_id', 'lesson_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
