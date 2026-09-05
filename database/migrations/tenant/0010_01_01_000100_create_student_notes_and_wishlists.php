<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | ملاحظات الطالب وقائمة أمنياته.
 |
 | الجدولان الوحيدان الناقصان من لوحة الطالب. وقد كانت القائمة
 | الجانبية تعرض رابطيهما قبل بنائهما — وهذا ما نُغلقه هنا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            /*
             | الملاحظة تُعلَّق على درس أو على كورس أو على لا شيء.
             |
             | حذف الدرس لا يحذف ملاحظة الطالب: ما كتبه بيده ملكه،
             | ويبقى في «ملاحظاتي» بلا سياقٍ خيرٌ من أن يختفي.
             */
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->nullOnDelete();

            // ثانية الفيديو التي كُتبت عندها — تعيد الطالب إلى موضعه
            $table->unsignedInteger('at_second')->nullable();

            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_pinned', 'updated_at']);
            $table->index(['user_id', 'lesson_id']);
        });

        Schema::create('wishlists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            /*
             | نوعٌ ومعرّف بدل علاقة متعدّدة الأشكال بجدول لكل نوع:
             | الأمنية تقع على كورس أو خدمة أو منتج، وثلاثة جداول
             | لثلاثة أنواع تُعقّد شاشةً واحدة تعرضها كلها معاً.
             */
            $table->string('itemable_type', 32);
            $table->unsignedBigInteger('itemable_id');

            // سعر الإضافة: نُخبر الطالب حين ينزل السعر عمّا رآه
            $table->unsignedBigInteger('price_minor_at_add')->nullable();
            $table->string('currency', 3)->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'itemable_type', 'itemable_id'], 'wishlists_owner_item_unique');
            $table->index(['itemable_type', 'itemable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('notes');
    }
};
