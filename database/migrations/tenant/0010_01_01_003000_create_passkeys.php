<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| مفاتيح المرور.
|
| الإعداد مُشغَّلٌ افتراضياً في شاشة المستخدمين منذ البداية ولا جدولَ
| وراءه ولا زرَّ في شاشة الدخول — فيظنّ المشترك أن منصّته تدعمها.
|
| والمفتاح الخاصّ لا يصلنا أبداً: يبقى في الجهاز، ولا نحفظ إلا
| العامّ وعدّاد التوقيع. فتسريبُ قاعدتنا لا يُدخل أحداً.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passkeys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // اسمٌ يكتبه صاحبه: «آيفوني»، «لابتوب الشغل» — ليعرف أيّها يحذف
            $table->string('label', 100);

            /*
             | المعرّف مُرمَّزٌ بـBase64 لا خاماً.
             |
             | هو بايتاتٌ ثنائية فيها صفرٌ ومحارف تحكّم، وحفظُها في
             | عمودٍ نصّي يقطعها عند أوّل صفر — فلا يُطابَق المفتاح
             | أبداً، والخطأ يظهر عند المستخدم لا عندنا.
             */
            $table->string('credential_id', 512)->unique();

            // الوصف الكامل كما تُخرجه المكتبة — مفتاحٌ عام ومسار ثقة
            $table->text('credential');

            /*
             | عدّاد التوقيع.
             |
             | جهازٌ حقيقي يزيده في كل دخول، ونسخةٌ مستنسخة منه تعيد
             | رقماً رأيناه — فيُرفَض. وإهمالُ حفظه يُبطل الحماية بلا
             | أن يظهر شيء.
             */
            $table->unsignedBigInteger('sign_count')->default(0);

            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            /*
             | معرّفٌ ثابت لا يحمل معنى.
             |
             | لو كان البريد لتغيّر بتغيّره فضاعت مفاتيحه؛ ولو كان
             | رقم الصفّ لأخبر من يقرأ الجهاز كم مستخدماً عندنا.
             */
            $table->uuid('passkey_handle')->nullable()->unique()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('passkey_handle'));
        Schema::dropIfExists('passkeys');
    }
};
