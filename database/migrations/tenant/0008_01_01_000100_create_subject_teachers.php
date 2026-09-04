<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مَن يُدرّس أي مادة، وفي أي فرع.
 *
 * السنتر ليس مدرّساً واحداً لكل مادة: الرياضيات فيها ثلاثة، ولكلٍّ
 * مواعيده وطلبته. وبغير هذا الجدول كانت شاشة المجموعة تعرض **كل**
 * مستخدمي المنصّة في قائمة المدرّس — فيُسنَد صفّ الكيمياء إلى
 * مدرّس اللغة العربية بضغطة سهو.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('center_subject_teacher', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subject_id')->constrained('center_subjects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // مدرّس قد يُدرّس في فرع دون آخر — والفراغ يعني كل الفروع
            $table->foreignId('branch_id')->nullable()->constrained('center_branches')->cascadeOnDelete();

            $table->decimal('share_percent', 5, 2)->nullable();   // نصيبه من إيراد المجموعة
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['subject_id', 'user_id', 'branch_id'], 'subject_teacher_unique');
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('center_subject_teacher');
    }
};
