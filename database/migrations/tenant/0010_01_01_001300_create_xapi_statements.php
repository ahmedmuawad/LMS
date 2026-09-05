<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | عبارات xAPI.
 |
 | `xapi` مفتاحٌ في الباقات بلا سطر كود. والمعيار يصف التعلّم جملةً:
 | «فلانٌ أتمّ الدرس الفلاني» — ويصلح لما لا يصلح له SCORM: نشاطٌ
 | خارج المنصة، أو تطبيقٌ يرسل ما فعله الطالب فيه.
 |
 | ## مخزنٌ داخلي لا LRS خارجي
 |
 | أكثر المشتركين لا يملكون LRS ولا يعرفون ما هو، ونصبُ واحدٍ لهم
 | كلفةٌ لا يحتاجونها. فالعبارات تُخزَّن عندنا وتُقرأ في تقرير —
 | ومن يملك LRS وجّهها إليه بإعداد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xapi_statements', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            /*
             | الفعل والهدف مفهرَسان، والباقي JSON.
             |
             | التقارير تسأل: «من أتمّ هذا؟» و«ما فعل هذا الطالب؟» —
             | وهما الفعلُ والهدف. وباقي العبارة يُحفظ كما وصل ولا
             | يُفكَّك، فالمعيار واسعٌ ولا نعرف ما سيرسله كل مصدر.
             */
            $table->string('verb', 120)->index();
            $table->string('object_id', 255)->index();
            $table->string('object_name')->nullable();

            $table->decimal('result_score', 6, 2)->nullable();
            $table->boolean('result_success')->nullable();
            $table->boolean('result_completion')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->json('statement');
            $table->timestamp('stored_at');

            /*
             | فهرسان للزمن: واحدٌ للطالب وواحدٌ للمدى.
             |
             | «ما فعل هذا الطالب؟» تقرأ الأول، و«ما نشاط هذا الشهر؟»
             | تقرأ الثاني — والمركّب لا يخدم الثانية لأن سؤالها لا
             | يبدأ بمستخدم.
             */
            $table->index(['user_id', 'stored_at']);
            $table->index('stored_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xapi_statements');
    }
};
