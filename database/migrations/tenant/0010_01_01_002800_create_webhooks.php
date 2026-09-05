<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| الـWebhooks الصادرة — المنصّة تُخبر غيرها بما جرى.
|
| الإعداد «تفعيل الـWebhooks» موجود منذ البداية ولا شيء وراءه: لا
| جدول ولا مُرسِل ولا شاشة. فيُفعّله المشترك ولا يصل شيئاً بشيء.
|
| والوجهةُ صفٌّ لا إعداد: من يربط منصّته بـZapier يربط عشرة
| «زابات»، ولكلٍّ رابطُه وأحداثُه.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('url', 2000);

            // الأحداث المشترَك فيها — من كتالوج الإشعارات نفسه
            $table->json('events');

            /*
             | السرّ يُولَّد لا يُطلب.
             |
             | المستقبِل يتحقّق بـHMAC أن الطلب منّا لا من غيرنا؛
             | ومن يُطلب منه اختراع سرٍّ يكتب `123456`.
             */
            $table->string('secret', 64);

            $table->boolean('is_active')->default(true);

            /*
             | حالةُ آخر تسليم — لتُقرأ من الشاشة بلا استعلامٍ ثقيل.
             |
             | ومن يفتح شاشة الوجهات يسأل سؤالاً واحداً: «هل تعمل؟»
             | وجوابُه لا يستحقّ ضمّ جدول التسليمات في كل مرّة.
             */
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->timestamp('last_delivered_at')->nullable();

            /*
             | إخفاقاتٌ متتالية تُوقف الوجهة.
             |
             | رابطٌ مات عند المشترك يُعيد كل حدثٍ محاولاتِه الثلاث
             | إلى الأبد، فيمتلئ الطابور بما لا يصل. والعدّاد يصفر
             | عند أول نجاح.
             */
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();

            /*
             | من أنشأها: Zapier يسجّل وجهته بنفسه عبر الواجهة،
             | والمشترك يكتبها بيده — وحذفُ اشتراك Zapier لا يمسّ
             | ما كتبه المشترك.
             */
            $table->string('source', 20)->default('manual');
            $table->unsignedBigInteger('token_id')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'disabled_at']);
            $table->index('token_id');
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $table->string('event', 64);

            $table->json('payload');

            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedTinyInteger('attempt')->default(1);

            /*
             | جوابُ المستقبِل يُقتطع.
             |
             | خادمٌ يردّ بصفحة خطأ HTML كاملة يملأ القاعدة بلا فائدة؛
             | وأوّلُ خمسمئة حرفٍ فيها الرسالة دائماً.
             */
            $table->text('response')->nullable();
            $table->text('error')->nullable();

            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['endpoint_id', 'created_at']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }
};
