<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Settings\SettingsGroup;

final class CommerceSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'commerce';
    }

    public function label(): string
    {
        return __('التجارة');
    }

    public function icon(): string
    {
        return '🛒';
    }

    public function module(): ?string
    {
        return 'commerce';
    }

    public function description(): ?string
    {
        return __('السلة والطلبات والفواتير والاسترداد والشحن.');
    }

    public function sections(): array
    {
        return [
            Section::make(__('المتجر'))->fields([
                SwitchField::make('store_open')->label(__('المتجر مفتوح'))->default(true),
                NumberField::make('minimum_order')->label(__('الحد الأدنى للطلب'))->range(0, 1000000)->half()->default(0),
                SwitchField::make('guest_checkout')->label(__('الشراء كضيف'))->default(false)
                    ->hint(__('الكورس يحتاج حساباً ليُسلَّم — الضيف يصلح للمنتجات المادية.')),
                SwitchField::make('one_click_courses')->label(__('شراء الكورس بنقرة واحدة'))->default(true),
                SelectField::make('cart_style')->label(__('شكل السلة'))->half()
                    ->options(['page' => __('صفحة سلة'), 'drawer' => __('سلة جانبية')])->default('drawer'),
                NumberField::make('cart_lifetime_days')->label(__('مدة الاحتفاظ بالسلة'))->suffix(__('يوم'))
                    ->range(1, 90)->half()->default(30),
            ]),

            Section::make(__('السلة المتروكة'))->fields([
                SwitchField::make('abandoned_enabled')->label(__('تذكير السلة المتروكة'))->default(true),
                NumberField::make('abandoned_after_hours')->label(__('أول تذكير بعد'))->suffix(__('ساعة'))
                    ->range(1, 168)->half()->default(4),
                NumberField::make('abandoned_messages')->label(__('عدد الرسائل'))->range(1, 5)->half()->default(2),
            ]),

            Section::make(__('الطلبات'))->fields([
                TextField::make('order_prefix')->label(__('بادئة رقم الطلب'))->half()->default('ORD-'),
                NumberField::make('order_start')->label(__('بداية التسلسل'))->range(1, 1000000)->half()->default(1000),
                SelectField::make('default_status')->label(__('الحالة الافتراضية'))->half()
                    ->options([
                        'pending' => __('بانتظار الدفع'),
                        'processing' => __('قيد المعالجة'),
                        'completed' => __('مكتمل'),
                    ])->default('pending'),
                NumberField::make('auto_cancel_hours')->label(__('الإلغاء التلقائي بعد'))->suffix(__('ساعة'))
                    ->range(0, 720)->half()->default(48)->hint(__('صفر يعني بلا إلغاء تلقائي.')),
            ]),

            Section::make(__('الاسترداد'))->fields([
                SwitchField::make('refunds_enabled')->label(__('قبول طلبات الاسترداد'))->default(true),
                NumberField::make('refund_days')->label(__('مهلة الاسترداد'))->suffix(__('يوم'))->range(0, 365)->half()->default(14),
                SelectField::make('refund_mode')->label(__('طريقة المعالجة'))->half()
                    ->options(['manual' => __('مراجعة يدوية'), 'auto' => __('تلقائي داخل المهلة')])->default('manual'),
                NumberField::make('refund_max_progress')->label(__('أقصى نسبة مشاهدة تسمح بالاسترداد'))->suffix('%')
                    ->range(0, 100)->half()->default(20),
                TranslatableField::make('refund_policy')->label(__('نصّ سياسة الاسترداد'))->long(),
            ]),

            Section::make(__('الفاتورة'))->fields([
                SwitchField::make('invoice_pdf')->label(__('إصدار فاتورة PDF'))->default(true),
                TextField::make('invoice_logo')->label(__('شعار الفاتورة'))->url()->half(),
                TextField::make('invoice_prefix')->label(__('بادئة رقم الفاتورة'))->half()->default('INV-'),
                TranslatableField::make('invoice_footer')->label(__('تذييل الفاتورة'))->long(),
            ]),

            Section::make(__('المخزون والشحن'))->fields([
                SwitchField::make('track_stock')->label(__('تتبّع المخزون'))->default(true),
                NumberField::make('low_stock_threshold')->label(__('تنبيه عند بلوغ'))->suffix(__('قطعة'))
                    ->range(0, 10000)->half()->default(5),
                SwitchField::make('backorders')->label(__('البيع عند نفاد المخزون'))->default(false),
                SwitchField::make('shipping_enabled')->label(__('تفعيل الشحن'))->default(false),
                NumberField::make('free_shipping_over')->label(__('شحن مجاني فوق'))->range(0, 1000000)->half()->default(0)
                    ->hint(__('صفر يعني بلا شحن مجاني.')),
            ]),
        ];
    }
}
