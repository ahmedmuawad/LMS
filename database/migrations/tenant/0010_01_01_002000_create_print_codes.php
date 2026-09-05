<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | رموز QR على المذكرات المطبوعة.
 |
 | المدرّس المصري يبيع مذكرةً ورقية، والطالب يقف عند مسألةٍ فيها
 | فلا يجد من يشرحها. ورمزٌ مطبوع بجوارها يفتح شرحها بالفيديو —
 | فتصير المذكرة باباً إلى المنصة لا بديلاً عنها.
 |
 | ## ولماذا رمزٌ ثابت لا رابطٌ مباشر
 |
 | الرابط المباشر يُطبَع فيُدفَن: يتغيّر معرّف الدرس أو يُعاد ترتيب
 | المنهج فتصير آلاف المذكرات المطبوعة تشير إلى لا شيء. والرمز
 | وسيطٌ يُحوَّل هدفه من اللوحة بعد الطباعة.
 |
 | ## والعدّاد يقيس ما لا يُقاس
 |
 | كم مذكرةً بيعت؟ لا يعرفه المدرّس. وكم رمزاً مُسح؟ يعرفه — وهو
 | أقرب ما يُعرف عن انتشار مذكرته.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_codes', function (Blueprint $table): void {
            $table->id();

            // الرمز في الرابط: /q/{code}
            $table->string('code', 24)->unique();

            $table->string('label');

            // الهدف: درس أو اختبار أو رابط خارجي
            $table->string('target_type', 24)->default('lesson');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_url')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('scans')->default(0);
            $table->timestamp('last_scan_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_codes');
    }
};
