<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Content\Models\Form;
use App\Modules\Content\Models\Page;
use App\Modules\Content\Models\Post;
use App\Modules\Services\Models\Availability;
use App\Modules\Services\Models\Service;
use App\Modules\Services\Models\ServiceProvider;

/*
 | مصانع بيانات المحتوى والخدمات — تُستدعى دائماً داخل سياق مشترك.
 */

function seedPage(array $overrides = []): Page
{
    return Page::create([
        'slug' => 'page-'.uniqid(),
        'title' => ['ar' => 'صفحة اختبار', 'en' => 'Test page'],
        'status' => 'published',
        'published_at' => now(),
        'blocks' => [
            [
                'type' => 'hero',
                'content' => ['heading' => ['ar' => 'عنوان البطل', 'en' => 'Hero heading']],
                'settings' => ['width' => 'wide'],
            ],
        ],
        ...$overrides,
    ]);
}

function seedPost(array $overrides = []): Post
{
    return Post::create([
        'slug' => 'post-'.uniqid(),
        'title' => ['ar' => 'مقال اختبار', 'en' => 'Test post'],
        'excerpt' => ['ar' => 'مقتطف قصير.'],
        'body' => ['ar' => 'نصّ المقال الكامل يشرح الفكرة خطوة بخطوة.'],
        'status' => 'published',
        'published_at' => now()->subDay(),
        'allow_comments' => true,
        ...$overrides,
    ]);
}

function seedForm(array $overrides = []): Form
{
    return Form::create([
        'key' => 'form-'.uniqid(),
        'name' => ['ar' => 'نموذج اختبار'],
        'fields' => [
            ['name' => 'name', 'label' => ['ar' => 'الاسم'], 'type' => 'text', 'required' => true],
            ['name' => 'message', 'label' => ['ar' => 'رسالتك'], 'type' => 'textarea', 'required' => true],
        ],
        'is_active' => true,
        ...$overrides,
    ]);
}

/** خدمة موعدية بمقدّم واحد وساعات عمل طوال الأسبوع. */
function seedService(array $overrides = []): Service
{
    $service = Service::create([
        'slug' => 'service-'.uniqid(),
        'title' => ['ar' => 'استشارة', 'en' => 'Consultation'],
        'type' => 'appointment',
        'currency' => (string) (tenant('currency') ?? 'EGP'),
        'price_minor' => 50000,
        'price_type' => 'fixed',
        'duration_minutes' => 60,
        'buffer_minutes' => 0,
        'lead_hours' => 0,
        'cancel_hours' => 24,
        'max_per_slot' => 1,
        'status' => 'published',
        ...$overrides,
    ]);

    if ($service->type === 'appointment') {
        seedProviderFor($service);
    }

    return $service;
}

function seedProviderFor(Service $service, ?User $user = null): ServiceProvider
{
    $user ??= User::where('role', 'owner')->firstOrFail();

    $provider = ServiceProvider::create([
        'service_id' => $service->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
    ]);

    foreach (range(0, 6) as $weekday) {
        Availability::create([
            'provider_id' => $provider->getKey(),
            'weekday' => $weekday,
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
        ]);
    }

    return $provider;
}
