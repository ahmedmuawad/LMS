<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Access\Ability;
use App\Core\Admin\Fields\ImageField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextareaField;
use App\Core\Admin\Fields\TextField;
use App\Core\Settings\SettingsGroup;

/**
 * محتوى الصفحة الرئيسية — يحرّره صاحب المنصة بنفسه.
 *
 * كانت صفحة المشترك الرئيسية لافتةً ثابتة: «منصّتك جاهزة. ابدأ
 * بإضافة أول كورس». يراها طلّابه وأولياء أمورهم فيظنّون الموقع
 * قيد الإنشاء — وهي أول ما يرونه.
 *
 * ## لماذا إعدادات لا باني صفحات
 *
 * باني الصفحات ميزةٌ في باقاتٍ بعينها، والصفحة الرئيسية يحتاجها
 * كل مشترك من أول يوم. وهو أيضاً أداةُ من يريد تصميماً؛ وأكثر
 * المدرّسين يريدون كتابة اسمهم ووصفهم ورفع صورة، لا ترتيب أعمدة.
 *
 * فمن أراد التحكّم الكامل فتح باني الصفحات، ومن أراد صفحةً تعمل
 * ملأ سبعة حقول هنا. والقوالب لكل نمط تملأ الافتراضات، فحتى من
 * لم يفتح هذه الشاشة لا يرى لافتة «قيد الإنشاء».
 */
final class HomepageSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'homepage';
    }

    public function label(): string
    {
        return __('الصفحة الرئيسية');
    }

    public function icon(): string
    {
        return '◫';
    }

    public function ability(): string
    {
        return Ability::SETTINGS_MANAGE;
    }

    public function description(): ?string
    {
        return __('أول ما يراه طلابك وأولياء أمورهم. اتركه فارغاً ليُملأ بما يناسب نمط منصّتك.');
    }

    public function sections(): array
    {
        return [
            Section::make(__('الواجهة'))
                ->description(__('العنوان الأول هو وعدك للزائر في سطر. والوصف يشرحه في سطرين — وما زاد لا يُقرأ.'))
                ->fields([
                    TextField::make('headline')->label(__('العنوان الرئيسي'))
                        ->placeholder(__('مثال: رياضيات الثانوية العامة مع أ. هبة معوض')),

                    TextareaField::make('subheadline')->label(__('الوصف'))->long()
                        ->placeholder(__('مثال: مجموعات محدودة العدد، متابعة أسبوعية، وحلّ امتحانات سابقة.')),

                    ImageField::make('hero_image')->label(__('صورة الواجهة'))
                        ->hint(__('صورتك أو شعارك أو صورة من حصّة. تُعرض بجوار العنوان.')),

                    TextField::make('cta_label')->label(__('نصّ زرّ الدعوة'))->half()
                        ->placeholder(__('مثال: احجز مكانك')),

                    TextField::make('cta_url')->label(__('رابط الزرّ'))->half()
                        ->placeholder(__('اتركه فارغاً ليقود إلى الكورسات')),
                ]),

            Section::make(__('لماذا أنت'))
                ->description(__('ثلاث نقاط تكفي. الرابعة تُضعف الثلاث.'))
                ->fields([
                    TextField::make('point_1')->label(__('النقطة الأولى')),
                    TextField::make('point_2')->label(__('النقطة الثانية')),
                    TextField::make('point_3')->label(__('النقطة الثالثة')),
                ]),

            Section::make(__('عنك'))->fields([
                TextareaField::make('about')->label(__('نبذة'))->long()
                    ->hint(__('خبرتك ومنهجك — يقرؤها وليّ الأمر قبل أن يقرّر.')),
                TextField::make('phone')->label(__('هاتف التواصل'))->half(),
                TextField::make('whatsapp')->label(__('واتساب'))->half()
                    ->hint(__('بصيغة دولية بلا صفر: 201012345678')),
            ]),

            Section::make(__('ما يُعرض'))
                ->description(__('ما لا يوجد لا يُعرض: قسمُ كورساتٍ فارغ أسوأ من غيابه.'))
                ->fields([
                    SwitchField::make('show_courses')->label(__('عرض الكورسات'))->default(true),
                    SwitchField::make('show_groups')->label(__('عرض المجموعات المفتوحة'))->default(true),
                    SwitchField::make('show_services')->label(__('عرض الخدمات'))->default(true),
                    SwitchField::make('show_posts')->label(__('عرض آخر المقالات'))->default(false),
                ]),
        ];
    }
}
