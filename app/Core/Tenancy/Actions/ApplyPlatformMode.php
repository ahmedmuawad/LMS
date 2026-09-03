<?php

declare(strict_types=1);

namespace App\Core\Tenancy\Actions;

use App\Core\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ADR-010 — يترجم اختيار المشترك في معالج التهيئة إلى حالة فعلية:
 * موديولات مفعّلة + إعدادات افتراضية + ثيم.
 *
 * النمط يحدّد المجموعة كاملة: ما ليس فيه يُعطَّل لا يُترك مفعّلاً،
 * وإلا ظهرت في قائمة السنتر بقايا نمط سابق. التعطيل إخفاء فقط —
 * بيانات الموديول تبقى في القاعدة كما هي، فالعودة للنمط تعيدها.
 */
final class ApplyPlatformMode
{
    /** @return list<string> الموديولات التي أصبحت مفعّلة */
    public function handle(Tenant $tenant, ?string $mode = null, ?string $delivery = null): array
    {
        $mode = $mode ?? $tenant->platform_mode;
        $delivery = $delivery ?? $tenant->delivery_mode;

        $config = config("platform-modes.modes.{$mode}");

        if ($config === null) {
            throw new InvalidArgumentException("نمط منصة غير معروف: [{$mode}]");
        }

        $modules = [
            ...$config['modules'],
            ...(config("platform-modes.delivery.{$delivery}.modules") ?? []),
        ];

        if ($tenant->center_enabled && ! in_array('center', $modules, true)) {
            // إدارة السنتر خيار مستقل: مدرّس فردي قد يدير سنتراً صغيراً
            $modules = [...$modules, 'center', 'attendance', 'center-finance', 'parent-portal'];
        }

        $modules = array_values(array_unique($modules));

        $tenant->run(function () use ($modules, $config): void {
            $now = now();

            foreach ($modules as $key) {
                DB::table('modules')->updateOrInsert(
                    ['key' => $key],
                    ['enabled' => true, 'updated_at' => $now, 'created_at' => $now],
                );
            }

            // ما ليس من هذا النمط يُخفى — والبيانات تبقى كما هي
            DB::table('modules')->whereNotIn('key', $modules)->update([
                'enabled' => false,
                'updated_at' => $now,
            ]);

            foreach ($config['settings'] as $path => $value) {
                [$group, $key] = explode('.', $path, 2);

                DB::table('settings')->updateOrInsert(
                    ['group' => $group, 'key' => $key],
                    ['value' => json_encode($value, JSON_UNESCAPED_UNICODE), 'updated_at' => $now, 'created_at' => $now],
                );
            }
        });

        $tenant->forceFill([
            'platform_mode' => $mode,
            'delivery_mode' => $delivery,
            'theme' => $tenant->theme ?: $config['theme'],
        ])->save();

        return $modules;
    }
}
