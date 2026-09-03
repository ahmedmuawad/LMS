<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * تحويل رابط قديم إلى جديد.
 *
 * شرط ترحيل لا رفاهية: رابط لا يصل إلى مقابله يُضيّع ترتيب سنوات
 * في نتائج البحث، ويُقابل الزائر بصفحة خطأ لا يفهم سببها.
 */
final class Redirect extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_hit_at' => 'datetime'];
    }

    public static function match(string $path): ?self
    {
        return self::where('from', '/'.ltrim($path, '/'))->first()
            ?? self::where('from', ltrim($path, '/'))->first();
    }

    public function hit(): void
    {
        $this->forceFill(['hits' => $this->hits + 1, 'last_hit_at' => now()])->saveQuietly();
    }
}
