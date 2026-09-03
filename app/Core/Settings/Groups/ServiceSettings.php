<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Settings\SettingsGroup;

final class ServiceSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'services';
    }

    public function label(): string
    {
        return __('الخدمات والحجوزات');
    }

    public function icon(): string
    {
        return '◇';
    }

    public function module(): ?string
    {
        return 'services';
    }

    public function description(): ?string
    {
        return __('سلوك التقويم والحجز والتأكيد والإلغاء.');
    }

    public function sections(): array
    {
        return [
            Section::make(__('التقويم'))
                ->description(__('لا نعرض موعداً لا يمكن حجزه: عرضه ثم رفضه عند الدفع أسوأ ما يحدث في صفحة حجز.'))
                ->fields([
                    NumberField::make('calendar_days')->label(__('مدى التقويم'))->suffix(__('يوم'))
                        ->range(1, 120)->half()->default(14),
                    SelectField::make('week_start')->label(__('بداية الأسبوع'))->half()
                        ->options([
                            '6' => __('السبت'), '0' => __('الأحد'), '1' => __('الاثنين'),
                        ])->default('6'),
                    SwitchField::make('show_provider')->label(__('إظهار اسم مقدّم الخدمة'))->default(true),
                    SwitchField::make('hide_full_days')->label(__('إخفاء الأيام المكتملة'))->default(true),
                ]),

            Section::make(__('الحجز'))->fields([
                SelectField::make('confirmation')->label(__('التأكيد'))->half()
                    ->options([
                        'manual' => __('يدوي — يراجعه فريقك'),
                        'auto' => __('تلقائي فور الحجز'),
                        'payment' => __('بعد الدفع'),
                    ])->default('manual'),
                SwitchField::make('guest_booking')->label(__('السماح بحجز الضيوف'))->default(true)
                    ->hint(__('بلا حساب — يصله رابط الحجز على بريده.')),
                SwitchField::make('require_phone')->label(__('رقم الهاتف إلزامي'))->default(false),
                NumberField::make('max_open_per_user')->label(__('أقصى حجوزات مفتوحة للعميل'))
                    ->range(0, 100)->half()->default(0)->hint(__('صفر يعني بلا حد.')),
            ]),

            Section::make(__('الإلغاء وعدم الحضور'))->fields([
                SwitchField::make('allow_cancel')->label(__('السماح للعميل بالإلغاء'))->default(true),
                SwitchField::make('allow_reschedule')->label(__('السماح بتغيير الموعد'))->default(true),
                NumberField::make('no_show_after_minutes')->label(__('يُعدّ غياباً بعد'))->suffix(__('دقيقة'))
                    ->range(0, 240)->half()->default(15),
            ]),

            Section::make(__('الاجتماع'))->fields([
                SelectField::make('meeting_provider')->label(__('مزوّد الاجتماع'))->half()
                    ->options([
                        'manual' => __('رابط يدوي'),
                        'zoom' => 'Zoom',
                        'meet' => 'Google Meet',
                        'bbb' => 'BigBlueButton',
                    ])->default('manual'),
                TextField::make('default_meeting_url')->label(__('رابط ثابت للاجتماع'))->url()
                    ->hint(__('يُستخدم حين لا يولّد المزوّد رابطاً لكل حجز.')),
                SwitchField::make('send_reminders')->label(__('تذكير قبل الموعد'))->default(true),
                NumberField::make('reminder_hours')->label(__('التذكير قبل'))->suffix(__('ساعة'))
                    ->range(1, 168)->half()->default(24),
            ]),
        ];
    }
}
