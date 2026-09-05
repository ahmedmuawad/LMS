<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | أجهزة الطالب.
 |
 | `device_limit` مفتاحٌ في الباقات بلا سطر كود: الحساب الواحد
 | يُتداول بين عشرة، فيدفع واحدٌ ويشاهد عشرة. وهذا الجدول يجعل
 | لكل حساب عدداً محدوداً من الأجهزة المعروفة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            /*
             | البصمة تجزئة لا وصف.
             |
             | الوصف الخام (نظام · متصفّح · مقاس) يُعرَّف صاحبه، وحفظه
             | يجعل قاعدتنا تحمل ما لا نحتاجه. والتجزئة تكفي للمطابقة
             | ولا تكفي للتعريف.
             */
            $table->string('fingerprint', 64);

            // للعرض وحده: «Chrome على ويندوز» — ليقرأ الطالب ما يفكّه
            $table->string('label', 120)->nullable();

            $table->string('last_ip', 45)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            /*
             | الثقة تُمنح ولا تُفترض.
             |
             | الجهاز الجديد فوق الحدّ يُرفض دخوله، ولا يُطرَد جهازٌ
             | قائم تلقائياً: من يذاكر الآن لا يُقطع عليه ليدخل غيره.
             */
            $table->boolean('is_trusted')->default(true);

            $table->timestamps();

            $table->unique(['user_id', 'fingerprint'], 'user_devices_owner_print_unique');
            $table->index(['user_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
