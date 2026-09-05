<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | توقيت التحصيل: مقدَّم أم مؤخَّر.
 |
 | كان الطالب يُسجَّل في مجموعة فلا يُقيَّد عليه شيء: يفتح المدرّس
 | ملفّه فيجد «المستحق عليه ٠٫٠٠» وهو مدينٌ بقسط الشهر. الفاتورة
 | كانت تُصدَر بأمرٍ منفصل على المجموعة كلّها، وقلّ من يعرفه.
 |
 | والتوقيت يُكتب على المجموعة لا على المركز: مجموعة الثانوية تُدفع
 | مقدّماً، ومجموعة التقوية تُدفع آخر الشهر — وقاعدةٌ واحدة للمركز
 | كلّه تُجبر صاحبه على مخالفتها يدوياً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('center_groups', function (Blueprint $table): void {
            $table->string('billing_timing', 16)->default('prepaid')->after('price_minor');
        });
    }

    public function down(): void
    {
        Schema::table('center_groups', function (Blueprint $table): void {
            $table->dropColumn('billing_timing');
        });
    }
};
