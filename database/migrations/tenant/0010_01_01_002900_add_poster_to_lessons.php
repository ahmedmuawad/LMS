<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| صورة الفيديو قبل التشغيل.
|
| مشغّلٌ بلا `poster` يعرض مستطيلاً أسود حتى يصل أوّل إطار — وعلى
| شبكةٍ مصرية بطيئة يبقى الأسود ثوانيَ يظنّ فيها الطالب أن الدرس
| معطوب فيعيد التحميل.
|
| والصورة تُختار لا تُستخرج: أوّلُ إطارٍ في الفيديو غالباً وجهٌ
| مقطوع أو شاشةٌ فارغة.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->string('poster', 2000)->nullable()->after('video_id');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn('poster');
        });
    }
};
