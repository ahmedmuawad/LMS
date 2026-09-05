<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | تجميد سعر الصرف في السلة.
 |
 | الطالب يرى ٢٩٫٩٩ ريالاً فيذهب يُحضر بطاقته، ويعود بعد ربع ساعة
 | فيجد ٣٠٫٤٠ — لأن سعر الصرف تغيّر بينهما. وذلك يُفقد البيعة
 | ويهدم الثقة أكثر ممّا يُكسب من فرق قرشين.
 |
 | فالسعر يُثبَّت عند أوّل تسعيرٍ للسلة، ويبقى نصف ساعة — مدّةٌ
 | تكفي للدفع ولا تُبقي سعراً قديماً إلى الغد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->decimal('locked_rate', 20, 10)->nullable()->after('currency');
            $table->timestamp('rate_locked_at')->nullable()->after('locked_rate');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->dropColumn(['locked_rate', 'rate_locked_at']);
        });
    }
};
