<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | سجلّ الروابط المكسورة.
 |
 | المشترك يُرحّل موقعه من ووردبريس، أو يُغيّر رابط كورس، فتموت
 | روابطُ في جوجل وفي منشوراتٍ نُشرت. ولا يعرف أيّها مات إلا حين
 | يشكو طالب.
 |
 | ## صفٌّ لكل مسار لا لكل زيارة
 |
 | رابطٌ ميت يُطلَب ألف مرّة؛ وألفُ صفٍّ لا تقول أكثر ممّا يقوله
 | صفٌّ بعدّاد — وتملأ القاعدة بما لا يُقرأ.
 |
 | ## وتحويلُه من هنا مباشرةً
 |
 | معرفةُ الرابط الميت بلا إصلاحه لا تنفع. فالشاشة تُنشئ التحويلة
 | ٣٠١ بضغطة، ويصير السجلّ قائمةَ عملٍ تُفرَغ لا تقريراً يُقرأ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('not_found_logs', function (Blueprint $table): void {
            $table->id();

            $table->string('path', 2048);

            /*
             | البصمة مفتاحُ التفرّد لا المسار.
             |
             | MySQL لا تفهرس عموداً بطول ٢٠٤٨ محرفاً، والمسار قد
             | يطول. والبصمة ٤٠ محرفاً تُفهرَس وتُقارَن.
             */
            $table->char('path_hash', 40)->unique();

            $table->string('referrer', 2048)->nullable();
            $table->unsignedInteger('hits')->default(1);
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_resolved')->default(false);

            $table->timestamps();

            $table->index(['is_resolved', 'hits']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_logs');
    }
};
