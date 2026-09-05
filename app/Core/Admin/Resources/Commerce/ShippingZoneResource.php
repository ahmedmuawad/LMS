<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Commerce;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextareaField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Resource;
use App\Modules\Commerce\Models\ShippingZone;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * مناطق الشحن.
 *
 * كان الشحن رقماً ثابتاً واحداً للعالم: الشحنة إلى الرياض بثمن
 * الشحنة إلى المعادي. فيخسر المشترك في البعيدة أو يُغالي في القريبة.
 */
final class ShippingZoneResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::PRODUCTS_MANAGE;
    }

    public function model(): string
    {
        return ShippingZone::class;
    }

    public function label(): string
    {
        return __('مناطق الشحن');
    }

    public function singularLabel(): string
    {
        return __('منطقة شحن');
    }

    public function query(): Builder
    {
        return ShippingZone::query()->orderBy('sort_order');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->label(__('المنطقة'))->searchable(),

            TextColumn::make('countries')->label(__('الدول'))
                ->using(fn ($value): string => in_array('*', (array) $value, true)
                    ? __('بقيّة العالم')
                    : implode(' · ', (array) $value)),

            TextColumn::make('rate_minor')->label(__('السعر'))->mono()->align('end')
                ->using(fn ($value): string => number_format(((int) $value) / 100, 2)),

            TextColumn::make('free_over_minor')->label(__('مجاناً فوق'))->mono()->align('end')
                ->using(fn ($value): string => ((int) $value) > 0
                    ? number_format(((int) $value) / 100, 2)
                    : '—'),

            BadgeColumn::make('is_active')->label(__('الحالة'))
                ->using(fn ($value): string => $value ? __('فعّالة') : __('موقوفة')),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('المنطقة'))
                ->description(__('الدول التي لها السعر نفسه — والأولى ترتيباً هي التي تُطبَّق.'))
                ->fields([
                    TranslatableField::make('name')->label(__('اسم المنطقة'))
                        ->hint(__('«الخليج» أو «مصر» أو «بقيّة العالم».')),

                    /*
                     | الدول تُكتب رموزاً بسطرٍ أو فاصلة.
                     |
                     | ومنتقي مئتَي دولة يجعل إنشاء منطقةٍ عملَ ربع
                     | ساعة؛ والرمز الثنائي معروفٌ لمن يشحن أصلاً.
                     */
                    TextareaField::make('countries')->label(__('رموز الدول'))
                        ->hint(__('EG SA AE — رمزٌ لكل دولة، أو نجمة (*) لبقيّة العالم.')),

                    NumberField::make('rate_minor')->label(__('سعر الشحن'))->half()
                        ->hint(__('بالقروش/الهللات — ٥٠٠٠ تعني ٥٠٫٠٠.')),

                    NumberField::make('free_over_minor')->label(__('شحن مجاني فوق'))->half()
                        ->hint(__('صفرٌ يعني لا شحن مجاني. وحدُّ مصر ليس حدَّ السعودية.')),

                    /*
                     | الترتيب يحسم التعارض.
                     |
                     | منطقةٌ تحمل `*` تبتلع كل ما بعدها، فتُوضع أخيراً
                     | برقمٍ كبير — وإلا لم تُطبَّق منطقةٌ واحدة غيرها.
                     */
                    NumberField::make('sort_order')->label(__('الترتيب'))->half()->default(0)
                        ->hint(__('الأصغر يُفحص أولاً. اجعل «بقيّة العالم» الأكبر رقماً.')),

                    SwitchField::make('is_active')->label(__('فعّالة'))->default(true),
                ]),
        ];
    }

    /**
     * الرموز تُقرأ سطراً أو فاصلة، وتُحفظ قائمة.
     *
     * ومن يكتب «eg, sa» يقصد ما يقصده من يكتب «EG SA» — والفرق
     * حرفٌ كبير لا يستحقّ رسالة خطأ. والمقارنة في `forCountry`
     * بالكبير، فتخزينُ الصغير يجعل المنطقة لا تُطابَق أبداً.
     */
    public function fillable(array $input, string $context): array
    {
        $data = parent::fillable($input, $context);

        if (! array_key_exists('countries', $data)) {
            return $data;
        }

        $raw = is_array($data['countries'])
            ? implode(' ', $data['countries'])
            : (string) $data['countries'];

        $data['countries'] = collect(preg_split('/[\s,;]+/', $raw) ?: [])
            ->map(fn (string $code): string => mb_strtoupper(trim($code)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $data;
    }
}
