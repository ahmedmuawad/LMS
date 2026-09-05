<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | الدرس من نوع «حصة مباشرة» كان طريقاً مسدوداً.
 |
 | يختاره المدرّس فلا يجد حقلاً لرابط الاجتماع ولا لموعده، فيبقى
 | الدرس بلا ما يجعله حصة. ورابط المجموعة (center_groups.meeting_url)
 | يخدم المجموعة المتكرّرة؛ أما الحصة داخل منهج كورس فتحتاج رابطها
 | وموعدها هنا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->string('live_url')->nullable()->after('video_id');
            $table->timestamp('live_starts_at')->nullable()->after('live_url');
            $table->unsignedSmallInteger('live_minutes')->nullable()->after('live_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn(['live_url', 'live_starts_at', 'live_minutes']);
        });
    }
};
