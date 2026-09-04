<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تغيير بريد الدخول — بتأكيد العنوان الجديد قبل اعتماده.
 *
 * تبديل البريد في مكانه خطر: خطأ مطبعي واحد يقفل الحساب على صاحبه
 * إلى الأبد، ومن استولى على جلسة يستطيع تحويل الحساب إلى بريده ثم
 * «نسيت كلمة المرور». فالجديد يبقى معلّقاً حتى يُفتح رابطه من
 * صندوقه هو، والقديم يبقى صالحاً للدخول حتى تلك اللحظة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('pending_email')->nullable()->after('email');
            $table->string('pending_email_token', 64)->nullable()->after('pending_email');
            $table->timestamp('pending_email_sent_at')->nullable()->after('pending_email_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['pending_email', 'pending_email_token', 'pending_email_sent_at']);
        });
    }
};
