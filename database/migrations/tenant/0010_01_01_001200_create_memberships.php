<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | عضويات الطلبة: اشتراكٌ شهري يفتح محتوى بدل شراء كل كورس.
 |
 | `subscriptions` مفتاحٌ في الباقات، والموجود اشتراكُ المشترك في
 | منصّتنا لا اشتراكُ الطالب عند مدرّسه — وهما شيئان.
 |
 | ومدرّس المجموعات يبيع بالشهر لا بالكورس: «مئتان شهرياً تفتح كل
 | كورساتي». وبلا هذا يضطرّ إلى إنشاء كورسٍ وهميّ كل شهر.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->json('name');
            $table->json('description')->nullable();

            $table->string('currency', 3)->default('EGP');
            $table->unsignedBigInteger('price_minor')->default(0);

            // month | quarter | year
            $table->string('period', 12)->default('month');

            /*
             | ما تفتحه العضوية.
             |
             | `all` تفتح كل الكورسات المنشورة — وهي ما يريده أكثر
             | المدرّسين. و`selected` تفتح ما يُختار، لمن يبيع مسارين.
             */
            $table->string('scope', 12)->default('all');
            $table->json('course_ids')->nullable();

            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('membership_plans')->cascadeOnDelete();

            // trialing | active | past_due | cancelled | expired
            $table->string('status', 16)->default('active')->index();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            /*
             | الإلغاء لا يقطع فوراً.
             |
             | من ألغى في اليوم الثاني من شهرٍ دفعه يبقى إلى آخره:
             | القطعُ الفوري سرقةٌ لما دُفع، ويُحوّل إلغاءً هادئاً إلى
             | شكوى.
             */
            $table->timestamp('cancelled_at')->nullable();

            $table->unsignedInteger('renewals')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('membership_plans');
    }
};
