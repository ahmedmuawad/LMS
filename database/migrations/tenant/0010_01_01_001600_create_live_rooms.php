<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | غرف الحصص التي يُنشئها مزوّدٌ خارجي.
 |
 | Jitsi لا يحتاج هذا الجدول: اسم الغرفة يُحسب من معرّف الحصة فيخرج
 | هو نفسه في كل مرّة بلا حفظ. أمّا Zoom وBigBlueButton فيُنشئان
 | الاجتماع عندهما ويُعيدان رابطاً ومعرّفاً لا يمكن حسابهما — فيُحفظان.
 |
 | ## ولماذا لا يُحفظ في عمودٍ على الحصة
 |
 | `meeting_url` على الحصة رابطُ المدرّس اليدوي، وهو يسبق كل توليد.
 | ووضعُ الرابط المولَّد فيه يخلط ما ثبّته المدرّس بما أنشأناه نحن،
 | فلا يُعرف أيّهما إن غيّر المشترك مزوّده — ويبقى رابط Zoom قديم
 | يفتح اجتماعاً لا أحد فيه.
 |
 | والجدول يخدم الحصص والمجموعات معاً بمفتاحٍ نصّي (`session-12`)،
 | فلا يحتاج عمودين ولا جدولين.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_rooms', function (Blueprint $table): void {
            $table->id();

            // `session-12` أو `group-3` — ما يُشتقّ منه اسم الغرفة
            $table->string('seed', 64);
            $table->string('provider', 24);

            $table->text('join_url');

            // رابط المدرّس حيث يفترق عن رابط الطالب (Zoom)
            $table->text('host_url')->nullable();

            // معرّف الاجتماع عند المزوّد — للحذف والتسجيلات لاحقاً
            $table->string('external_id')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // غرفةٌ واحدة لكل بذرة عند كل مزوّد
            $table->unique(['seed', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_rooms');
    }
};
