<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | نسخة واتساب لكل مشترك.
 |
 | الرسائل تخرج برقم المدرّس — الرقم الذي يعرفه طلابه — لا برقمٍ
 | واحد للمنصّة كلّها. ورقمٌ واحد يرسل باسم مئة مدرّس هو ما يجعل
 | واتساب يحظره.
 |
 | ## ولماذا في السجلّ المركزي لا في إعدادات المشترك
 |
 | لوحة المنصّة تحتاج أن ترى حال كل نسخة لتُصلحها حين تتعطّل —
 | وقراءةُ ذلك من إعدادات كل مشترك تعني فتح مئة قاعدة في شاشةٍ
 | واحدة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('wa_instance')->nullable()->after('plan_key');
            $table->text('wa_token')->nullable()->after('wa_instance');
            $table->string('wa_number', 32)->nullable()->after('wa_token');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['wa_instance', 'wa_token', 'wa_number']);
        });
    }
};
