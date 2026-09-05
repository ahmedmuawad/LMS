<?php

declare(strict_types=1);

namespace App\Core\Tenancy\Bootstrappers;

use App\Core\Access\Roles;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * ينسى ما حُفظ عن المشترك السابق.
 *
 * ## لماذا يلزم أصلاً
 *
 * `Roles` مفردةٌ في الحاوية عمداً — الحكم على الصلاحيات مصدرُه واحد.
 * ولمّا صار يقرأ توزيعاً من قاعدة المشترك، صارت المفردة تحمل توزيع
 * أوّل مشترك مرّ بها إلى كل من بعده.
 *
 * ولا يظهر ذلك في طلبٍ عادي: الطلب يخصّ مشتركاً واحداً. يظهر في
 * الطابور — عاملٌ يعالج مهمّةً لمشتركٍ ثم أخرى لغيره في العملية
 * نفسها — وفي أوامر السطر التي تمرّ على المشتركين، وفي الاختبارات.
 *
 * وهو أخطر ما يقع من هذا الصنف: صلاحيةٌ يملكها موظّفٌ عند مشتركٍ
 * تُمنح لموظّفٍ عند غيره.
 */
final class ScopedStateBootstrapper implements TenancyBootstrapper
{
    public function bootstrap(Tenant $tenant): void
    {
        app(Roles::class)->forgetOverrides();
    }

    public function revert(): void
    {
        // والعودة إلى السياق المركزي تُنسى أيضاً: توزيعُ مشتركٍ ليس توزيعنا
        app(Roles::class)->forgetOverrides();
    }
}
