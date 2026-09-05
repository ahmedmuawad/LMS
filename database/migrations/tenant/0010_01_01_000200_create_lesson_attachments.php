<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | مرفقات الدرس: ملفّ PDF أو Word يقرؤه الطالب داخل المنصة.
 |
 | كانت المرفقات عمود JSON في `lessons` يحمل روابط عامة — أي رابط
 | يُنسخ ويُرسَل في مجموعة واتساب فيقرؤه من لم يدفع. وهذا الجدول
 | يجعل لكل مرفق هويةً تُحرَس: من يفتحه، وهل يُنزَّل، ومتى فُتح.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();

            $table->string('title')->nullable();

            /*
             | التنزيل قرارٌ لكل ملف لا لكل درس.
             |
             | المدرّس يعطي ورقة المراجعة لتُطبع، ويمنع تنزيل بنك
             | الأسئلة. وعَلَمٌ واحد على الدرس كلّه يجبره على اختيار
             | أحدهما للاثنين.
             */
            $table->boolean('is_downloadable')->default(false);
            $table->boolean('watermark')->default(true);

            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['lesson_id', 'position']);
        });

        /*
         | سجلّ الفتحات.
         |
         | الحماية الكاملة من التصوير مستحيلة في متصفّح. وما يمكن
         | فعله أن يكون كل فتح موسوماً باسم فاتحه، فمن سرّب يُعرَف —
         | والردع بالمعرفة أصدق من وعدٍ بمنعٍ لا يتحقّق.
         */
        Schema::create('attachment_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attachment_id')->constrained('lesson_attachments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 16)->default('view'); // view | download
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['attachment_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_views');
        Schema::dropIfExists('lesson_attachments');
    }
};
