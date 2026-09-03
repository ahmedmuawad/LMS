<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ملكية بنوك الدروس والاختبارات والأسئلة والواجبات.
 *
 * الدرس ليس تابعاً لكورس واحد (يُعاد استعماله في عدّة كورسات عبر
 * `course_items`)، فلا يمكن حصر نطاقه بالكورس. وبغير عمود ملكية
 * صريح يرى كل مدرّس بنك أسئلة كل مدرّس آخر في نمط السوق — وهذا
 * ما كان يحدث فعلاً قبل هذه الهجرة.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = ['lessons', 'quizzes', 'questions', 'assignments'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'created_by')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('created_by')->nullable()->after('id')
                    ->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'created_by')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('created_by');
            });
        }
    }
};
