<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عمولات المدرّسين وتحويلاتهم.
 *
 * العمولة تُقيَّد عند البيع لا عند التحويل: المدرّس يجب أن يرى ما
 * استحقّه لحظة البيع، لا أن ينتظر آخر الشهر ليعرف.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_earnings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instructor_id')->constrained('instructors')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('payout_id')->nullable();
            $table->string('currency', 3);
            $table->bigInteger('amount_minor');                 // سالب عند الاسترداد
            $table->decimal('rate', 5, 2)->default(0);
            $table->enum('status', ['pending', 'available', 'paid', 'reversed'])->default('pending')->index();
            $table->timestamp('available_at')->nullable()->index();  // بعد انقضاء مهلة الاسترداد
            $table->timestamps();

            $table->index(['instructor_id', 'status']);
        });

        Schema::create('payouts', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('instructor_id')->constrained('instructors')->cascadeOnDelete();
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->enum('status', ['pending', 'processing', 'paid', 'failed'])->default('pending')->index();
            $table->string('method', 32)->nullable();           // bank · vodafone_cash · instapay
            $table->json('destination')->nullable();
            $table->string('transaction_ref')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('instructor_earnings');
    }
};
