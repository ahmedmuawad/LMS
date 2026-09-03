<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وثيقة 16 — إدارة السنتر: البنية التنظيمية والمجموعات والجداول.
 *
 * السنتر ليس موقعاً: هو فروع وقاعات ومواعيد وطلاب حاضرون وأقساط.
 * وأصعب ما فيه أن كل حصة قد تتعارض مع أخرى في قاعة أو مدرّس أو وقت.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('center_branches', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->string('code', 16)->nullable()->unique();
            $table->text('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('whatsapp', 32)->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('timezone', 64)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('center_rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('center_branches')->cascadeOnDelete();
            $table->json('name');
            $table->unsignedSmallInteger('capacity')->default(30);
            $table->json('equipment')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('center_stages', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('center_grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stage_id')->constrained('center_stages')->cascadeOnDelete();
            $table->json('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('center_subjects', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->foreignId('stage_id')->nullable()->constrained('center_stages')->nullOnDelete();
            $table->string('color', 9)->nullable();
            $table->string('icon', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('center_terms', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false)->index();
            $table->timestamps();
        });

        /*
         | العطلات الرسمية: يُفحص عليها كل موعد قبل الحفظ، فلا
         | تُجدوَل حصة في يوم لن يحضره أحد.
         */
        Schema::create('center_holidays', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->foreignId('branch_id')->nullable()->constrained('center_branches')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
        });

        Schema::create('center_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('center_branches')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('center_subjects')->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('center_grades')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('center_terms')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();

            $table->json('name');
            $table->string('code', 24)->nullable()->unique();
            $table->unsignedSmallInteger('capacity')->default(25);
            $table->unsignedSmallInteger('enrolled_count')->default(0);

            $table->string('currency', 3);
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->enum('price_type', ['monthly', 'per_session', 'full_term'])->default('monthly');
            $table->unsignedSmallInteger('sessions_count')->default(0);

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['draft', 'open', 'running', 'finished', 'cancelled'])->default('open')->index();
            $table->string('color', 9)->nullable();
            $table->text('notes')->nullable();

            // يربط المجموعة الحضورية بكورس مسجّل: الغائب يراجع ما فاته
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('center_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('center_groups')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('center_rooms')->nullOnDelete();
            $table->unsignedTinyInteger('weekday');            // 0=الأحد … 6=السبت
            $table->time('starts_at');
            $table->time('ends_at');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['weekday', 'starts_at']);
        });

        Schema::create('center_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('center_groups')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('center_rooms')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('center_schedules')->nullOnDelete();

            $table->date('date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->enum('status', ['scheduled', 'running', 'done', 'cancelled', 'postponed'])
                ->default('scheduled')->index();
            $table->string('topic')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('attendance_taken_at')->nullable();
            $table->string('meeting_url')->nullable();
            $table->foreignId('makeup_for_id')->nullable();     // حصة تعويضية عن أخرى
            $table->timestamps();

            $table->index(['date', 'starts_at']);
            $table->index(['group_id', 'date']);
        });

        Schema::create('center_students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('code', 24)->unique();               // كود الكارنيه — يُمسح بالـ QR
            $table->foreignId('branch_id')->nullable()->constrained('center_branches')->nullOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('center_stages')->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('center_grades')->nullOnDelete();
            $table->string('school')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('national_id', 32)->nullable();
            $table->text('address')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('emergency_phone', 32)->nullable();
            $table->text('medical_notes')->nullable();
            $table->enum('source', ['walk_in', 'online', 'referral'])->default('walk_in');
            $table->foreignId('referred_by')->nullable()->constrained('center_students')->nullOnDelete();
            $table->date('joined_at')->nullable();
            $table->enum('status', ['active', 'paused', 'left'])->default('active')->index();
            $table->timestamps();
        });

        Schema::create('center_guardians', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('relation', 32)->nullable();
            $table->string('phone', 32);
            $table->string('whatsapp', 32)->nullable();
            $table->string('email')->nullable();
            $table->boolean('can_login')->default(false);
            $table->json('notification_prefs')->nullable();
            $table->timestamps();

            $table->index('phone');
        });

        Schema::create('guardian_student', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guardian_id')->constrained('center_guardians')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('center_students')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['guardian_id', 'student_id']);
        });

        Schema::create('center_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('center_students')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('center_groups')->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('center_terms')->nullOnDelete();
            $table->string('currency', 3);
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->string('discount_reason')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->enum('status', ['active', 'paused', 'transferred', 'dropped'])->default('active')->index();
            $table->timestamps();

            $table->unique(['student_id', 'group_id']);
        });

        Schema::create('center_attendance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('center_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('center_students')->cascadeOnDelete();
            $table->enum('status', ['present', 'absent', 'late', 'excused', 'online'])->default('present')->index();
            $table->enum('method', ['manual', 'code', 'qr', 'fingerprint', 'nfc', 'self', 'meeting'])->default('manual');
            $table->unsignedSmallInteger('minutes_late')->default(0);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->string('device_id', 64)->nullable();
            $table->string('note')->nullable();
            $table->boolean('guardian_notified')->default(false);
            $table->timestamps();

            $table->unique(['session_id', 'student_id']);
        });

        Schema::create('center_teacher_attendance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('center_sessions')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['present', 'absent', 'late', 'substituted'])->default('present');
            $table->foreignId('substitute_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('minutes_late')->default(0);
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'center_teacher_attendance', 'center_attendance', 'center_enrollments',
            'guardian_student', 'center_guardians', 'center_students',
            'center_sessions', 'center_schedules', 'center_groups', 'center_holidays',
            'center_terms', 'center_subjects', 'center_grades', 'center_stages',
            'center_rooms', 'center_branches',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
