<?php

declare(strict_types=1);

namespace App\Core\Tenancy\Actions;

use App\Core\Billing\Actions\StartSubscription;
use App\Core\Entitlements\Models\Plan;
use App\Core\Tenancy\Models\Domain;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * ADR-009 — التجهيز الآلي: من الدفع إلى منصة عاملة.
 *
 * كل خطوة قابلة للتراجع؛ الفشل في أي مرحلة يترك الحالة نظيفة
 * ورسالة سبب مفهومة في provision_error بدل مشترك نصف مجهّز.
 */
final class ProvisionTenant
{
    public function __construct(
        private readonly ApplyPlatformMode $applyMode,
        private readonly StartSubscription $subscriptions,
    ) {}

    /**
     * @param  array{name:string, owner_email:string, owner_name?:string, owner_phone?:string,
     *               slug?:string, plan_key?:string, platform_mode?:string, delivery_mode?:string,
     *               center_enabled?:bool, country?:string, currency?:string, locale?:string,
     *               timezone?:string, password?:string}  $input
     */
    public function handle(array $input): Tenant
    {
        /*
         | التجهيز يُنشئ قاعدة ويُهاجرها: عشرات الجداول، وهو أطول من
         | مهلة تنفيذ طلبٍ عادية. وانقطاعه في منتصفه يترك قاعدة نصف
         | مبنيّة ومشتركاً عالقاً في «قيد التجهيز» ونطاقاً محجوزاً لا
         | يُستعمل — وهو ما حدث فعلاً في أول تسجيلين حقيقيين.
         |
         | فنمنحه مهلته صراحةً، ونُنظّف خلفنا إن فشل رغم ذلك.
         |
         | ## لكنّه يرفع المهلة ولا يفرضها
         |
         | في سطر الأوامر لا مهلةَ أصلاً (`max_execution_time = 0`)،
         | و`set_time_limit(180)` هناك **يفرض** حدّاً لم يكن — ويعيد
         | تصفير عدّاده. فكان تشغيل الاختبارات يموت بـ«Maximum
         | execution time of 180 seconds exceeded» في اختبارٍ لا
         | علاقة له بالتجهيز، لأن أوّل تجهيزٍ فيه فرض الحدّ على
         | العملية كلّها.
         */
        $limit = (int) ini_get('max_execution_time');

        if ($limit > 0 && $limit < 180 && function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $slug = $this->uniqueSlug($input['slug'] ?? $input['name']);
        $plan = isset($input['plan_key']) ? Plan::find($input['plan_key']) : null;

        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => $input['name'],
            'slug' => $slug,
            'owner_name' => $input['owner_name'] ?? null,
            'owner_email' => $input['owner_email'],
            'owner_phone' => $input['owner_phone'] ?? null,
            'platform_mode' => $input['platform_mode'] ?? config('platform-modes.default'),
            'delivery_mode' => $input['delivery_mode'] ?? 'recorded',
            'center_enabled' => $input['center_enabled'] ?? false,
            'country' => $input['country'] ?? 'EG',
            'currency' => $input['currency'] ?? $this->currencyFor($input['country'] ?? 'EG'),
            'locale' => $input['locale'] ?? 'ar',
            'timezone' => $input['timezone'] ?? $this->timezoneFor($input['country'] ?? 'EG'),
            'plan_key' => $plan?->key,
            'status' => 'provisioning',
            'trial_ends_at' => $plan ? now()->addDays($plan->trial_days) : null,
        ]);

        try {
            // 1) النطاق الفرعي — دائماً، لكل الباقات
            Domain::create([
                'tenant_id' => $tenant->id,
                'domain' => $slug.'.'.config('tenancy.base_domain', 'localhost'),
                'type' => 'subdomain',
                'is_primary' => true,
                'ssl_status' => 'not_required',
                'verified_at' => now(),
            ]);

            // 2) قاعدة البيانات والهجرات (يتكفّل بها stancl عبر أحداث الإنشاء)
            //    ثم 3) النمط: الموديولات والإعدادات والثيم
            $this->applyMode->handle($tenant);

            // 4) حساب المدير الأول
            $password = $input['password'] ?? Str::password(12);

            $tenant->run(function () use ($input, $password): void {
                DB::table('users')->insert([
                    'name' => $input['owner_name'] ?? $input['name'],
                    'email' => $input['owner_email'],
                    'phone' => $input['owner_phone'] ?? null,
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'locale' => $input['locale'] ?? 'ar',
                    'status' => 'active',
                    'role' => 'owner',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            $tenant->forceFill([
                'status' => $plan && $plan->trial_days > 0 ? 'trialing' : 'active',
                'provisioned_at' => now(),
                'provision_error' => null,
            ])->save();

            // 5) الاشتراك — يُجمَّد سعر الباقة وقت الاشتراك، فلا يمسّه رفع لاحق
            if ($plan !== null) {
                $this->subscriptions->handle($tenant->refresh(), $plan->key, $tenant->currency);
            }

            return $tenant->refresh();
        } catch (Throwable $e) {
            Log::error('فشل تجهيز مشترك', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);

            /*
             | التراجع الكامل لا التوقّف في المنتصف.
             |
             | مشترك عالق في «قيد التجهيز» يحجز نطاقه ولا يعمل: صاحبه
             | لا يدخل، ولا يستطيع التسجيل بالاسم نفسه مرة أخرى. فنُزيل
             | ما بنيناه ونترك الاسم متاحاً ليعيد المحاولة فوراً.
             */
            $this->rollBack($tenant);

            throw $e;
        }
    }

    /**
     * إزالة ما بُني قبل الفشل: القاعدة ثم النطاق ثم السجلّ.
     *
     * كلٌّ في محاولته: قاعدة تأبى الحذف يجب ألّا تمنع تحرير النطاق،
     * وإلا بقي الاسم محجوزاً إلى الأبد بسبب ملف عالق.
     */
    private function rollBack(Tenant $tenant): void
    {
        try {
            if ($tenant->database()->manager()->databaseExists((string) $tenant->database()->getName())) {
                $tenant->database()->manager()->deleteDatabase($tenant);
            }
        } catch (Throwable $e) {
            Log::warning('تعذّر حذف قاعدة مشترك فاشل', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);
        }

        try {
            Domain::where('tenant_id', $tenant->id)->delete();
            $tenant->delete();
        } catch (Throwable $e) {
            Log::warning('تعذّر حذف سجلّ مشترك فاشل', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);
        }
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'academy';
        $slug = $base;

        for ($i = 2; Tenant::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /**
     * جدول الدول هو المرجع؛ وحين يغيب الصف نرجع إلى افتراضي المنصة
     * لا إلى الدولار: مشترك مصري يُنشأ بعملة أجنبية عطلٌ صامت،
     * يظهر متأخراً في أول فاتورة.
     */
    private function currencyFor(string $country): string
    {
        return $this->reference('countries')->where('code', $country)->value('currency')
            ?? (string) config('money.default', 'EGP');
    }

    private function timezoneFor(string $country): string
    {
        return $this->reference('countries')->where('code', $country)->value('timezone_default')
            ?? (string) config('app.timezone', 'UTC');
    }

    /**
     * جدول مرجعي مركزي — بالاتصال المركزي صراحةً.
     *
     * `DB::table()` تستعمل الاتصال الافتراضي، وهو اتصال المشترك حين
     * يكون سياقه مهيّأً. وتجهيزُ مشترك من داخل سياق مشترك آخر —
     * وهو ما يحدث في اللوحة العليا وفي البذور — كان يسأل قاعدةً
     * لا وجود لجدول الدول فيها.
     */
    private function reference(string $table): Builder
    {
        return DB::connection(config('tenancy.database.central_connection'))->table($table);
    }
}
