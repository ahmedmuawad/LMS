<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أعمدة المصادقة الكاملة — وثيقة 11 §ب.
 *
 * كانت شاشة الدخول وحدها موجودة: لا تسجيل ولا تحقّق بريد ولا
 * استعادة كلمة مرور ولا توثيق بخطوتين. منصّة تبيع كورسات ولا
 * يستطيع أحد التسجيل فيها ليست منصّة بعد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // السرّ مشفَّر في التطبيق لا مُهشَّم: نحتاج قراءته للتحقّق
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');

            // تاريخ آخر تغيير لكلمة المرور — لسياسة انتهاء الصلاحية
            $table->timestamp('password_changed_at')->nullable()->after('two_factor_confirmed_at');

            $table->boolean('terms_accepted')->default(false)->after('password_changed_at');
        });

        /*
         | رمز تحقّق الهاتف.
         |
         | جدول منفصل لا عمود: الرمز يُطلب مرّات، ولكل طلب حدّ ومهلة
         | وعدّ محاولات — وعمودٌ واحد لا يحمل ذلك.
         */
        Schema::create('phone_verification_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('phone', 32);
            $table->string('code_hash', 64);          // مُهشَّم: سجلّ مسروق لا يُعطي رمزاً صالحاً
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_verification_codes');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
                'password_changed_at', 'terms_accepted',
            ]);
        });
    }
};
