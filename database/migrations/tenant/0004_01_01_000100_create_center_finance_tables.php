<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وثيقة 16.6 — مالية السنتر.
 *
 * أوجع سؤالين يومياً: «مين اللي عليه فلوس؟» و«الفلوس اللي في الدرج
 * مش مظبوطة». هذان الجدولان — المتأخرات والخزنة — هما الجواب.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('center_fee_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->nullable()->constrained('center_groups')->cascadeOnDelete();
            $table->json('name');
            $table->enum('type', ['monthly', 'per_session', 'full_term'])->default('monthly');
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedTinyInteger('installments')->default(1);
            $table->unsignedTinyInteger('due_day')->default(1);        // اليوم من الشهر
            $table->unsignedSmallInteger('grace_days')->default(5);
            $table->decimal('late_fee_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('center_cashboxes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('center_branches')->cascadeOnDelete();
            $table->json('name');
            $table->string('currency', 3);
            $table->bigInteger('opening_minor')->default(0);
            $table->bigInteger('balance_minor')->default(0);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('center_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('student_id')->constrained('center_students')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('center_groups')->nullOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('center_enrollments')->nullOnDelete();
            $table->string('period', 16)->nullable();                   // 2026-09
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('late_fee_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->date('due_on')->index();
            $table->enum('status', ['draft', 'unpaid', 'partial', 'paid', 'void'])->default('unpaid')->index();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->unique(['enrollment_id', 'period']);
        });

        Schema::create('center_payments', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt_no', 32)->unique();                 // إيصال مرقّم إلزامي
            $table->foreignId('invoice_id')->nullable()->constrained('center_invoices')->nullOnDelete();
            $table->foreignId('student_id')->constrained('center_students')->cascadeOnDelete();
            $table->foreignId('cashbox_id')->nullable()->constrained('center_cashboxes')->nullOnDelete();
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->enum('method', ['cash', 'card', 'wallet', 'transfer', 'online'])->default('cash');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['student_id', 'paid_at']);
        });

        Schema::create('center_cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cashbox_id')->constrained('center_cashboxes')->cascadeOnDelete();
            $table->enum('type', ['in', 'out', 'transfer'])->index();
            $table->string('currency', 3);
            $table->bigInteger('amount_minor');
            $table->bigInteger('balance_after_minor');
            $table->string('category', 48)->nullable();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('cashbox_to_id')->nullable()->constrained('center_cashboxes')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cashbox_id', 'created_at']);
        });

        /*
         | تقفيل الخزنة اليومي: نُقارن ما تقوله السجلات بما عُدّ فعلاً،
         | والفرق يُسجَّل ويُبرَّر ولا يُطمس. هذا ما يوقف تسرّب النقد.
         */
        Schema::create('center_cash_closings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cashbox_id')->constrained('center_cashboxes')->cascadeOnDelete();
            $table->date('closed_on');
            $table->bigInteger('expected_minor');
            $table->bigInteger('counted_minor');
            $table->bigInteger('difference_minor');
            $table->text('explanation')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cashbox_id', 'closed_on']);
        });

        Schema::create('center_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('center_branches')->nullOnDelete();
            $table->foreignId('cashbox_id')->nullable()->constrained('center_cashboxes')->nullOnDelete();
            $table->string('category', 48);
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->date('spent_on');
            $table->string('attachment_path')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'spent_on']);
        });

        Schema::create('center_salaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['fixed', 'per_session', 'percentage'])->default('fixed');
            $table->string('period', 16);                               // 2026-09
            $table->string('currency', 3);
            $table->unsignedBigInteger('base_minor')->default(0);
            $table->unsignedBigInteger('earned_minor')->default(0);
            $table->unsignedBigInteger('deductions_minor')->default(0);
            $table->unsignedBigInteger('net_minor')->default(0);
            $table->unsignedSmallInteger('sessions_count')->default(0);
            $table->decimal('rate', 8, 2)->default(0);
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft')->index();
            $table->text('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period']);
        });

        Schema::create('center_discounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('center_students')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('center_groups')->nullOnDelete();
            $table->enum('type', ['sibling', 'excellence', 'hardship', 'promo', 'staff'])->default('promo');
            $table->enum('value_type', ['percent', 'fixed'])->default('percent');
            $table->decimal('value', 10, 2);
            $table->string('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // درجات السنتر — الامتحانات الورقية والشفوية إلى جانب الأونلاين
        Schema::create('center_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('center_groups')->cascadeOnDelete();
            $table->json('name');
            $table->enum('type', ['exam', 'quiz', 'homework', 'oral', 'behaviour'])->default('quiz');
            $table->decimal('max_marks', 6, 2)->default(100);
            $table->decimal('weight', 5, 2)->default(1);
            $table->date('held_on')->nullable();
            $table->timestamps();
        });

        Schema::create('center_marks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained('center_assessments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('center_students')->cascadeOnDelete();
            $table->decimal('marks', 6, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['assessment_id', 'student_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'center_marks', 'center_assessments', 'center_discounts', 'center_salaries',
            'center_expenses', 'center_cash_closings', 'center_cash_movements',
            'center_payments', 'center_invoices', 'center_cashboxes', 'center_fee_plans',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
