<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تذاكر الدخول كمشترك: تُستهلك مرة واحدة وتنتهي خلال دقيقة،
 * فلا تصلح رابطاً دائماً لو تسرّبت من سجلّ أو سِجِل متصفح.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user_impersonation_tokens', function (Blueprint $table): void {
            $table->string('token', 128)->primary();
            $table->string('tenant_id');
            $table->string('user_id');
            $table->string('auth_guard');
            $table->string('redirect_url');
            $table->timestamp('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants')
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_impersonation_tokens');
    }
};
