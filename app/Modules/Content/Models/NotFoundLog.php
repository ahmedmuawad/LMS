<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** رابطٌ مكسور طُلب فلم يوجد. */
final class NotFoundLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'is_resolved' => 'boolean',
        ];
    }

    /**
     * يُسجّل الطلب — أو يرفع عدّاده.
     *
     * ## ولا يُسقط الصفحة أبداً
     *
     * هذا يقع أثناء معالجة خطأ ٤٠٤؛ وخطأٌ هنا يتحوّل إلى ٥٠٠ —
     * فيرى الزائر «خطأ في الخادم» بدل «الصفحة غير موجودة»، ويقرأ
     * جوجل الرمز الخطأ. فكلّ ما هنا محاطٌ بالحماية.
     */
    public static function record(string $path, ?string $referrer = null): void
    {
        try {
            if (! Schema::hasTable('not_found_logs')) {
                return;
            }

            $path = mb_substr(trim($path), 0, 2000);

            if ($path === '' || self::ignorable($path)) {
                return;
            }

            $hash = sha1($path);

            $updated = DB::table('not_found_logs')->where('path_hash', $hash)->update([
                'hits' => DB::raw('hits + 1'),
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]);

            if ($updated === 0) {
                DB::table('not_found_logs')->insert([
                    'path' => $path,
                    'path_hash' => $hash,
                    'referrer' => $referrer === null ? null : mb_substr($referrer, 0, 2000),
                    'hits' => 1,
                    'last_seen_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (Throwable) {
            // سجلٌّ لا يستحقّ أن يُسقط صفحةً
        }
    }

    /**
     * ما لا يُسجَّل: ضجيجُ الفاحصين الآلي.
     *
     * كل موقعٍ على الإنترنت يُطلَب منه `/wp-login.php` و`/.env` مئات
     * المرّات يومياً. وتسجيلها يُغرق القائمة بما ليس رابطاً مكسوراً
     * عندنا — فلا تُقرأ القائمة أصلاً.
     */
    private static function ignorable(string $path): bool
    {
        foreach (['wp-', '.php', '.env', 'phpmyadmin', '.git', 'xmlrpc', 'vendor/', '.aspx', 'cgi-bin'] as $noise) {
            if (str_contains(mb_strtolower($path), $noise)) {
                return true;
            }
        }

        return (bool) preg_match('#\.(png|jpe?g|gif|webp|svg|ico|css|js|map|woff2?)$#i', $path);
    }
}
