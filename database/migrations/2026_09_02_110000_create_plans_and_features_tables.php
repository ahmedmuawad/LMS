<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-011 — كل خيار مبني في النظام؛ الباقة والإعدادات هما ما يحدّدان المتاح.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table): void {
            $table->string('key', 64)->primary();
            $table->json('name');
            $table->json('description')->nullable();
            $table->enum('type', ['boolean', 'limit', 'quota'])->default('boolean');
            $table->string('unit', 32)->nullable();       // طالب · جيجابايت · دقيقة
            $table->string('group', 32)->default('general');
            $table->string('resets', 16)->nullable();     // month | year — للحصص المتجدّدة
            $table->boolean('is_visible')->default(true); // تظهر في جدول مقارنة الباقات
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table): void {
            $table->string('key', 64)->primary();
            $table->json('name');
            $table->json('tagline')->nullable();
            $table->json('prices');                        // {"EGP": 49900, "SAR": 7900} بأصغر وحدة
            $table->enum('interval', ['month', 'year'])->default('month');
            $table->unsignedTinyInteger('interval_count')->default(1);
            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->json('modes')->nullable();             // الأنماط التي تناسبها هذه الباقة
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('plan_features', function (Blueprint $table): void {
            $table->id();
            $table->string('plan_key', 64);
            $table->string('feature_key', 64);
            $table->string('value', 64);                   // "1" · "500" · "unlimited"
            $table->timestamps();

            $table->unique(['plan_key', 'feature_key']);
            $table->foreign('plan_key')->references('key')->on('plans')->cascadeOnDelete();
            $table->foreign('feature_key')->references('key')->on('features')->cascadeOnDelete();
        });

        // تجاوز فردي لمشترك بعينه (عرض خاص · تعويض · اتفاق مؤسسي)
        Schema::create('tenant_features', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('feature_key', 64);
            $table->string('value', 64);
            $table->string('reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'feature_key']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        // القياس: عدّادات محدّثة بالأحداث، لا COUNT() لحظي
        Schema::create('usage_records', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('feature_key', 64);
            $table->string('period', 7)->nullable();       // 2026-09 للحصص المتجدّدة، null للحدود الثابتة
            $table->unsignedBigInteger('used')->default(0);
            $table->timestamp('notified_80_at')->nullable();
            $table->timestamp('notified_95_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'feature_key', 'period']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
        Schema::dropIfExists('tenant_features');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('features');
    }
};
