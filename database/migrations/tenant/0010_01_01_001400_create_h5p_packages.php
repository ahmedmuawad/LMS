<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | حزم H5P.
 |
 | H5P محتوًى تفاعليّ (فيديو تفاعلي، بطاقات، سحبٌ وإفلات، فروع)،
 | يُؤلَّف في h5p.org أو Lumi ويُصدَّر ملفّاً واحداً `.h5p`. وكثيرٌ
 | من المدرّسين عندهم ما ألّفوه هناك ولا يملكون تحويله.
 |
 | ## ولماذا لا جدولَ نتائجٍ خاصّاً به
 |
 | H5P يُبلّغ نتائجه بعبارات xAPI — وهي مخزَّنة عندنا في
 | `xapi_statements`. فجدولٌ ثانٍ يعني مصدرين للحقيقة الواحدة،
 | وتقريرين يفترقان. فالنتائج تُقرأ من هناك بالمعرّف الذي يحمله
 | الهدف: `h5p:{package}`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h5p_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();

            $table->string('title')->nullable();

            // المكتبة الرئيسة: نوع المحتوى (H5P.InteractiveVideo وأمثالها)
            $table->string('main_library')->nullable();

            // المجلّد الذي فُكّت فيه الحزمة
            $table->string('path');

            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->index('lesson_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('h5p_packages');
    }
};
