<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وثيقة 05 §10 — الإشعارات.
 *
 * كتالوج الأحداث مغلق في config، والقابل للتحرير هنا: القالب لكل
 * حدث·قناة، وتفضيل كل مستخدم، وسجلّ ما أُرسل فعلاً.
 *
 * السجلّ ليس ترفاً: «ما وصلني إشعار» أكثر شكوى في أنظمة السناتر،
 * وبغير سجلّ لا يُحسم النقاش.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 64)->index();
            $table->string('channel', 16);
            $table->json('subject')->nullable();          // مترجَم
            $table->json('body')->nullable();             // مترجَم
            $table->string('provider_template')->nullable();  // قالب واتساب المعتمد من ميتا
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['event', 'channel']);
        });

        /*
         | تفضيل المستخدم يُخزَّن عند المخالفة فقط: صفّ لكل مستخدم ×
         | حدث × قناة يعني ملايين الصفوف بلا فائدة. الغياب = الافتراضي.
         */
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event', 64);
            $table->string('channel', 16);
            $table->boolean('is_enabled');
            $table->timestamps();

            $table->unique(['user_id', 'event', 'channel']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event', 64)->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('url')->nullable();
            $table->string('icon', 8)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });

        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 64)->index();
            $table->string('channel', 16)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('destination')->nullable();     // بريد أو رقم — للمراجعة
            $table->enum('status', ['queued', 'sent', 'failed', 'skipped'])->default('queued')->index();
            $table->string('reason')->nullable();          // سبب التخطّي أو الفشل
            $table->string('provider_id')->nullable();     // معرّف الرسالة عند المزوّد
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'status']);
        });

        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('endpoint', 500);
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique('endpoint');
        });
    }

    public function down(): void
    {
        foreach ([
            'push_subscriptions', 'notification_logs', 'notifications',
            'notification_preferences', 'notification_templates',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
