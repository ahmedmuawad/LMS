<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وثيقة 03 — المحتوى: الوسائط والصفحات والمدونة والقوائم والنماذج.
 *
 * الصفحة تُبنى بكتل لا بمحرّر نصّي حرّ (ADR-005): الكتلة تُترجَم
 * وتُعاد ترتيباً وتبقى متجاوبة، والنصّ الحرّ يكسر الثلاثة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('name');
            $table->string('mime', 128);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('alt')->nullable();                    // نص بديل مترجم — شرط وصولية لا رفاهية
            $table->json('conversions')->nullable();            // المقاسات المولَّدة
            $table->string('folder')->nullable()->index();
            $table->string('hash', 64)->nullable()->index();    // يمنع رفع الملف نفسه مرتين
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['mime', 'created_at']);
        });

        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 200)->unique();
            $table->json('title');
            $table->json('excerpt')->nullable();
            $table->json('blocks')->nullable();                 // بنية الصفحة كاملة
            $table->string('template', 48)->default('default');
            $table->enum('status', ['draft', 'published', 'scheduled'])->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_system')->default(false);       // صفحات إلزامية لا تُحذف
            $table->string('system_key', 32)->nullable()->unique();
            $table->json('seo')->nullable();
            $table->foreignId('cover_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('wp_post_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 200)->unique();
            $table->json('title');
            $table->json('excerpt')->nullable();
            $table->json('body')->nullable();
            $table->json('blocks')->nullable();
            $table->foreignId('cover_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('taxonomies')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'pending', 'published', 'scheduled'])->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('reading_minutes')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->boolean('allow_comments')->default(true);
            $table->boolean('featured')->default(false);
            $table->json('seo')->nullable();
            $table->unsignedBigInteger('wp_post_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('post_tag', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('taxonomy_id')->constrained('taxonomies')->cascadeOnDelete();

            $table->unique(['post_id', 'taxonomy_id']);
        });

        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->string('commentable_type', 64);
            $table->unsignedBigInteger('commentable_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->text('body');
            $table->enum('status', ['pending', 'approved', 'spam', 'trash'])->default('pending')->index();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['commentable_type', 'commentable_id', 'status']);
        });

        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 32)->unique();                // main · footer · mobile · account
            $table->json('name');
            $table->json('items')->nullable();                  // شجرة الروابط
            $table->timestamps();
        });

        Schema::create('forms', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 48)->unique();
            $table->json('name');
            $table->json('fields');
            $table->json('success_message')->nullable();
            $table->string('notify_email')->nullable();
            $table->boolean('store_submissions')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->json('data');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->enum('status', ['new', 'read', 'archived', 'spam'])->default('new')->index();
            $table->timestamps();
        });

        /*
         | تحويلات الروابط — شرط ترحيل: كل رابط من الموقع القديم
         | يجب أن يصل إلى مقابله، وإلا ضاع ترتيب سنوات في جوجل.
         */
        Schema::create('redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('from', 500);
            $table->string('to', 500);
            $table->unsignedSmallInteger('code')->default(301);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();

            $table->index('from');
        });
    }

    public function down(): void
    {
        foreach ([
            'redirects', 'form_submissions', 'forms', 'menus', 'comments',
            'post_tag', 'posts', 'pages', 'media',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
