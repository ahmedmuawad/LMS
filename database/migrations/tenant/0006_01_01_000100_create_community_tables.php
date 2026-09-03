<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وثيقة 05 §13 — المجتمع والتقييمات والتحفيز.
 *
 * الطالب الذي يجد من يسأله يكمل الكورس، والذي لا يجد يتوقّف عند
 * أول عائق. النقاش ليس رفاهية اجتماعية بل جزء من معدّل الإتمام.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         | النقاش عام في الكورس، والسؤال موجَّه إلى المدرّس.
         | الاثنان في جدول واحد بنوع مختلف: البنية واحدة، وفصلهما
         | جدولين يضاعف كل استعلام لاحق بلا فارق حقيقي.
         */
        Schema::create('discussions', function (Blueprint $table): void {
            $table->id();
            $table->enum('type', ['question', 'discussion', 'announcement'])->default('question')->index();
            $table->foreignId('course_id')->nullable()->constrained('courses')->cascadeOnDelete();
            $table->unsignedBigInteger('item_id')->nullable();      // الدرس الذي سُئل عنده
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->enum('status', ['open', 'answered', 'closed', 'hidden'])->default('open')->index();
            $table->boolean('is_pinned')->default(false);
            $table->unsignedInteger('replies_count')->default(0);
            $table->unsignedInteger('votes_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'status', 'last_reply_at']);
        });

        Schema::create('discussion_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discussion_id')->constrained('discussions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_answer')->default(false);           // الردّ الذي حلّ السؤال
            $table->boolean('is_instructor')->default(false);
            $table->unsignedInteger('votes_count')->default(0);
            $table->enum('status', ['visible', 'hidden'])->default('visible');
            $table->timestamps();
            $table->softDeletes();
        });

        /* صوت واحد لكل شخص على كل عنصر — القيد الفريد هو ما يمنع التلاعب. */
        Schema::create('discussion_votes', function (Blueprint $table): void {
            $table->id();
            $table->string('votable_type', 64);
            $table->unsignedBigInteger('votable_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('value')->default(1);
            $table->timestamps();

            $table->unique(['votable_type', 'votable_id', 'user_id'], 'votes_unique');
        });

        /* تقييم الخدمات — الكورسات لها جدولها من المرحلة ٣. */
        Schema::create('service_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'user_id']);
        });

        /*
         | التحفيز: نقاط وشارات ومستويات.
         |
         | النقطة تُقيَّد بسببها ومصدرها لا مجموعاً وحده: مجموع بلا
         | تفصيل لا يُراجَع ولا يُصحَّح حين يُخطئ قاعدة.
         */
        Schema::create('point_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('rule', 48)->index();
            $table->integer('points');
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('badges', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 48)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('icon', 8)->default('★');
            $table->string('tone', 16)->default('primary');
            $table->string('condition_rule', 48)->nullable();       // القاعدة التي تمنحها
            $table->unsignedInteger('condition_value')->default(0);
            $table->unsignedInteger('points')->default(0);          // نقاط تُمنح معها
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('badge_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('badge_id')->constrained('badges')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('awarded_at');
            $table->timestamps();

            $table->unique(['badge_id', 'user_id']);
        });

        /*
         | التتابع اليومي يُخزَّن محسوباً لا مُستنتَجاً عند كل عرض:
         | استنتاجه يعني قراءة كل نشاط المستخدم في كل صفحة.
         */
        Schema::create('learning_streaks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->unique();
            $table->unsignedInteger('current_days')->default(0);
            $table->unsignedInteger('longest_days')->default(0);
            $table->unsignedInteger('total_points')->default(0);
            $table->unsignedSmallInteger('level')->default(1);
            $table->date('last_active_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'learning_streaks', 'badge_user', 'badges', 'point_entries',
            'service_reviews', 'discussion_votes', 'discussion_replies', 'discussions',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
