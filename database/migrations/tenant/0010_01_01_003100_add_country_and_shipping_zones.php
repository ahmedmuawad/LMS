<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| دولة المشتري، ومناطق الشحن.
|
| جدول `countries` المركزي فيه `tax_rate` لكل دولة منذ البداية، وشاشةُ
| الإعدادات تَعِد به صراحةً — والسلّة تقرأ نسبةً واحدة. فمشترٍ سعودي
| يُحسَب له ١٤٪ المصرية بدل ١٥٪، وإماراتي يُحسَب له ١٤٪ وضريبتُه ٥٪.
| وذلك مالٌ يُحصَّل باسم الضريبة ولا يُورَّد، أو يُورَّد ناقصاً.
|
| ولا يُعرف بلدُ المشتري إلا إن سُئل: كان الدفع يكتب بلد المشترك في
| خانة المشتري دائماً.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            /*
             | على السلّة لا على الطلب وحده.
             |
             | الضريبة تُعرض قبل الدفع لا بعده؛ ومن يرى إجمالاً في
             | السلّة ثم يجده أعلى في صفحة الدفع يتوقّف عن الشراء.
             */
            $table->char('country', 2)->nullable()->after('currency');
        });

        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->id();
            $table->json('name');

            /*
             | الدول قائمةٌ لا عمود.
             |
             | «الخليج» منطقةٌ واحدة بستّ دول وسعرٍ واحد؛ وصفٌّ لكل
             | دولة يجعل تغيير السعر ستّ تعديلات يُنسى أحدها.
             */
            $table->json('countries');

            $table->unsignedBigInteger('rate_minor')->default(0);

            /*
             | «مجاناً فوق» لكل منطقة.
             |
             | حدُّ الشحن المجاني في مصر ليس حدَّه في السعودية: التكلفة
             | تختلف، ورقمٌ واحد يجعل المشترك يخسر في إحداهما.
             */
            $table->unsignedBigInteger('free_over_minor')->default(0);

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
        Schema::table('carts', fn (Blueprint $table) => $table->dropColumn('country'));
    }
};
