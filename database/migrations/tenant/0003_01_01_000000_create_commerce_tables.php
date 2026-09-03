<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وثيقة 03 — التجارة داخل قاعدة المشترك: مبيعاته هو لطلابه.
 *
 * كل ما يُباع يمرّ بجدول products الموحّد: الكورس والحزمة والخدمة
 * والمنتج المادي والرقمي. فسلة واحدة تحملها جميعاً، وطلب واحد
 * يجمعها، وتقرير واحد يقيسها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->enum('type', ['course', 'bundle', 'subscription', 'service', 'digital', 'physical'])
                ->default('course')->index();
            $table->string('purchasable_type', 64)->nullable();
            $table->unsignedBigInteger('purchasable_id')->nullable();

            $table->string('sku', 64)->nullable()->unique();
            $table->string('slug', 200)->unique();
            $table->json('title');
            $table->json('short_description')->nullable();
            $table->json('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->json('gallery')->nullable();

            $table->string('currency', 3);
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->unsignedBigInteger('sale_price_minor')->nullable();
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();

            $table->boolean('is_taxable')->default(true);
            $table->string('tax_class', 32)->nullable();

            $table->boolean('manage_stock')->default(false);
            $table->integer('stock_qty')->default(0);
            $table->boolean('allow_backorder')->default(false);

            $table->unsignedInteger('weight_grams')->nullable();
            $table->json('dimensions')->nullable();
            $table->boolean('requires_shipping')->default(false);

            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->boolean('featured')->default(false);
            $table->json('seo')->nullable();
            $table->unsignedInteger('sales_count')->default(0);
            $table->unsignedBigInteger('wp_product_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['purchasable_type', 'purchasable_id']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku', 64)->nullable();
            $table->json('options');                       // {"المقاس":"L","اللون":"أسود"}
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->integer('stock_qty')->default(0);
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();         // للزائر قبل أن يسجّل
            $table->string('currency', 3);
            $table->foreignId('coupon_id')->nullable();
            $table->timestamp('reminded_at')->nullable();  // آخر تذكير بسلة متروكة
            $table->unsignedTinyInteger('reminders_sent')->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_minor');
            $table->timestamps();

            $table->unique(['cart_id', 'product_id', 'variant_id']);
        });

        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 48)->unique();
            $table->json('name')->nullable();
            $table->enum('type', ['percent', 'fixed', 'free_shipping'])->default('percent');
            $table->decimal('value', 10, 2)->default(0);
            $table->string('currency', 3)->nullable();
            $table->unsignedBigInteger('min_order_minor')->default(0);
            $table->unsignedBigInteger('max_discount_minor')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->json('applies_to')->nullable();        // منتجات أو أقسام بعينها
            $table->boolean('first_order_only')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_email')->nullable();

            $table->enum('status', [
                'pending', 'awaiting_payment', 'paid', 'processing',
                'completed', 'cancelled', 'refunded', 'failed',
            ])->default('pending')->index();

            $table->string('currency', 3);
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('shipping_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('refunded_minor')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);

            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 48)->nullable();

            $table->json('billing')->nullable();
            $table->json('shipping')->nullable();
            $table->text('notes')->nullable();

            $table->string('gateway', 32)->nullable();
            $table->timestamp('placed_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->unsignedBigInteger('wp_order_id')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('purchasable_type', 64)->nullable();
            $table->unsignedBigInteger('purchasable_id')->nullable();

            // نسخة مجمّدة: تغيير اسم الكورس أو سعره لا يغيّر فاتورة صدرت
            $table->json('title_snapshot');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');

            $table->timestamp('fulfilled_at')->nullable();
            $table->foreignId('instructor_id')->nullable();
            $table->unsignedBigInteger('commission_minor')->default(0);
            $table->timestamps();

            $table->index(['purchasable_type', 'purchasable_id']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('gateway', 32);
            $table->string('gateway_ref')->nullable()->index();
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->enum('status', ['pending', 'authorized', 'captured', 'failed', 'refunded', 'cancelled'])
                ->default('pending')->index();
            $table->string('failure_reason')->nullable();
            $table->string('receipt_path')->nullable();     // إيصال التحويل البنكي
            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->enum('status', ['requested', 'approved', 'rejected', 'processed'])->default('requested')->index();
            $table->text('reason')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('coupon_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('discount_minor');
            $table->timestamps();

            $table->index(['coupon_id', 'user_id']);
        });

        /*
         | أكواد الشحن — الأهم في السوق المصري: الطالب يشتري كرتاً
         | من مكتبة ويفتح به محتواه بلا بطاقة بنكية.
         */
        Schema::create('recharge_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('batch_id')->nullable();
            $table->enum('type', ['wallet', 'course', 'bundle'])->default('wallet');
            $table->string('currency', 3)->nullable();
            $table->unsignedBigInteger('value_minor')->default(0);
            $table->unsignedBigInteger('course_id')->nullable();
            $table->enum('status', ['unused', 'used', 'void', 'expired'])->default('unused')->index();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('recharge_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->enum('type', ['wallet', 'course', 'bundle'])->default('wallet');
            $table->string('currency', 3)->nullable();
            $table->unsignedBigInteger('value_minor')->default(0);
            $table->unsignedBigInteger('course_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // محفظة الطالب: رصيد داخلي يُشحن بالأكواد أو بأي بوابة
        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit'])->index();
            $table->string('currency', 3);
            $table->bigInteger('amount_minor');
            $table->bigInteger('balance_after_minor');
            $table->string('source', 32);                  // code · order · refund · admin
            $table->string('reference')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach ([
            'wallet_transactions', 'recharge_batches', 'recharge_codes', 'coupon_usages',
            'refunds', 'payments', 'order_items', 'orders', 'coupons',
            'cart_items', 'carts', 'product_variants', 'products',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
