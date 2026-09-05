<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| النزاعات — حين يشكو المشتري لبنكه لا لنا.
|
| ## لماذا ليست استرداداً
|
| الاسترداد قرارُ المشترك: هو يضغط ويُعيد المال. والنزاع قرارُ البنك:
| المال يُسحب فوراً ويُطلب منه دليلٌ خلال مدّة — وإن لم يردّ خسِر
| المبلغ ورسمَ النزاع فوقه.
|
| ## ومهلةٌ تفوت بلا أن يعلم
|
| البوابة تُخطرنا وتُمهل أياماً معدودة. وبلا جدولٍ وشاشة، يصل
| الإخطار إلى بريدٍ لا يقرؤه أحد، وتفوت المهلة، ويخسر المشترك مالاً
| كان يملك دليلَه: سجلُّ دخول الطالب ومشاهداتُه عندنا كلّها.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->string('gateway', 32);

            /*
             | مرجع البوابة فريد.
             |
             | البوابات تُعيد إرسال الإخطار حتى نُجيب بنجاح؛ وبلا قيد
             | فريد يصير النزاع الواحد خمسة صفوفٍ في الشاشة.
             */
            $table->string('gateway_ref', 191)->unique();

            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_minor')->default(0);

            $table->string('reason')->nullable();

            $table->enum('status', ['open', 'under_review', 'won', 'lost', 'refunded'])
                ->default('open')->index();

            /*
             | المهلة عمودٌ مفهرَس لا حقلٌ في JSON.
             |
             | الشاشة تُرتّب بها وتُنذر عند اقترابها؛ وهو السبب الوحيد
             | لوجود هذه الشاشة أصلاً.
             */
            $table->timestamp('due_by')->nullable()->index();

            $table->text('evidence')->nullable();
            $table->json('raw')->nullable();

            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
