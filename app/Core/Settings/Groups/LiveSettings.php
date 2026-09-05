<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Access\Ability;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Settings\SettingsGroup;

/**
 * إعدادات الحصص المباشرة.
 *
 * الخيارات هنا قليلة عمداً: المدرّس لا يريد ضبط مزوّد فيديو، يريد
 * زرّاً يفتح حصّته. فالافتراضات تعمل بلا لمس، وما يُعرض هو ما قد
 * يحتاج تغييره فعلاً.
 */
final class LiveSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'live';
    }

    public function label(): string
    {
        return __('الحصص المباشرة');
    }

    public function icon(): string
    {
        return '◉';
    }

    public function module(): ?string
    {
        return 'live';
    }

    public function ability(): string
    {
        return Ability::SETTINGS_MANAGE;
    }

    public function description(): ?string
    {
        return __('رابط الحصة يُنشأ تلقائياً لكل موعد — بلا حساب ولا مفاتيح.');
    }

    public function sections(): array
    {
        $providers = collect(config('live.providers', []))
            ->map(fn (array $p): string => ($p['name'][app()->getLocale()] ?? $p['name']['ar'])
                .($p['needs_keys'] ? ' — '.__('قريباً') : ''))
            ->all();

        return [
            Section::make(__('المزوّد'))
                ->description(__('Jitsi يعمل فوراً بلا حساب. أمّا Zoom وMeet وBigBlueButton فتحتاج ربط حساب، وهي قيد البناء — واختيارها الآن يعني الرجوع إلى الرابط اليدوي.'))
                ->fields([
                    SelectField::make('provider')->label(__('مزوّد الحصص'))->half()
                        ->options($providers)->default('jitsi'),

                    SwitchField::make('auto_rooms')->label(__('إنشاء غرفة لكل حصة تلقائياً'))->default(true)
                        ->hint(__('اسم الغرفة سرّيّ ولا يُخمَّن، وثابتٌ لكل حصة فيجد الطالب مدرّسه فيه.')),

                    TextField::make('jitsi_domain')->label(__('خادم Jitsi'))->half()
                        ->placeholder('meet.jit.si')
                        ->hint(__('اتركه فارغاً لاستعمال الخادم العام. ضع خادمك هنا إن كان لديك واحد.')),
                ]),

            Section::make(__('نافذة الدخول'))
                ->description(__('الرابط الظاهر طوال الأسبوع يُنسَخ ويُتداول خارج المشتركين؛ والظاهر في اللحظة وحدها يُفوّت من تأخّر دقيقة على اتصاله.'))
                ->fields([
                    NumberField::make('opens_before')->label(__('يُفتح قبل الموعد بـ'))->suffix(__('دقيقة'))
                        ->range(0, 180)->half()->default(30),
                    NumberField::make('closes_after')->label(__('يُغلق بعد الانتهاء بـ'))->suffix(__('دقيقة'))
                        ->range(0, 180)->half()->default(30),
                ]),

            Section::make(__('التذكير'))
                ->description(__('الغياب سببه النسيان لا الرفض.'))
                ->fields([
                    SwitchField::make('remind')->label(__('تذكير الطلبة قبل الحصة'))->default(true),
                    NumberField::make('remind_before')->label(__('قبل الموعد بـ'))->suffix(__('دقيقة'))
                        ->range(5, 1440)->half()->default(60),
                ]),
        ];
    }
}
