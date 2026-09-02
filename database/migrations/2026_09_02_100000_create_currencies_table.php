<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ADR-014 — العملات جزء من النواة، لا إضافة لاحقة.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->char('code', 3)->primary();               // ISO 4217
            $table->json('name');                             // {"ar": "جنيه مصري", "en": "Egyptian Pound"}
            $table->json('symbol');
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->enum('position', ['before', 'after'])->default('after');
            $table->string('thousands_separator', 4)->default(',');
            $table->string('decimal_separator', 4)->default('.');
            $table->decimal('rate_to_base', 20, 10)->default(1);
            $table->timestamp('rate_updated_at')->nullable();
            $table->enum('rate_source', ['manual', 'api'])->default('manual');
            $table->string('rounding_rule', 20)->default('none'); // none | .99 | .00 | nearest_9
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'position_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
