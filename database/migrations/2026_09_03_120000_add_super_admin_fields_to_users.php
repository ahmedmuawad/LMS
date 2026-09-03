<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مستخدمو القاعدة المركزية = فريقنا نحن، لا مستخدمو أي مشترك.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['super_admin', 'support', 'finance'])->default('support')->after('email');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('last_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'is_active', 'last_seen_at']);
        });
    }
};
