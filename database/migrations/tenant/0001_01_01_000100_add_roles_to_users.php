<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أدوار داخل منصة المشترك. المالك واحد لا يُحذف ولا يُخفَّض،
 * وإلا أمكن أن تصبح المنصة بلا من يدخل لوحتها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['owner', 'admin', 'instructor', 'staff', 'student', 'guardian'])
                ->default('student')
                ->after('status')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
