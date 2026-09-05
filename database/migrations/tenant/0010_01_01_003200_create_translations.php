<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| نصوص الواجهة — ترجمتها وإعادة صياغتها.
|
| ## لغةٌ كانت معلنةً وفارغة
|
| المنصّة تُوجّه `/en/` وتكتب `lang="en" dir="ltr"`، ولا ملفَّ ترجمةٍ
| واحد في المشروع: كل `__('نصّ عربي')` يعيد العربية. فزائرٌ اختار
| الإنجليزية يرى صفحةً إنجليزية الاتجاه عربية النصّ.
|
| ## وإعادة الصياغة حاجةٌ لا ترف
|
| «كورس» عند مدرّس، و«دورة» عند مركز تدريب، و«مقرّر» عند جامعة.
| ومنصّةٌ تفرض كلمتها تُقرأ غريبةً على طلبة كلٍّ منهم.
|
| ## والمفتاح هو النصّ الأصلي
|
| لأن `__()` في هذا المشروع تأخذ العربية مفتاحاً لا رمزاً مثل
| `courses.title`. وتجزئتُه تُفهرَس لأن النصّ قد يطول.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table): void {
            $table->id();
            $table->string('locale', 5)->index();

            // النصّ كما كُتب في الكود — هو المفتاح
            $table->text('source');

            /*
             | تجزئةُ المصدر تُفهرَس لا المصدر نفسه.
             |
             | نصٌّ يبلغ ثلاثمئة حرف لا يصلح مفتاحاً فريداً في MySQL
             | (حدّ الفهرس ٧٦٧ بايت، والعربي ثلاثة بايتات للحرف).
             */
            $table->char('source_hash', 40);

            $table->text('value')->nullable();

            $table->timestamps();

            $table->unique(['locale', 'source_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
