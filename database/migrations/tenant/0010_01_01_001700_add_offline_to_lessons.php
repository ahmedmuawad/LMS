<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | إتاحة الدرس للمشاهدة بلا اتصال.
 |
 | `offline_download` مفتاحٌ في الباقات بلا سطر كود. والمقصود به أن
 | يحفظ الطالب درسه في جهازه فيشاهده في المواصلات أو حيث لا إنترنت —
 | وهو أكثر ما يُطلب في مصر حيث الباقات محدودة.
 |
 | ## وهو غير «يسمح بالتنزيل»
 |
 | `is_downloadable` يعطي الطالب الملفّ نفسه ليخرج من المنصة إلى
 | جهازه ثم إلى غيره. وهذا يحفظه داخل مخزن المتصفّح للمنصة وحدها،
 | فيُشاهَد فيها ولا يُنسَخ بضغطة. وهما خياران مختلفان، فلا يُدمجان
 | في مفتاحٍ واحد يظنّ المدرّس أنه يفتح أحدهما فيفتح الآخر.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->boolean('is_offline')->default(false)->after('is_downloadable');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn('is_offline');
        });
    }
};
