<?php

declare(strict_types=1);

use App\Core\Seo\Seo;
use App\Models\User;
use App\Modules\Lms\Actions\EnrollStudent;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

/*
 | السيو الداخلي: الوسوم والبيانات المنظّمة والخريطة وrobots.
 */

it('يقصّ العنوان عند حدّ نتيجة البحث', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $long = str_repeat('كلمة طويلة ', 20);

        expect(mb_strlen(app(Seo::class)->title($long)))->toBeLessThanOrEqual(65);
    });
});

it('يقصّ الوصف ويجرّده من الوسوم', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $description = app(Seo::class)->description('<p>نصّ فيه <b>وسوم</b> '.str_repeat('وكلام كثير ', 40).'</p>');

        expect($description)->not->toContain('<')
            ->and(mb_strlen((string) $description))->toBeLessThanOrEqual(161);
    });
});

it('يبني الرابط القانوني بلا استعلامات', function (): void {
    $tenant = provision();
    $slug = $tenant->run(fn () => seedCourse()->slug);

    $response = tenantGet($tenant, '/courses/'.$slug.'?utm_source=facebook&page=2')->assertOk();

    // صفحة بفلتر ليست صفحة أخرى، والقانوني يوحّدهما
    expect($response->getContent())->toContain('rel="canonical"')
        ->and($response->getContent())->toContain('/courses/'.$slug.'"')
        ->and($response->getContent())->not->toContain('canonical" href="'.tenantUrl($tenant, '/courses/'.$slug).'?');
});

it('يضع hreflang للغتين وx-default', function (): void {
    $tenant = provision();
    $slug = $tenant->run(fn () => seedCourse()->slug);

    $response = tenantGet($tenant, '/courses/'.$slug)->assertOk();

    expect($response->getContent())->toContain('hreflang="ar"')
        ->toContain('hreflang="en"')
        ->toContain('hreflang="x-default"')
        ->toContain('/en/courses/'.$slug);
});

it('يبني بيانات Course المنظّمة بسعرها وتقييمها', function (): void {
    $tenant = provision();

    $slug = $tenant->run(function (): string {
        $course = seedCourse(['price_minor' => 149900, 'enrollment_type' => 'paid']);
        $course->forceFill(['rating_avg' => 4.7, 'ratings_count' => 12])->save();

        return $course->slug;
    });

    $response = tenantGet($tenant, '/courses/'.$slug)->assertOk();

    expect($response->getContent())->toContain('"@type":"Course"')
        ->toContain('"ratingValue":4.7')
        ->toContain('"price":"1499.00"');
});

it('يمنع الفهرسة حين يطفئها المشترك', function (): void {
    $tenant = provision();
    $slug = $tenant->run(function (): string {
        setting()->set('seo.indexable', false);

        return seedCourse()->slug;
    });

    expect(tenantGet($tenant, '/courses/'.$slug)->getContent())
        ->toContain('content="noindex, nofollow"');
});

it('يمنع فهرسة مسودّة الصفحة ولو كان الموقع مفهرساً', function (): void {
    $tenant = provision();
    $slug = $tenant->run(fn () => seedPage(['status' => 'draft', 'published_at' => null])->slug);

    actingAsOwner($tenant);

    expect(tenantGet($tenant, '/'.$slug)->getContent())
        ->toContain('content="noindex, nofollow"');
});

it('يُقدّم فهرس الخريطة بأقسامه', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        seedCourse();
        seedPost();
    });

    $response = tenantGet($tenant, '/sitemap.xml')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/xml')
        ->and($response->getContent())->toContain('sitemap-courses.xml')
        ->toContain('sitemap-posts.xml');
});

it('يُدرج الكورس المنشور وحده في خريطته', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        seedCourse(['slug' => 'public-course']);
        seedCourse(['slug' => 'hidden-course', 'status' => 'draft']);
    });

    $content = tenantGet($tenant, '/sitemap-courses.xml')->assertOk()->getContent();

    expect($content)->toContain('/courses/public-course')
        ->and($content)->not->toContain('/courses/hidden-course');
});

it('يرفض قسم خريطة غير معروف', function (): void {
    $tenant = provision();

    tenantGet($tenant, '/sitemap-secrets.xml')->assertNotFound();
});

it('يستثني اللوحة والدفع في robots ويشير إلى الخريطة', function (): void {
    $tenant = provision();

    $content = tenantGet($tenant, '/robots.txt')->assertOk()->getContent();

    expect($content)->toContain('Disallow: /admin/')
        ->toContain('Disallow: /checkout')
        ->toContain('Sitemap: ');
});

it('يمنع كل الزحف حين يُطفأ الفهرسة', function (): void {
    $tenant = provision();

    $tenant->run(fn () => setting()->set('seo.indexable', false));

    expect(tenantGet($tenant, '/robots.txt')->getContent())->toContain("Disallow: /\n");
});

it('يقدّم robots المخصّص كما كتبه المشترك', function (): void {
    $tenant = provision();

    $tenant->run(fn () => setting()->set('seo.robots_txt', "User-agent: BadBot\nDisallow: /"));

    expect(tenantGet($tenant, '/robots.txt')->getContent())->toContain('BadBot');
});

/*
 | التحليلات.
 */

it('لا يحقن شيئاً حين لا معرّفات', function (): void {
    $tenant = provision();

    $content = tenantGet($tenant, '/')->assertOk()->getContent();

    expect($content)->not->toContain('googletagmanager.com')
        ->and($content)->not->toContain('connect.facebook.net');
});

it('يحقن GA4 وبكسل ميتا حين تُضبط معرّفاتهما', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('analytics.ga4_id', 'G-TESTID');
        setting()->set('analytics.meta_pixel_id', '123456789');
    });

    $content = tenantGet($tenant, '/')->assertOk()->getContent();

    expect($content)->toContain('G-TESTID')
        ->toContain('123456789')
        ->toContain("gtag('consent', 'default'");
});

it('يرسل حدث الشراء من صفحة الطلب المدفوع وحدها', function (): void {
    $tenant = provision();

    [$paid, $pending] = $tenant->run(function (): array {
        setting()->set('analytics.ga4_id', 'G-TESTID');

        $owner = User::where('role', 'owner')->firstOrFail();

        return [
            seedPaidOrder(['user_id' => $owner->getKey()])->number,
            seedPaidOrder(['user_id' => $owner->getKey(), 'status' => 'pending'])->number,
        ];
    });

    actingAsOwner($tenant);

    expect(tenantGet($tenant, '/orders/'.$paid)->getContent())->toContain('purchase')
        ->and(tenantGet($tenant, '/orders/'.$pending)->getContent())->not->toContain("'purchase'");
});

/*
 | التقارير.
 */

it('يفتح التقارير الثلاثة', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $course = seedCourse();
        app(EnrollStudent::class)->handle(seedStudent(), $course, 'free');
    });

    actingAsOwner($tenant);

    foreach (['learning', 'financial', 'marketing'] as $tab) {
        tenantGet($tenant, '/admin/reports?tab='.$tab)->assertOk();
    }
});

it('يملأ الأيام الصفرية في السلسلة اليومية', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $course = seedCourse();
        app(EnrollStudent::class)->handle(seedStudent(), $course, 'free');
    });

    actingAsOwner($tenant);

    $response = tenantGet($tenant, '/admin/reports?tab=learning&preset=7')->assertOk();

    // ثمانية أيام في المدى (اليوم وسبعة قبله) وكلّها ظاهرة في الجدول البديل
    expect(substr_count($response->getContent(), '<tr><td>20'))->toBeGreaterThanOrEqual(7);
});

it('يصحّح المدى المقلوب بدل أن يُرجع جدولاً فارغاً', function (): void {
    $tenant = provision();

    actingAsOwner($tenant);

    // المقلوب يُصحَّح: التقرير يبدأ من الأقدم لا يعود فارغاً
    tenantGet($tenant, '/admin/reports?tab=learning&preset=custom&from=2026-06-30&to=2026-06-01')
        ->assertOk()
        ->assertSee('2026-06-01', false)
        ->assertSee('2026-06-30', false);
});

it('يصدّر التقرير CSV ببادئة BOM', function (): void {
    $tenant = provision();

    actingAsOwner($tenant);

    $response = tenantGet($tenant, '/admin/reports/export?tab=financial')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('report-financial-')
        // بلا BOM تُقرأ العربية رموزاً في إكسل
        ->and(substr($response->getContent(), 0, 3))->toBe("\u{FEFF}");
});
