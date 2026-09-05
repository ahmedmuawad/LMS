<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات منصّتنا نحن — لا إعدادات المشترك.
 *
 * بيانات التحصيل (إنستاباي والحساب البنكي والمحفظة) كانت في ملف
 * البيئة، فتغييرُ رقم حساب يحتاج دخولاً على الخادم. وهي بيانات
 * تجارية يغيّرها صاحب المنصّة لا المبرمج — فمكانها القاعدة وشاشتها
 * في اللوحة العليا.
 *
 * وما كان سرّاً — مفتاح بوابة — يُخزَّن مشفَّراً: قاعدة تُنسخ
 * احتياطياً وتُقرأ في نسخها لا يجوز أن تحمل مفاتيح الدفع نصّاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->string('key', 128)->primary();
            $table->longText('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
