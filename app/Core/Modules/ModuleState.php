<?php

declare(strict_types=1);

namespace App\Core\Modules;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حالة الموديولات للمشترك الحالي، مقروءة مرة واحدة في الطلب.
 *
 * سؤال القاعدة عند كل رابط في الهيدر يعني عشرات الاستعلامات
 * في صفحة واحدة، والأداء أول ما يُهدر في أماكن كهذه.
 */
final class ModuleState
{
    /** @var array<string, bool>|null */
    private ?array $enabled = null;

    public function enabled(string $module): bool
    {
        if (tenant() === null) {
            return true;   // السياق المركزي يرى كل شيء
        }

        if ($this->enabled === null) {
            $this->enabled = Schema::hasTable('modules')
                ? DB::table('modules')->where('enabled', true)->pluck('key')
                    ->mapWithKeys(fn (string $key): array => [$key => true])->all()
                : [];
        }

        return $this->enabled[$module] ?? false;
    }
}
