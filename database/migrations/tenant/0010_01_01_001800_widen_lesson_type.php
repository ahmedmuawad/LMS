<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | نوع الدرس نصٌّ لا قائمةً محصورة في القاعدة.
 |
 | كان `enum('video','audio','text','pdf','slides','live','scorm','embed')`،
 | وأُضيف النوع `h5p` إلى `Lesson::TYPES` في الكود — فقبِلَته SQLite
 | (لا enum فيها) ورفضته MySQL بصمت: «Data truncated for column type».
 |
 | ## ولماذا نصّ لا enum موسَّع
 |
 | القائمة موجودة في `Lesson::TYPES` وهي المرجع: الشاشات تقرأ منها،
 | والتصفية تقرأ منها، والتحقّق من المُدخَل يقرأ منها. ونسخُها في
 | القاعدة يعني مرجعين يفترقان — وقد افترقا فعلاً. ووسمُ النوع
 | بحرفٍ واحد زائد لا يحمي شيئاً لا يحميه التحقّق قبله.
 |
 | وهو خللٌ لا يظهر في التطوير أبداً: قاعدة التطوير SQLite وقاعدة
 | الإنتاج MySQL، فالنوع الجديد يعمل هنا ويسقط هناك.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite تُخزّن enum نصّاً أصلاً، فلا شيء يُغيَّر فيها
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `lessons` MODIFY `type` VARCHAR(24) NOT NULL DEFAULT 'video'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `lessons` MODIFY `type` ENUM('video','audio','text','pdf','slides','live','scorm','embed') NOT NULL DEFAULT 'video'");
    }
};
