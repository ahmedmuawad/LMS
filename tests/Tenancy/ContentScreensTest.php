<?php

declare(strict_types=1);

use App\Modules\Content\Models\Media;
use App\Modules\Content\Models\Page;
use App\Modules\Services\Models\Booking;
use App\Modules\Services\Models\Service;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

/*
 | شاشات المرحلة السادسة: باني الصفحات والوسائط وقوائم المحتوى والخدمات.
 */

it('يفتح قائمة الصفحات وباني الصفحة', function (): void {
    $tenant = provision();
    $page = $tenant->run(fn () => seedPage());

    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/page-builder')->assertOk()->assertSee('الصفحات');
    tenantGet($tenant, '/admin/page-builder/'.$page->id)->assertOk()->assertSee('أضف كتلة');
});

it('يحفظ كتل الصفحة كما يرسلها المحرّر', function (): void {
    $tenant = provision();
    $page = $tenant->run(fn () => seedPage(['blocks' => []]));

    actingAsOwner($tenant);

    tenantPut($tenant, '/admin/page-builder/'.$page->id, [
        'title' => ['ar' => 'صفحة محدّثة'],
        'slug' => $page->slug,
        'status' => 'published',
        'blocks' => [
            json_encode(['type' => 'hero', 'content' => ['heading' => ['ar' => 'عنوان جديد']], 'settings' => ['width' => 'narrow']]),
            json_encode(['type' => 'unknown-block', 'content' => []]),
        ],
    ])->assertRedirect();

    $tenant->run(function () use ($page): void {
        $fresh = Page::find($page->id);

        expect($fresh->blocks)->toHaveCount(1)
            ->and($fresh->blocks[0]['type'])->toBe('hero')
            ->and($fresh->blocks[0]['content']['heading']['ar'])->toBe('عنوان جديد')
            ->and($fresh->status)->toBe('published');
    });
});

it('يمنع تغيير رابط الصفحة الإلزامية', function (): void {
    $tenant = provision();

    $page = $tenant->run(fn () => seedPage([
        'is_system' => true, 'system_key' => 'about', 'slug' => 'about',
    ]));

    actingAsOwner($tenant);

    tenantPut($tenant, '/admin/page-builder/'.$page->id, [
        'title' => ['ar' => 'من نحن'],
        'slug' => 'hijacked',
        'status' => 'published',
    ])->assertRedirect();

    $tenant->run(fn () => expect(Page::find($page->id)->slug)->toBe('about'));
});

it('ينشئ صفحة جديدة ويفتح محرّرها مباشرة', function (): void {
    $tenant = provision();

    actingAsOwner($tenant);

    tenantPost($tenant, '/admin/page-builder', ['title' => 'صفحة الأسعار', 'slug' => 'pricing'])
        ->assertRedirect();

    $tenant->run(fn () => expect(Page::where('slug', 'pricing')->exists())->toBeTrue());
});

it('يرفع ملفاً إلى المكتبة ويعرضه', function (): void {
    $tenant = provision();
    Storage::fake('public');

    actingAsOwner($tenant);

    tenantPost($tenant, '/admin/media', [
        'files' => [UploadedFile::fake()->image('cover.png', 60, 60)],
    ])->assertRedirect();

    $tenant->run(fn () => expect(Media::count())->toBe(1));

    tenantGet($tenant, '/admin/media')->assertOk()->assertSee('cover.png');
});

it('ينبّه على الصورة بلا نص بديل', function (): void {
    $tenant = provision();
    Storage::fake('public');

    $tenant->run(fn () => Media::create([
        'disk' => 'public', 'path' => 'library/a.png', 'name' => 'a.png',
        'mime' => 'image/png', 'size' => 1200, 'width' => 60, 'height' => 60,
    ]));

    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/media')->assertOk()->assertSee('بلا نص بديل');
});

it('يحذف ملف الوسائط وسجلّه معاً', function (): void {
    $tenant = provision();
    Storage::fake('public');

    $media = $tenant->run(fn () => Media::create([
        'disk' => 'public', 'path' => 'library/a.png', 'name' => 'a.png',
        'mime' => 'image/png', 'size' => 1200,
    ]));

    actingAsOwner($tenant);

    tenantDelete($tenant, '/admin/media/'.$media->id)->assertRedirect();

    $tenant->run(fn () => expect(Media::count())->toBe(0));
});

it('يفتح قوائم المحتوى والخدمات في اللوحة', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        seedPost();
        seedForm();
        seedService();
    });

    actingAsOwner($tenant);

    foreach (['posts', 'pages', 'comments', 'forms', 'redirects', 'services', 'bookings'] as $resource) {
        tenantGet($tenant, '/admin/'.$resource)->assertOk();
    }
});

it('يحرّر خدمة من اللوحة ويحفظ قوائمها بنيةً لا نصّاً', function (): void {
    $tenant = provision();
    $service = $tenant->run(fn () => seedService());

    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/services/'.$service->id.'/edit')->assertOk();

    tenantPut($tenant, '/admin/services/'.$service->id, [
        'title' => ['ar' => 'استشارة مطوّرة'],
        'slug' => $service->slug,
        'type' => 'appointment',
        'currency' => $service->currency,
        'price_type' => 'fixed',
        'price_minor' => 60000,
        'duration_minutes' => 45,
        'buffer_minutes' => 0,
        'lead_hours' => 12,
        'cancel_hours' => 24,
        'max_per_slot' => 1,
        'delivery_days' => 0,
        'location' => 'online',
        'status' => 'published',
        'deliverables' => json_encode(['خطة مكتوبة', 'قائمة مصادر']),
        'requirements' => json_encode(['ما هدفك؟']),
    ])->assertRedirect();

    $tenant->run(function () use ($service): void {
        $fresh = Service::find($service->id);

        expect($fresh->deliverables)->toBe(['خطة مكتوبة', 'قائمة مصادر'])
            ->and($fresh->duration_minutes)->toBe(45);
    });
});

it('يتابع حجزاً من اللوحة ويؤكّده', function (): void {
    $tenant = provision();

    $booking = $tenant->run(function (): Booking {
        $service = seedService();

        return Booking::create([
            'reference' => 'BK-TEST-0009',
            'service_id' => $service->getKey(),
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '09:00:00', 'ends_at' => '10:00:00',
            'status' => 'pending',
            'currency' => $service->currency, 'price_minor' => $service->price_minor,
        ]);
    });

    actingAsOwner($tenant);

    tenantPut($tenant, '/admin/bookings/'.$booking->id, [
        'status' => 'confirmed',
        'meeting_url' => 'https://meet.example.test/abc',
    ])->assertRedirect();

    $tenant->run(fn () => expect(Booking::find($booking->id)->status)->toBe('confirmed'));
});
