<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Modules\Center\Models\Group;
use App\Modules\Content\Models\Post;
use App\Modules\Lms\Models\Course;
use App\Modules\Services\Models\Service;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * الصفحة الرئيسية للمشترك.
 *
 * كانت لافتةً ثابتة: «منصّتك جاهزة. ابدأ بإضافة أول كورس» — يراها
 * طلّابه وأولياء أمورهم فيظنّون الموقع قيد الإنشاء، وهي أول ما
 * يرونه وأكثر ما يُشارَك رابطه.
 *
 * ## القوالب تملأ ما لم يُكتب
 *
 * أكثر المدرّسين لا يفتحون شاشة إعدادات قبل أن يُرسلوا الرابط
 * لطلابهم. فالنمط يملأ الافتراضات: مدرّس المجموعات يُعرض له نصٌّ
 * عن مجموعاته ومواعيدها، وصاحب الأكاديمية الأونلاين نصٌّ عن
 * كورساته. ومن كتب نصّه غلب القالب.
 */
final class HomeController
{
    public function __invoke(): View
    {
        $mode = (string) (tenant('platform_mode') ?? 'solo');
        $defaults = $this->defaultsFor($mode);

        return view('tenant.home', [
            'headline' => setting('homepage.headline') ?: $defaults['headline'],
            'subheadline' => setting('homepage.subheadline') ?: $defaults['subheadline'],
            'heroImage' => setting('homepage.hero_image'),
            'ctaLabel' => setting('homepage.cta_label') ?: $defaults['cta'],
            'ctaUrl' => setting('homepage.cta_url') ?: $defaults['cta_url'],

            'points' => array_values(array_filter([
                setting('homepage.point_1') ?: ($defaults['points'][0] ?? null),
                setting('homepage.point_2') ?: ($defaults['points'][1] ?? null),
                setting('homepage.point_3') ?: ($defaults['points'][2] ?? null),
            ])),

            'about' => setting('homepage.about'),
            'phone' => setting('homepage.phone'),
            'whatsapp' => setting('homepage.whatsapp'),

            // ما لا يوجد لا يُعرض: قسمٌ فارغ أسوأ من غيابه
            'courses' => $this->courses(),
            'groups' => $this->groups(),
            'services' => $this->services(),
            'posts' => $this->posts(),
        ]);
    }

    /** @return array{headline:string, subheadline:string, cta:string, cta_url:string, points:list<string>} */
    private function defaultsFor(string $mode): array
    {
        $name = site_name();

        return match ($mode) {
            'teacher' => [
                'headline' => __(':name — مجموعات محدودة العدد', ['name' => $name]),
                'subheadline' => __('حصص منتظمة، متابعة أسبوعية، وتقرير شهري لوليّ الأمر عن حضور ابنه ومستواه.'),
                'cta' => __('المجموعات المفتوحة'),
                'cta_url' => url('/courses'),
                'points' => [
                    __('مجموعات صغيرة — كل طالب يُسأل ويُتابَع'),
                    __('حضور مسجَّل، ووليّ الأمر يعرف أولاً بأول'),
                    __('مذكّرات وواجبات داخل المنصة، تُقرأ ولا تضيع'),
                ],
            ],

            'center' => [
                'headline' => __(':name', ['name' => $name]),
                'subheadline' => __('مراحل وصفوف ومواد، بجدول حصص واضح وحضور مسجَّل وأقساط منظَّمة.'),
                'cta' => __('المجموعات والمواعيد'),
                'cta_url' => url('/courses'),
                'points' => [
                    __('جدول حصص لكل صف، بلا تعارض في القاعات'),
                    __('حضور وغياب يصل وليّ الأمر يوم وقوعه'),
                    __('أقساط ومتأخّرات بكشفٍ واضح لكل طالب'),
                ],
            ],

            'marketplace' => [
                'headline' => __('تعلّم مع أفضل المدرّسين في :name', ['name' => $name]),
                'subheadline' => __('كورسات يقدّمها مدرّسون مختصّون — تشتري مرة وتشاهد متى شئت.'),
                'cta' => __('تصفّح الكورسات'),
                'cta_url' => url('/courses'),
                'points' => [
                    __('مدرّسون مختصّون، وتقييمات من طلبة حقيقيين'),
                    __('شاهد متى شئت ومن أي جهاز'),
                    __('شهادة عند الإتمام بكود يتحقّق منه أي أحد'),
                ],
            ],

            'hybrid' => [
                'headline' => __(':name — أونلاين وحضورياً', ['name' => $name]),
                'subheadline' => __('كورسات مسجّلة، ومجموعات حضورية، وخدمات ومنتجات — في مكان واحد.'),
                'cta' => __('ابدأ من هنا'),
                'cta_url' => url('/courses'),
                'points' => [
                    __('تعلّم أونلاين أو احضر في المركز — أو الاثنين'),
                    __('متابعة موحّدة لتقدّمك أينما تعلّمت'),
                    __('شهادات معتمَدة بكود تحقّق'),
                ],
            ],

            default => [
                'headline' => __('كورسات :name', ['name' => $name]),
                'subheadline' => __('اشترِ الكورس مرة، وشاهده متى شئت، ومن أي جهاز.'),
                'cta' => __('تصفّح الكورسات'),
                'cta_url' => url('/courses'),
                'points' => [
                    __('محتوى مرتَّب خطوة بخطوة'),
                    __('اختبارات وواجبات تُصحَّح وتُراجَع'),
                    __('شهادة عند الإتمام بكود يتحقّق منه أي أحد'),
                ],
            ],
        };
    }

    private function courses()
    {
        if (! module_enabled('lms') || ! (bool) setting('homepage.show_courses', true)) {
            return collect();
        }

        /*
         | العلاقات تُحمَّل مع الاستعلام لا مع كل بطاقة.
         |
         | بطاقة الكورس تعرض مدرّسه وتصنيفه؛ وستّ بطاقاتٍ بلا تحميلٍ
         | مسبق تعني اثني عشر استعلاماً زائداً في الصفحة الأولى التي
         | يراها الزائر — وهي أهمّ صفحةٍ في الأداء.
         */
        return Course::where('status', 'published')->where('visibility', 'public')
            ->with(['instructor.user', 'category'])
            ->latest('published_at')->limit(6)->get();
    }

    /**
     * المجموعات المفتوحة — هي «منتج» مدرّس المجموعات.
     *
     * وهي أهمّ ما يبحث عنه وليّ الأمر: أي مجموعة تناسب ابنه ومتى
     * موعدها. وكانت لا تُعرض في أي صفحة عامة إطلاقاً.
     */
    private function groups()
    {
        if (! module_enabled('center') || ! (bool) setting('homepage.show_groups', true)) {
            return collect();
        }

        if (! Schema::hasTable('center_groups')) {
            return collect();
        }

        return Group::where('status', 'open')
            ->with(['subject', 'grade', 'schedules'])
            ->limit(6)->get();
    }

    private function services()
    {
        if (! module_enabled('services') || ! (bool) setting('homepage.show_services', true)) {
            return collect();
        }

        return Service::where('status', 'published')->limit(4)->get();
    }

    private function posts()
    {
        if (! module_enabled('blog') || ! (bool) setting('homepage.show_posts', false)) {
            return collect();
        }

        return Post::where('status', 'published')->latest('published_at')->limit(3)->get();
    }
}
