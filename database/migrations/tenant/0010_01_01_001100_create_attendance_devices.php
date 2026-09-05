<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | أجهزة الحضور: بصمة أو كارت أو QR.
 |
 | `attendance_devices` مفتاحٌ في الباقات بلا سطر كود. والسنتر الذي
 | يشتري جهاز بصمة يريده أن يكتب في المنصة مباشرةً، لا أن يُصدَّر
 | ملفّ Excel كل ليلة ويُرفَع يدوياً.
 |
 | ## لماذا نقطةُ استقبال لا تكاملٌ مع طراز
 |
 | أجهزة البصمة في السوق المصري عشرات الطُّرُز (ZKTeco وHikvision
 | ونسخ صينية بلا اسم)، وبروتوكولاتها مغلقة ومتفاوتة. وربطُ طرازٍ
 | بعينه يخدم من اشتراه ويترك البقية.
 |
 | فالمنصة تفتح باباً واحداً بسيطاً: الجهاز — أو وسيطٌ صغير عنده —
 | يرسل «هذا الكود حضر الآن». وهذا ما يستطيعه كل جهاز، إمّا مباشرةً
 | أو بسكربت يقرأ سجلّه ويُرسل.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_devices', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);

            // fingerprint | card | qr | turnstile
            $table->string('kind', 16)->default('fingerprint');

            $table->foreignId('branch_id')->nullable()->constrained('center_branches')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('center_rooms')->nullOnDelete();

            /*
             | المفتاح لا يُخزَّن نصّاً — كمفاتيح الواجهة البرمجية.
             |
             | وجهازٌ في ممرّ السنتر أسهل وصولاً من خادم: من فكّه
             | وقرأ ذاكرته لا ينبغي أن يحصل على مفتاحٍ يعمل.
             */
            $table->string('prefix', 12)->index();
            $table->string('token_hash', 64)->unique();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        /*
         | سجلّ البصمات الخام.
         |
         | يُكتب أولاً ثم يُطابَق بالحصة. فبصمةٌ وصلت والحصة لم تبدأ
         | بعد — أو الطالب ليس في مجموعتها — لا تُرمى: تبقى في
         | السجلّ ليراها صاحب السنتر ويعرف أن الجهاز يعمل وأن
         | المشكلة في الجدول لا في الجهاز.
         */
        Schema::create('device_punches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained('attendance_devices')->cascadeOnDelete();

            $table->string('code', 32)->index();
            $table->timestamp('punched_at');

            // matched | unknown_code | no_session | duplicate
            $table->string('result', 16)->default('matched')->index();
            $table->foreignId('session_id')->nullable()->constrained('center_sessions')->nullOnDelete();

            $table->timestamp('created_at')->nullable();

            $table->index(['device_id', 'punched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_punches');
        Schema::dropIfExists('attendance_devices');
    }
};
