<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وثيقة 03 — الخدمات والحجوزات.
 *
 * الخدمة تُباع بوقت لا بنسخة: استشارة، جلسة تقوية، مراجعة ملف.
 * فالمخزون هنا ساعات المقدّم، والتعارض ممنوع كما في السنتر.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 200)->unique();
            $table->json('title');
            $table->json('excerpt')->nullable();
            $table->json('description')->nullable();
            $table->foreignId('cover_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('taxonomies')->nullOnDelete();

            $table->enum('type', ['appointment', 'delivery', 'subscription'])->default('appointment');
            $table->string('currency', 3);
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->enum('price_type', ['fixed', 'hourly', 'quote'])->default('fixed');

            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->unsignedSmallInteger('lead_hours')->default(24);      // أقل مهلة للحجز
            $table->unsignedSmallInteger('cancel_hours')->default(24);    // مهلة الإلغاء المجاني
            $table->unsignedSmallInteger('max_per_slot')->default(1);
            $table->unsignedSmallInteger('delivery_days')->default(0);    // للخدمات غير الموعدية

            $table->json('requirements')->nullable();                     // ما يرسله العميل
            $table->json('deliverables')->nullable();
            $table->enum('location', ['online', 'onsite', 'both'])->default('online');
            $table->string('meeting_provider', 24)->nullable();

            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->json('seo')->nullable();
            $table->unsignedInteger('bookings_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('service_providers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['service_id', 'user_id']);
        });

        /*
         | ساعات العمل: قالب أسبوعي لكل مقدّم. الاستثناءات (إجازة،
         | ساعة إضافية) تُسجَّل منفصلة كي لا يُعاد بناء القالب لكل تغيير.
         */
        Schema::create('service_availability', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('service_providers')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();
        });

        Schema::create('service_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('service_providers')->cascadeOnDelete();
            $table->date('date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->boolean('is_available')->default(false);   // false = إجازة
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'date']);
        });

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            // الرقم متسلسل ليُقرأ ويُقال في الهاتف؛ والرابط العام يحمل
            // رمزاً عشوائياً، وإلا صار تصفّح حجوزات الناس عدّاً تصاعدياً.
            $table->string('token', 40)->unique();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('service_providers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('order_item_id')->nullable();

            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 32)->nullable();

            $table->date('date')->nullable();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('timezone', 64)->nullable();

            $table->enum('status', [
                'pending', 'confirmed', 'in_progress', 'delivered',
                'completed', 'cancelled', 'no_show',
            ])->default('pending')->index();

            $table->string('currency', 3);
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->json('intake')->nullable();                 // إجابات العميل
            $table->json('deliverables')->nullable();
            $table->string('meeting_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['service_id', 'date']);
            $table->index(['provider_id', 'date']);
        });
    }

    public function down(): void
    {
        foreach ([
            'bookings', 'service_exceptions', 'service_availability',
            'service_providers', 'services',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
