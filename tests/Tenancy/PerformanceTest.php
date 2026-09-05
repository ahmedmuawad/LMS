<?php

declare(strict_types=1);

use App\Core\Media\ImageVariants;
use App\Core\Settings\SettingsRepository;
use App\Models\User;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Cache;

/*
| كاش الصفحة ونسخُ الصور.
|
| والاختبار هنا لأن أخطر ما فيهما صامت: صفحةٌ فيها اسمُ مسجَّلٍ
| تُخزَّن فيراها كلُّ زائرٍ بعده باسمه — وهذا تسريبُ هويّة لا بطءُ
| صفحة، ولا يظهر في لوج ولا يشتكي منه أحدٌ حتى يقع.
*/

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);

    $this->tenant = provision();

    $this->tenant->run(function (): void {
        $settings = app(SettingsRepository::class);
        $settings->set('performance.cached_pages', ['home', 'course_list']);
        $settings->set('performance.page_cache_minutes', 60);
        $settings->flush();
    });

    Cache::flush();
});

it('serves the second guest visit from the cache', function () {
    tenantGet($this->tenant, '/')->assertOk()->assertHeader('X-Page-Cache', 'miss');
    tenantGet($this->tenant, '/')->assertOk()->assertHeader('X-Page-Cache', 'hit');
});

it('never caches a page rendered for a signed-in user', function () {
    /*
     | وهذا أهمّ اختبارٍ في الملفّ.
     |
     | صفحةٌ فيها «أهلاً يا أحمد» تُخزَّن فيراها كلُّ زائرٍ بعده باسم
     | أحمد — تسريبُ هويّة لا بطءُ صفحة.
     */
    $this->tenant->run(function (): void {
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $this->actingAs($user);
    });

    /*
     | وغيابُ الترويسة هو الإثبات.
     |
     | المِدلوير يخرج مبكّراً لما لا يُخزَّن، فلا يضع ترويسةً أصلاً —
     | ووجودُ «miss» يعني أنه نظر في الكاش، وهو ما لا نريده هنا.
     */
    tenantGet($this->tenant, '/')->assertOk()->assertHeaderMissing('X-Page-Cache');
    tenantGet($this->tenant, '/')->assertOk()->assertHeaderMissing('X-Page-Cache');
});

it('leaves a page out of the cache when the tenant did not choose it', function () {
    $this->tenant->run(function (): void {
        $settings = app(SettingsRepository::class);
        $settings->set('performance.cached_pages', ['blog']);
        $settings->flush();
    });

    tenantGet($this->tenant, '/')->assertOk()->assertHeaderMissing('X-Page-Cache');
    tenantGet($this->tenant, '/')->assertOk()->assertHeaderMissing('X-Page-Cache');
});

it('never caches the panel whatever the setting says', function () {
    $this->tenant->run(function (): void {
        $settings = app(SettingsRepository::class);
        $settings->set('performance.cached_pages', ['home', 'course_list', 'blog', 'pages']);
        $settings->flush();
    });

    // اللوحة تُعيد تحويلاً إلى الدخول — والمهمّ أنها لا تُخزَّن
    tenantGet($this->tenant, '/admin/dashboard')->assertHeaderMissing('X-Page-Cache');
});

it('refuses a width that is not on the allowed list', function () {
    /*
     | القائمة المغلقة هي الحماية.
     |
     | `?w=` مفتوحٌ يعني أن أيَّ زائرٍ يطلب ألف مقاسٍ في الدقيقة،
     | فيشغل المعالج ويملأ القرص بصورٍ لا يراها أحد.
     */
    tenantGet($this->tenant, '/img/333/webp/library/x.jpg')->assertNotFound();
    tenantGet($this->tenant, '/img/640/gif/library/x.jpg')->assertNotFound();
});

it('refuses a path that climbs out of the media folder', function () {
    // `..` في مسارٍ من الرابط يقرأ ملفّاتٍ خارج مجلّد الوسائط — ومنها `.env`
    tenantGet($this->tenant, '/img/640/webp/../../.env')->assertNotFound();
});

it('offers only the formats this server can actually write', function () {
    $this->tenant->run(function (): void {
        $settings = app(SettingsRepository::class);
        $settings->set('performance.image_format', 'both');
        $settings->flush();

        $variants = app(ImageVariants::class);

        /*
         | إعدادُ المشترك رغبةٌ لا قدرة.
         |
         | ومنصّةٌ تَعِد بـAVIF على خادمٍ لا يكتبها ترسل صورةً مكسورة
         | — وهذا أسوأ من WebP بلا وعد.
         */
        foreach ($variants->formats() as $format) {
            expect($variants->supports($format))->toBeTrue();
        }
    });
});

it('rounds a requested width up to the nearest allowed one', function () {
    expect(ImageVariants::nearestWidth(300))->toBe(320)
        ->and(ImageVariants::nearestWidth(700))->toBe(960)
        ->and(ImageVariants::nearestWidth(4000))->toBe(1920);
});
