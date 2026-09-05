<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | ختم التذكير على الحصة.
 |
 | أمر التذكير يعمل كل خمس دقائق والنافذة أوسع، فالحصة الواحدة تقع
 | في أكثر من دورة. وبلا ختمٍ يصل الطالب اثنا عشر تذكيراً بحصة
 | واحدة — والتذكير المتكرّر يُكتَم لا يُقرأ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('center_sessions', function (Blueprint $table): void {
            $table->timestamp('reminded_at')->nullable()->after('attendance_taken_at');
        });
    }

    public function down(): void
    {
        Schema::table('center_sessions', function (Blueprint $table): void {
            $table->dropColumn('reminded_at');
        });
    }
};
