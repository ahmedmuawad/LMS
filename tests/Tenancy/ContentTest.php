<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Content\Actions\InstallSystemPages;
use App\Modules\Content\Actions\StoreMedia;
use App\Modules\Content\Blocks\BlockRegistry;
use App\Modules\Content\Models\Comment;
use App\Modules\Content\Models\FormSubmission;
use App\Modules\Content\Models\Media;
use App\Modules\Content\Models\Page;
use App\Modules\Content\Models\Post;
use App\Modules\Content\Models\Redirect;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 | المحتوى: الكتل والصفحات والمدونة والتعليقات والنماذج والتحويلات.
 */

it('يُسقط الكتل المجهولة ولا يخزّنها', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $clean = app(BlockRegistry::class)->sanitizeAll([
            ['type' => 'hero', 'content' => ['heading' => ['ar' => 'أهلاً']], 'settings' => ['width' => 'narrow']],
            ['type' => 'evil-script', 'content' => ['body' => '<script>alert(1)</script>']],
        ]);

        expect($clean)->toHaveCount(1)
            ->and($clean[0]['type'])->toBe('hero');
    });
});

it('يحصر إعدادات الكتلة في المسموح ولا يمرّر مفتاحاً مجهولاً', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $clean = app(BlockRegistry::class)->sanitizeAll([
            [
                'type' => 'text',
                'content' => ['body' => ['ar' => 'نص'], 'onclick' => 'steal()'],
                'settings' => ['width' => 'narrow', 'class' => 'hacked'],
            ],
        ]);

        expect($clean[0]['settings'])->toHaveKey('width')
            ->and($clean[0]['settings'])->not->toHaveKey('class')
            ->and($clean[0]['content'])->not->toHaveKey('onclick');
    });
});

it('ينشئ الصفحات الإلزامية عند التهيئة ولا يكرّرها', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        // التهيئة أنشأتها فعلاً: موقع بلا سياسة خصوصية تُرفضه بوابات الدفع
        $existing = Page::where('is_system', true)->count();

        expect($existing)->toBeGreaterThan(0)
            ->and(app(InstallSystemPages::class)->handle())->toBe(0)
            ->and(Page::where('is_system', true)->count())->toBe($existing);
    });
});

it('يعرض الصفحة المنشورة ويحجب المسودّة عن الزائر', function (): void {
    $tenant = provision();

    $draft = $tenant->run(fn () => seedPage(['status' => 'draft', 'published_at' => null]));
    $live = $tenant->run(fn () => seedPage());

    tenantGet($tenant, '/'.$live->slug)->assertOk()->assertSee('عنوان البطل');
    tenantGet($tenant, '/'.$draft->slug)->assertNotFound();
});

it('يُري صاحب اللوحة مسودّة الصفحة للمعاينة', function (): void {
    $tenant = provision();
    $draft = $tenant->run(fn () => seedPage(['status' => 'draft', 'published_at' => null]));

    actingAsOwner($tenant);

    tenantGet($tenant, '/'.$draft->slug)->assertOk()->assertSee('معاينة مسودّة');
});

it('يعرض المدونة والمقال ويزيد عدّاد المشاهدة', function (): void {
    $tenant = provision();
    $post = $tenant->run(fn () => seedPost());

    tenantGet($tenant, '/blog')->assertOk()->assertSee('مقال اختبار');
    tenantGet($tenant, '/blog/'.$post->slug)->assertOk()->assertSee('نصّ المقال الكامل');

    $tenant->run(fn () => expect(Post::find($post->id)->views_count)->toBe(1));
});

it('يبحث في المدونة بالعنوان العربي', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        seedPost(['title' => ['ar' => 'خطة المذاكرة الأسبوعية']]);
        seedPost(['title' => ['ar' => 'أدوات المطوّر']]);
    });

    tenantGet($tenant, '/blog?q=المذاكرة')->assertOk()
        ->assertSee('خطة المذاكرة الأسبوعية')
        ->assertDontSee('أدوات المطوّر');
});

it('يحجز التعليق للمراجعة أول مرة ثم ينشره بعدها', function (): void {
    $tenant = provision();
    $post = $tenant->run(fn () => seedPost());

    $student = $tenant->run(fn () => User::create([
        'name' => 'طالبة', 'email' => 'student@t.test',
        'password' => 'password', 'status' => 'active', 'role' => 'student',
    ]));

    tenancy()->initialize($tenant);
    test()->actingAs($student);

    tenantPost($tenant, '/blog/'.$post->slug.'/comments', ['body' => 'تعليق أول.'])
        ->assertRedirect();

    expect(Comment::latest('id')->first()->status)->toBe('pending');

    Comment::latest('id')->first()->forceFill(['status' => 'approved'])->save();

    tenantPost($tenant, '/blog/'.$post->slug.'/comments', ['body' => 'تعليق ثانٍ.'])
        ->assertRedirect();

    expect(Comment::latest('id')->first()->status)->toBe('approved');
});

it('يرفض التعليق حين تكون التعليقات مغلقة على المقال', function (): void {
    $tenant = provision();
    $post = $tenant->run(fn () => seedPost(['allow_comments' => false]));

    tenantPost($tenant, '/blog/'.$post->slug.'/comments', ['body' => 'تعليق.'])
        ->assertSessionHasErrors('comment');

    $tenant->run(fn () => expect(Comment::count())->toBe(0));
});

it('يخزّن رسالة النموذج ويحقّق حقوله الإلزامية', function (): void {
    $tenant = provision();
    $form = $tenant->run(fn () => seedForm());

    tenantPost($tenant, '/forms/'.$form->key, ['data' => ['name' => '']])
        ->assertSessionHasErrors('data.name');

    tenantPost($tenant, '/forms/'.$form->key, [
        'data' => ['name' => 'سارة', 'message' => 'أريد الاشتراك.'],
    ])->assertRedirect();

    $tenant->run(function (): void {
        expect(FormSubmission::count())->toBe(1)
            ->and(FormSubmission::first()->data['name'])->toBe('سارة');
    });
});

it('يحوّل الرابط القديم إلى مقابله ويعدّ الزيارة', function (): void {
    $tenant = provision();

    $tenant->run(fn () => Redirect::create([
        'from' => '/old-page', 'to' => '/blog', 'code' => 301,
    ]));

    tenantGet($tenant, '/old-page')->assertRedirect(tenantUrl($tenant, '/blog'));

    $tenant->run(fn () => expect(Redirect::first()->hits)->toBe(1));
});

it('يردّ 404 حين لا صفحة ولا تحويل', function (): void {
    $tenant = provision();

    tenantGet($tenant, '/nothing-here-at-all')->assertNotFound();
});

it('يحجب المقال المجدول حتى يحين موعده', function (): void {
    $tenant = provision();

    $post = $tenant->run(fn () => seedPost([
        'status' => 'published', 'published_at' => now()->addWeek(),
    ]));

    tenantGet($tenant, '/blog/'.$post->slug)->assertNotFound();
    tenantGet($tenant, '/blog')->assertOk()->assertDontSee('مقال اختبار');
});

it('يخزّن مرفق النموذج في المكتبة لا في عمود JSON', function (): void {
    $tenant = provision();
    Storage::fake('public');

    $form = $tenant->run(fn () => seedForm([
        'fields' => [
            ['name' => 'cv', 'label' => ['ar' => 'سيرتك'], 'type' => 'file', 'required' => true],
        ],
    ]));

    tenantPost($tenant, '/forms/'.$form->key, [
        'data' => ['cv' => UploadedFile::fake()->image('cv.png', 40, 40)],
    ])->assertRedirect();

    $tenant->run(function (): void {
        $stored = FormSubmission::firstOrFail()->data['cv'];

        expect($stored)->toHaveKey('media_id')
            ->and(Media::find($stored['media_id']))->not->toBeNull();
    });
});

it('يرفض ملف SVG يحمل كوداً قابلاً للتنفيذ', function (): void {
    $tenant = provision();
    Storage::fake('public');

    $tenant->run(function (): void {
        $path = tempnam(sys_get_temp_dir(), 'svg').'.svg';
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        expect(fn () => app(StoreMedia::class)->handle(
            new UploadedFile($path, 'logo.svg', 'image/svg+xml', null, true),
        ))->toThrow(RuntimeException::class);

        @unlink($path);
    });
});

it('يرفض ملف SVG يحمل مُعالِج حدث', function (): void {
    $tenant = provision();
    Storage::fake('public');

    $tenant->run(function (): void {
        $path = tempnam(sys_get_temp_dir(), 'svg').'.svg';
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg" onload="steal()"><rect/></svg>');

        expect(fn () => app(StoreMedia::class)->handle(
            new UploadedFile($path, 'logo.svg', 'image/svg+xml', null, true),
        ))->toThrow(RuntimeException::class);

        @unlink($path);
    });
});

it('يرفض رفع نوع ملف خارج القائمة المسموحة', function (): void {
    $tenant = provision();
    Storage::fake('public');

    $tenant->run(function (): void {
        expect(fn () => app(StoreMedia::class)->handle(
            UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
        ))->toThrow(RuntimeException::class);
    });
});

it('لا يخزّن الملف نفسه مرتين', function (): void {
    $tenant = provision();
    Storage::fake('public');

    $tenant->run(function (): void {
        $file = UploadedFile::fake()->image('logo.png', 40, 40);
        $copy = clone $file;

        $first = app(StoreMedia::class)->handle($file);
        $second = app(StoreMedia::class)->handle($copy);

        expect($second->getKey())->toBe($first->getKey())
            ->and(Media::count())->toBe(1);
    });
});

it('لا يربط التذييل صفحة إلزامية ما لم تُنشر', function (): void {
    $tenant = provision();

    // تُنشأ مسودّةً ليحرّرها المشترك: ربطها قبل النشر رابط ميت
    tenantGet($tenant, '/courses')->assertOk()->assertDontSee('/about', false);

    $tenant->run(fn () => Page::where('system_key', 'about')
        ->update(['status' => 'published', 'published_at' => now()]));

    tenantGet($tenant, '/courses')->assertOk()->assertSee('/about', false);
});
