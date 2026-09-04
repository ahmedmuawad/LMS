<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ما تحتاجه مادة كالرياضيات، وما يحتاجه مدرّس يُدرّس في أكثر من مكان.
 *
 * السؤال في الرياضيات ليس نصّاً وأربعة خيارات: له **خطوات حل** هي
 * الدرس نفسه — الطالب الذي يرى الإجابة وحدها لا يتعلّم، والذي يرى
 * الخطوات يعرف أين أخطأ.
 *
 * والمجموعة ليست دائماً في فرع: نفس المدرّس يُعطي أونلاين، وفي بيته،
 * وفي سنترين لا يملكهما. فالفرع صار اختيارياً، ومكان المجموعة وشكلها
 * عمودين صريحين بدل أن يُستنتَجا من غياب البيانات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            // سطر لكل خطوة — والترتيب هو ترتيب الحل
            $table->json('steps')->nullable()->after('explanation');
        });

        Schema::table('center_groups', function (Blueprint $table): void {
            $table->string('venue', 16)->default('branch')->after('branch_id')->index();
            $table->string('kind', 16)->default('group')->after('venue')->index();
            $table->string('meeting_url')->nullable()->after('kind');
            $table->string('location')->nullable()->after('meeting_url');
        });

        /*
         | الفرع كان إلزامياً، والمجموعة الأونلاين لا فرع لها.
         | SQLite لا يُعدّل عموداً في مكانه، فنبني الجدول من جديد —
         | ولهذا نُمرّر التحويل عبر Schema لا عبر SQL خام.
         */
        Schema::table('center_groups', function (Blueprint $table): void {
            $table->unsignedBigInteger('branch_id')->nullable()->change();
        });

        // ما كان قائماً قبل هذه الهجرة مجموعات فرع بحكم الأمر الواقع
        DB::table('center_groups')->whereNull('venue')->update(['venue' => 'branch', 'kind' => 'group']);
    }

    public function down(): void
    {
        Schema::table('questions', fn (Blueprint $table) => $table->dropColumn('steps'));

        Schema::table('center_groups', function (Blueprint $table): void {
            $table->dropColumn(['venue', 'kind', 'meeting_url', 'location']);
        });
    }
};
