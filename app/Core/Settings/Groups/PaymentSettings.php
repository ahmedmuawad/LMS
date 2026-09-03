<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Admin\Fields\Field;
use App\Core\Admin\Fields\MultiSelectField;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\PasswordField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextareaField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Settings\SettingsGroup;

/**
 * ADR-002 — كل بوابة بلجن مستقل بإعداداته.
 * الحقول المشتركة تُولَّد لكل بوابة، والخاصة تُضاف من تعريف البوابة نفسها.
 */
final class PaymentSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'payments';
    }

    public function label(): string
    {
        return __('المدفوعات');
    }

    public function icon(): string
    {
        return '💳';
    }

    public function description(): ?string
    {
        return __('بوابات الدفع ومفاتيحها وحدودها. المفاتيح تُخزَّن مشفّرة.');
    }

    public function sections(): array
    {
        return array_map(
            fn (array $gateway): Section => $this->gatewaySection($gateway),
            config('payments.gateways', []),
        );
    }

    private function gatewaySection(array $gateway): Section
    {
        $key = $gateway['key'];

        /** @var list<Field> $fields */
        $fields = [
            SwitchField::make($key.'_enabled')->label(__('تفعيل'))->default(false),
            SelectField::make($key.'_mode')->label(__('الوضع'))->half()
                ->options(['test' => __('تجريبي'), 'live' => __('حقيقي')])->default('test'),
            TranslatableField::make($key.'_title')->label(__('الاسم الظاهر للعميل')),
            TranslatableField::make($key.'_description')->label(__('الوصف الظاهر للعميل'))->long(),
            TextField::make($key.'_icon')->label(__('الأيقونة'))->url()->half(),
            NumberField::make($key.'_position')->label(__('ترتيب الظهور'))->range(0, 100)->half()->default(0),
        ];

        foreach ($gateway['credentials'] ?? [] as $name => $label) {
            $fields[] = str_contains($name, 'secret') || str_contains($name, 'key') || str_contains($name, 'password')
                ? PasswordField::make($key.'_'.$name)->label(__($label))->half()
                : TextField::make($key.'_'.$name)->label(__($label))->half();
        }

        $fields[] = MultiSelectField::make($key.'_currencies')->label(__('العملات المدعومة'))
            ->options(collect($gateway['currencies'] ?? [])->mapWithKeys(fn ($c): array => [$c => $c])->all())
            ->columns(3)->default($gateway['currencies'] ?? []);

        $fields[] = MultiSelectField::make($key.'_countries')->label(__('مقصورة على دول'))
            ->options(collect($gateway['countries'] ?? [])->mapWithKeys(fn ($c): array => [$c => $c])->all())
            ->columns(3)->hint(__('اتركها فارغة لتعمل في كل الدول.'));

        $fields[] = NumberField::make($key.'_min')->label(__('أقل مبلغ'))->range(0, 10000000)->half()->default(0);
        $fields[] = NumberField::make($key.'_max')->label(__('أكبر مبلغ'))->range(0, 10000000)->half()->default(0)
            ->hint(__('صفر يعني بلا حد.'));
        $fields[] = NumberField::make($key.'_fee_percent')->label(__('رسوم إضافية'))->suffix('%')->range(0, 100)->half()->default(0);
        $fields[] = NumberField::make($key.'_fee_fixed')->label(__('رسوم ثابتة'))->range(0, 100000)->half()->default(0);

        if (($gateway['manual'] ?? false) === true) {
            $fields[] = TextareaField::make($key.'_instructions')->label(__('تعليمات الدفع للعميل'))
                ->hint(__('تظهر بعد الطلب — بيانات الحساب أو خطوات التحويل.'));
            $fields[] = SwitchField::make($key.'_require_receipt')->label(__('طلب رفع إيصال'))->default(true);
        }

        $section = Section::make(__($gateway['label']));

        if (filled($gateway['note'] ?? null)) {
            $section->description(__($gateway['note']));
        }

        return $section->fields($fields);
    }
}
