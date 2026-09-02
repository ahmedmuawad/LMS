<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-009 — القاعدة المركزية: كل ما يخصّ المشترك كـ«عميل لنا».
 * بيانات منصّته نفسها (الطلاب، الكورسات، الطلبات) تعيش في قاعدته المستقلة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->string('id')->primary();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('owner_name')->nullable();
            $table->string('owner_email')->index();
            $table->string('owner_phone', 32)->nullable();

            // ADR-010 — أنماط المنصة
            $table->enum('platform_mode', ['solo', 'marketplace', 'center', 'hybrid'])->default('solo');
            $table->enum('delivery_mode', ['recorded', 'live', 'blended'])->default('recorded');
            $table->boolean('center_enabled')->default(false);
            $table->string('theme', 64)->default('solo-academy');

            // ADR-014 — الدولة والعملة واللغة من لحظة الإنشاء
            $table->char('country', 2)->default('EG');
            $table->char('currency', 3)->default('EGP');
            $table->string('locale', 5)->default('ar');
            $table->string('timezone', 64)->default('Africa/Cairo');

            // دورة الحياة
            $table->enum('status', [
                'provisioning', 'trialing', 'active', 'past_due',
                'suspended', 'cancelled', 'archived',
            ])->default('provisioning')->index();
            $table->string('plan_key', 64)->nullable()->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            // البنية: قاعدة مستقلة، وقد تكون على خادم مختلف عند التوسّع
            $table->string('db_shard', 32)->default('default');
            $table->string('provision_error')->nullable();
            $table->timestamp('provisioned_at')->nullable();

            $table->json('data')->nullable();   // يستخدمه stancl للأعمدة الافتراضية
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
