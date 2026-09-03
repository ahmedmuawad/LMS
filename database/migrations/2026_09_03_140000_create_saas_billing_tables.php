<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فوترة طبقة الـ SaaS: اشتراك المشترك عندنا نحن.
 * لا علاقة لها بمبيعات المشترك لطلابه — تلك في قاعدته هو.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('plan_key', 64);
            $table->enum('status', ['trialing', 'active', 'past_due', 'paused', 'cancelled', 'expired'])
                ->default('trialing')->index();
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor');       // السعر المتفق عليه وقت الاشتراك
            $table->enum('interval', ['month', 'year'])->default('month');
            $table->unsignedTinyInteger('interval_count')->default(1);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable()->index();
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('cancel_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->string('gateway', 32)->nullable();
            $table->string('gateway_ref')->nullable();
            $table->unsignedTinyInteger('failed_charges')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('plan_key')->references('key')->on('plans');
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 32)->unique();           // ترقيم متسلسل غير قابل للتلاعب
            $table->string('tenant_id');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->enum('status', ['draft', 'open', 'paid', 'overdue', 'void', 'refunded'])
                ->default('open')->index();
            $table->string('currency', 3);
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->string('tax_label', 32)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->json('lines');                            // نسخة مجمّدة من البنود وقت الإصدار
            $table->json('billing_details')->nullable();      // اسم المشترك وعنوانه ورقمه الضريبي وقتها
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('invoice_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('gateway', 32);
            $table->string('reference')->nullable();
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded'])->default('pending')->index();
            $table->string('failure_reason')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
    }
};
