<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-009 — قاعدة المشترك: كل ما يخصّ منصّته هو.
 * لا يوجد عمود tenant_id في أي جدول هنا — العزل على مستوى القاعدة نفسها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 32)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->boolean('legacy_hash')->default(false);   // ADR-007 — كلمات مرور WordPress
            $table->string('locale', 5)->default('ar');
            $table->string('timezone', 64)->nullable();
            $table->string('avatar_path')->nullable();
            $table->enum('status', ['active', 'pending', 'suspended'])->default('active');
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedBigInteger('wp_user_id')->nullable()->index();  // مفتاح الترحيل
            $table->json('meta')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // إعدادات المشترك — طبقة الإعدادات (وثيقة 05)
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 64)->index();
            $table->string('key', 128);
            $table->json('value')->nullable();
            $table->boolean('is_translatable')->default(false);
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();

            $table->unique(['group', 'key']);
        });

        // تفعيل/تعطيل الموديولات لهذا المشترك
        Schema::create('modules', function (Blueprint $table): void {
            $table->string('key', 64)->primary();
            $table->boolean('enabled')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
