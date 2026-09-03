<?php

declare(strict_types=1);

use App\Core\Modules\ModuleState;

if (! function_exists('module_enabled')) {
    /**
     * هل هذا الموديول مفعّل لهذا المشترك؟
     *
     * الواجهة العامة تسأل السؤال نفسه الذي تسأله القائمة الإدارية:
     * رابط في الهيدر لقسم مطفأ يقود إلى شاشة فارغة أو 404.
     */
    function module_enabled(string $module): bool
    {
        return app(ModuleState::class)->enabled($module);
    }
}
