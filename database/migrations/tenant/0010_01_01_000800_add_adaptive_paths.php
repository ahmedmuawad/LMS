<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | المسار التكيّفي: المنهج يتفرّع بنتيجة الاختبار.
 |
 | `adaptive_learning` مفتاحٌ في الباقات بلا سطر كود. والمنهج اليوم
 | خطٌّ واحد: من رسب في اختبار الوحدة يمضي إلى التي بعدها كمن
 | أتقنها، ومن أتقنها يُجبَر على مراجعةٍ لا يحتاجها.
 |
 | والقاعدة هنا بسيطة عمداً: «إن كانت نتيجتك في هذا الاختبار دون
 | كذا فافتح لك هذا العنصر». لا محرّك قواعد ولا شجرة شروط — تلك
 | تُبنى في أسبوع وتُفهَم في شهر، ولا يستعملها مدرّس.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();

            // الاختبار الذي تُقاس نتيجته
            $table->foreignId('trigger_item_id')->constrained('course_items')->cascadeOnDelete();

            // below | above
            $table->string('comparison', 8)->default('below');
            $table->unsignedTinyInteger('threshold')->default(50);

            // العنصر الذي يُفتح — علاجيٌّ للراسب أو متقدّمٌ للمتقن
            $table->foreignId('target_item_id')->constrained('course_items')->cascadeOnDelete();

            /*
             | العلاج يُفتح ولا يُلزَم افتراضاً.
             |
             | إلزامُ الراسب بمراجعةٍ قبل أن يمضي صحيحٌ تربوياً وثقيلٌ
             | نفسياً — فيُترك للمدرّس أن يقرّره لطلابه هو.
             */
            $table->boolean('blocks_progress')->default(false);
            $table->timestamps();

            $table->index(['course_id', 'trigger_item_id']);
        });

        Schema::create('unlocked_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('course_items')->cascadeOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('learning_rules')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['enrollment_id', 'item_id'], 'unlocked_items_owner_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unlocked_items');
        Schema::dropIfExists('learning_rules');
    }
};
