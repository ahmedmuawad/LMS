<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ADR-014 — كل قاعدة تختلف بالدولة تُخزَّن هنا، لا في الكود.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->char('code', 2)->primary();               // ISO 3166-1 alpha-2
            $table->json('name');
            $table->string('dial_code', 8);
            $table->char('currency', 3);
            $table->string('locale_default', 5)->default('ar');
            $table->string('timezone_default', 64);

            // الضرائب
            $table->boolean('tax_enabled')->default(false);
            $table->decimal('tax_rate', 6, 3)->default(0);     // 14.000
            $table->json('tax_name')->nullable();              // {"ar": "ضريبة القيمة المضافة"}
            $table->string('tax_id_label', 64)->nullable();
            $table->boolean('tax_inclusive_display')->default(true);

            // البوابات والفوترة الإلكترونية
            $table->json('gateways')->nullable();              // ["paymob","fawry","stripe"]
            $table->string('e_invoice_provider', 32)->nullable(); // eta | zatca | null

            // الأقلمة
            $table->string('phone_pattern', 128)->nullable();
            $table->string('address_format', 64)->default('default');
            $table->unsignedTinyInteger('week_start')->default(6); // 6 = السبت
            $table->enum('numerals', ['arabic', 'hindi'])->default('arabic');
            $table->enum('calendar', ['gregorian', 'hijri', 'both'])->default('gregorian');

            $table->unsignedSmallInteger('consumer_refund_days')->default(14);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'position_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
