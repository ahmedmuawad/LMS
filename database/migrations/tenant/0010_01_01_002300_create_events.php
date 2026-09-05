<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | تقويم الفعاليات.
 |
 | الحصة موعدٌ لمجموعة، والدرس محتوًى يُفتح متى شاء الطالب — وبينهما
 | ما ليس واحداً منهما: ندوةٌ مفتوحة، ورشة، يوم امتحان، لقاء أولياء
 | أمور، إجازة. وهذه لا مكان لها اليوم، فتُكتب في منشورٍ يضيع.
 |
 | ## والتسجيل اختياري
 |
 | «إجازة العيد» فعاليةٌ تُعلَن ولا يُسجَّل فيها؛ و«ورشة المراجعة»
 | لها عشرون مقعداً. فالسعة تفتح التسجيل، وغيابها يجعلها إعلاناً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();

            $table->json('title');
            $table->json('description')->nullable();

            // workshop | webinar | exam | meeting | holiday | other
            $table->string('kind', 24)->default('workshop');

            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable();

            $table->string('location')->nullable();
            $table->string('url')->nullable();
            $table->string('cover_path')->nullable();

            /*
             | السعة صفرٌ تعني إعلاناً بلا تسجيل.
             |
             | ولو جُعل التسجيل مفتاحاً مستقلّاً لأمكن فتحُه بلا سعة
             | ثم امتلاء القاعة — والسعة هي القيد الحقيقي.
             */
            $table->unsignedInteger('capacity')->default(0);
            $table->unsignedInteger('registered_count')->default(0);

            $table->boolean('is_published')->default(true);
            $table->boolean('is_public')->default(true);

            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();

            $table->timestamps();

            $table->index(['is_published', 'starts_at']);
        });

        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // registered | attended | cancelled
            $table->string('status', 16)->default('registered');

            $table->timestamp('registered_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('events');
    }
};
