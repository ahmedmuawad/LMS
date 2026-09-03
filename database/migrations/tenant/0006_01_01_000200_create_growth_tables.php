<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وثيقة 05 §14 — النمو: التسويق بالعمولة والتسلسلات التسويقية.
 *
 * النقرة تُسجَّل والتحويل يُنسب: مسوّق لا يرى أثره لا يسوّق، ومنصّة
 * لا تعرف من جاء بمن تدفع عمولات على بيع كانت ستبيعه بلا أحد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->enum('status', ['pending', 'active', 'suspended'])->default('pending')->index();
            $table->decimal('commission_rate', 5, 2)->nullable();   // فارغ = النسبة العامة
            $table->enum('commission_type', ['percent', 'fixed'])->default('percent');
            $table->unsignedBigInteger('fixed_minor')->default(0);
            $table->string('payout_method', 24)->nullable();
            $table->json('payout_details')->nullable();
            $table->unsignedInteger('clicks_count')->default(0);
            $table->unsignedInteger('conversions_count')->default(0);
            $table->unsignedBigInteger('earned_minor')->default(0);
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        /*
         | النقرة تُسجَّل مجرّدة من الهوية: لا نحتاج من ضغط بل كم ضُغط
         | ومن أين، والاحتفاظ بأكثر من ذلك التزام خصوصية بلا مقابل.
         */
        Schema::create('affiliate_clicks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->string('landing_url', 500)->nullable();
            $table->string('referer', 500)->nullable();
            $table->string('ip_hash', 64)->nullable();      // مُهشَّم لا خام
            $table->string('country', 2)->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['affiliate_id', 'created_at']);
        });

        Schema::create('affiliate_conversions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor');         // قيمة الطلب
            $table->unsignedBigInteger('commission_minor');     // ما استحقّه المسوّق
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending')->index();
            $table->string('reject_reason')->nullable();
            $table->timestamp('matured_at')->nullable();        // بعد انقضاء مهلة الاسترداد
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // طلب واحد لا يُحتسب مرتين لنفس المسوّق مهما تكرّر النداء
            $table->unique(['affiliate_id', 'order_id']);
        });

        /*
         | التسلسل التسويقي: خطوات مؤجَّلة تُرسل لمن دخله.
         |
         | الخطوة تحمل تأخيرها وقالبها وشرط توقّفها؛ ومن حقّق الهدف
         | يخرج فوراً — إرسال «أكمل شراءك» لمن اشترى يفقدك عميلاً.
         */
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 48)->unique();
            $table->json('name');
            $table->enum('trigger', [
                'cart_abandoned', 'course_idle', 'access_expiring', 'course_completed',
                'signup', 'booking_upcoming', 'manual',
            ])->index();
            $table->json('conditions')->nullable();
            $table->enum('status', ['draft', 'active', 'paused'])->default('draft')->index();
            $table->unsignedInteger('entered_count')->default(0);
            $table->unsignedInteger('converted_count')->default(0);
            $table->timestamps();
        });

        Schema::create('campaign_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedInteger('delay_minutes')->default(60);
            $table->string('event', 64);                        // حدث الإشعار الذي يُرسل
            $table->string('channel', 16)->nullable();          // فارغ = القنوات المفعّلة للحدث
            $table->json('payload')->nullable();                // متغيّرات إضافية
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['campaign_id', 'position']);
        });

        Schema::create('campaign_enrolments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_type', 64)->nullable();     // السلة أو التسجيل الذي أدخله
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedSmallInteger('step_index')->default(0);
            $table->enum('status', ['running', 'converted', 'completed', 'stopped'])->default('running')->index();
            $table->timestamp('next_step_at')->nullable()->index();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            // لا يدخل المستخدم نفس الحملة على نفس الموضوع مرتين
            $table->unique(['campaign_id', 'user_id', 'subject_type', 'subject_id'], 'campaign_subject_unique');
        });

        Schema::create('campaign_sends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrolment_id')->constrained('campaign_enrolments')->cascadeOnDelete();
            $table->foreignId('step_id')->constrained('campaign_steps')->cascadeOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['enrolment_id', 'step_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'campaign_sends', 'campaign_enrolments', 'campaign_steps', 'campaigns',
            'affiliate_conversions', 'affiliate_clicks', 'affiliates',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
