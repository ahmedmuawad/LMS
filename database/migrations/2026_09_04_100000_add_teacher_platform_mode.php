<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نمط «مدرّس مستقل» (ADR-010).
 *
 * مدرّس يُدرّس أونلاين وفي بيته وفي سناتر لا يملكها كان يُجبَر على
 * نمط «شامل» فيرث لوحة صاحب سنتر: فروع وقاعات ومدرّسو سنتر وخزنة —
 * أدوات ليست له تُزاحم ما هو له. النمط الجديد يعطيه مجموعاته وحصصه
 * وحضوره وأقساطه وأولياء أموره، ولا شيء فوق ذلك.
 *
 * القيد على العمود قائمة مغلقة، فتوسيعها هجرة لا إعداد.
 */
return new class extends Migration
{
    private const MODES = ['solo', 'teacher', 'marketplace', 'center', 'hybrid'];

    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->enum('platform_mode', self::MODES)->default('solo')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->enum('platform_mode', array_values(array_diff(self::MODES, ['teacher'])))
                ->default('solo')->change();
        });
    }
};
