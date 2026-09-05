<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | مفاتيح الواجهة البرمجية.
 |
 | `api_access` مفتاحٌ في الباقات بلا سطر كود: لا مسار ولا مفتاح
 | ولا توثيق. والمشترك الذي يريد ربط منصّته بنظام مدرسته أو ببرنامج
 | محاسبته لا يجد باباً.
 |
 | ## المفتاح لا يُخزَّن
 |
 | يُعرض مرة واحدة عند إنشائه ثم تُحفظ تجزئته وحدها. فتسريبُ قاعدة
 | البيانات لا يسرّب مفاتيح أحد — ومنصّةٌ تحفظ المفاتيح نصّاً تجعل
 | نسخةً احتياطية مسروقة كارثةً مضاعفة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('name', 100);

            // أول ثمانية أحرف — لتمييز المفتاح في القائمة بلا كشفه
            $table->string('prefix', 12)->index();
            $table->string('token_hash', 64)->unique();

            /*
             | النطاقات قائمة مغلقة لا نصّ حرّ.
             |
             | ومفتاحٌ للقراءة وحده هو الافتراض: أكثر التكاملات تقرأ،
             | ومفتاحٌ يكتب بالخطأ يُفسد بيانات مشترك لا نملك ردّها.
             */
            $table->json('scopes')->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
