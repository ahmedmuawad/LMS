<?php

declare(strict_types=1);

namespace App\Core\Tenancy\Bootstrappers;

use Illuminate\Support\Facades\Config;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * رابط وسائط المشترك.
 *
 * `FilesystemTenancyBootstrapper` يحوّل **جذر** القرص العام إلى مجلّد
 * المشترك، ولا يمسّ **رابطه**. فيبقى الرابط `APP_URL/storage/…` وهو:
 *   ١) على النطاق المركزي لا على نطاق المشترك،
 *   ٢) ومصادف لمسار خدمة القرص الخاص (`serve => true` في disks.local)،
 *      الذي يبحث في `app/private` فيردّ 403.
 * فكانت كل صورة يرفعها مشترك — شعاره وأغلفة كورساته وصور منتجاته —
 * غير قابلة للعرض، بلا رسالة خطأ في الشاشة.
 *
 * مسار `stancl.tenancy.asset` يخدم `storage_path('app/public')` بعد
 * لصق لاحقة المشترك، فهو الوجهة الصحيحة. نوجّه الرابط إليه هنا لتصحّ
 * الروابط في الطلب والطابور والأمر على السواء.
 */
final class TenantMediaUrlBootstrapper implements TenancyBootstrapper
{
    private ?string $original = null;

    public function bootstrap(Tenant $tenant): void
    {
        $this->original = Config::get('filesystems.disks.public.url');

        Config::set('filesystems.disks.public.url', $this->baseUrl($tenant).'/tenancy/assets');
    }

    public function revert(): void
    {
        Config::set('filesystems.disks.public.url', $this->original);

        $this->original = null;
    }

    /**
     * أصل نطاق المشترك.
     *
     * داخل طلبٍ على نطاق المشترك يكفي `url()`؛ أمّا في طابور أو أمر
     * سطر أوامر فلا طلب، و`url()` تعطي النطاق المركزي — فيخرج رابط
     * صورة في فاتورة أو بريد على نطاق غير نطاق صاحبها.
     */
    private function baseUrl(Tenant $tenant): string
    {
        $host = request()?->getHost();
        $domain = $tenant->domains->firstWhere('is_primary', true)?->domain
            ?? $tenant->domains->first()?->domain;

        if ($domain === null || $host === $domain) {
            return rtrim(url('/'), '/');
        }

        $request = request();
        $scheme = $request?->getScheme() ?? 'https';
        $port = $request?->getPort();
        $suffix = in_array($port, [null, 80, 443], true) ? '' : ':'.$port;

        return $scheme.'://'.$domain.$suffix;
    }
}
