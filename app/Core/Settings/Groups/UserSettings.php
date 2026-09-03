<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Admin\Fields\MultiSelectField;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\PasswordField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Settings\SettingsGroup;

final class UserSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return __('المستخدمون والحسابات');
    }

    public function icon(): string
    {
        return '👤';
    }

    public function description(): ?string
    {
        return __('من يسجّل، وكيف يُوثَّق، وكيف يدخل.');
    }

    public function sections(): array
    {
        return [
            Section::make(__('التسجيل'))->fields([
                SwitchField::make('registration_open')->label(__('التسجيل مفتوح'))->default(true),
                SwitchField::make('verify_email')->label(__('تفعيل البريد إلزامي'))->default(true),
                SwitchField::make('verify_phone')->label(__('تفعيل الهاتف برمز OTP'))->default(false),
                SwitchField::make('terms_required')->label(__('الموافقة على الشروط إلزامية'))->default(true),
                SwitchField::make('instructor_approval')->label(__('اعتماد المدرّس يدوياً قبل النشر'))->default(true),
                SwitchField::make('self_delete')->label(__('السماح للمستخدم بحذف حسابه'))->default(true)
                    ->hint(__('حق قانوني في كثير من الدول — يُنفَّذ بحذف مؤجّل قابل للتراجع.')),
                TextField::make('default_avatar')->label(__('الصورة الرمزية الافتراضية'))->url()->half(),
            ]),

            Section::make(__('كلمة المرور والجلسات'))->fields([
                NumberField::make('password_min')->label(__('أقل طول لكلمة المرور'))->range(8, 64)->half()->default(8),
                MultiSelectField::make('password_requires')->label(__('تعقيد كلمة المرور'))
                    ->options([
                        'mixed' => __('حرف كبير وصغير'),
                        'numbers' => __('رقم'),
                        'symbols' => __('رمز'),
                        'uncompromised' => __('غير مسرّبة في اختراق معروف'),
                    ])->default(['uncompromised']),
                NumberField::make('password_expires_days')->label(__('انتهاء كلمة المرور'))->suffix(__('يوم'))
                    ->range(0, 3650)->half()->default(0)->hint(__('صفر يعني بلا انتهاء.')),
                NumberField::make('session_lifetime')->label(__('مهلة الجلسة'))->suffix(__('دقيقة'))
                    ->range(5, 43200)->half()->default(120),
                NumberField::make('max_sessions')->label(__('حد الجلسات المتزامنة'))->range(0, 20)->half()->default(0)
                    ->hint(__('صفر يعني بلا حد.')),
                NumberField::make('lockout_attempts')->label(__('الحظر بعد محاولات فاشلة'))->range(0, 20)->half()->default(5),
            ]),

            Section::make(__('التوثيق بخطوتين'))->fields([
                SelectField::make('two_factor')->label(__('التوثيق بخطوتين'))->half()
                    ->options([
                        'off' => __('معطّل'),
                        'optional' => __('اختياري للجميع'),
                        'staff' => __('إجباري على الإدارة والمدرّسين'),
                        'all' => __('إجباري على الجميع'),
                    ])->default('optional'),
                SwitchField::make('passkeys')->label(__('مفاتيح المرور (Passkeys)'))->default(true),
            ]),

            Section::make(__('التسجيل الاجتماعي'))
                ->description(__('كل شبكة تحتاج مفتاحاً وسرّاً من لوحة مطوّريها. السرّ يُخزَّن مشفّراً.'))
                ->fields(array_merge(...array_map(
                    fn (array $p): array => [
                        SwitchField::make($p[0].'_enabled')->label(__('تفعيل :net', ['net' => $p[1]])),
                        TextField::make($p[0].'_client_id')->label(__(':net — المعرّف', ['net' => $p[1]]))->half(),
                        PasswordField::make($p[0].'_client_secret')->label(__(':net — السرّ', ['net' => $p[1]]))->half(),
                    ],
                    [
                        ['google', 'Google'], ['facebook', 'Facebook'], ['apple', 'Apple'],
                        ['x', 'X'], ['linkedin', 'LinkedIn'],
                    ],
                ))),
        ];
    }
}
