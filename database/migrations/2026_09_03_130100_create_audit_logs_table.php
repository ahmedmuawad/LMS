<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ أفعال فريق المنصة على حسابات المشتركين.
 *
 * كل تدخّل منا في حساب عميل — تعليق، تغيير باقة، دخول كمشترك —
 * يُقيَّد باسم فاعله ووقته وعنوانه. هذا التزام تعاقدي لا رفاهية.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('action')->index();
            $table->string('tenant_id')->nullable()->index();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
