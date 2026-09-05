<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | المخزون والعُهد.
 |
 | `inventory` مفتاحٌ في الباقة الاحترافية بلا سطر كود. والسنتر
 | يبيع مذكّرات وكتباً، ويسلّم أدواتٍ لمدرّسيه، ويفقد بعضها — وكلّه
 | اليوم في دفترٍ ورقي أو في رأس صاحبه.
 |
 | ## صنفٌ وحركات لا رصيدٌ يُكتب
 |
 | الرصيد عمودٌ يُحدَّث، والحركات سجلٌّ يُضاف إليه. ولو حُفظ الرصيد
 | وحده لما عُرف من أخذ ومتى ولا لماذا نقص — وهو أوّل سؤالٍ يُسأل
 | حين يختلف الجرد.
 |
 | فالرصيد عمودٌ محسوب يُسرِّع القوائم، والحقيقة في الحركات.
 |
 | ## والعهدة حركةٌ تُردّ لا نوعُ صنف
 |
 | الكتاب يُباع فيخرج ولا يعود؛ وجهاز العرض يُسلَّم للمدرّس ويعود.
 | والفرق في الحركة لا في الصنف — فالمِسطرة تُباع لطالبٍ وتُسلَّم
 | عهدةً لآخر.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('center_branches')->nullOnDelete();

            $table->string('name');
            $table->string('sku', 64)->nullable();

            // book | notes | tool | other
            $table->string('kind', 24)->default('notes');

            $table->unsignedBigInteger('price_minor')->default(0);
            $table->unsignedBigInteger('cost_minor')->default(0);

            $table->integer('stock_qty')->default(0);

            // حدُّ التنبيه: تحته يُنبَّه صاحب السنتر قبل أن ينفد
            $table->unsignedInteger('reorder_level')->default(0);

            $table->boolean('is_sellable')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['kind', 'branch_id']);
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();

            // in | out | sale | custody | return | damaged | lost | count
            $table->string('type', 16);

            // موجبة للداخل وسالبة للخارج — فالمجموع هو الرصيد
            $table->integer('qty');

            $table->foreignId('student_id')->nullable()->constrained('center_students')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('reason')->nullable();

            // للعهدة: متى رُدّت. وما لم يُردّ بعدُ يبقى null
            $table->timestamp('returned_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['item_id', 'created_at']);
            $table->index(['type', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_items');
    }
};
