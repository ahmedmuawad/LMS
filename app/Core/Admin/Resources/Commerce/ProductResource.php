<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Commerce;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\NumberColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\ImageField;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Commerce\Models\Product;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class ProductResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::PRODUCTS_MANAGE;
    }

    public function model(): string
    {
        return Product::class;
    }

    public function label(): string
    {
        return __('المنتجات');
    }

    public function singularLabel(): string
    {
        return __('منتج');
    }

    public function query(): Builder
    {
        return Product::query();
    }

    public function columns(): array
    {
        return [
            TextColumn::make('title')->label(__('المنتج'))->searchable()->wrap()->description('sku'),

            BadgeColumn::make('type')->label(__('النوع'))->sortable()
                ->tones(['course' => 'primary', 'physical' => 'accent'])
                ->labels(array_map(fn (string $l): string => __($l), Product::TYPES)),

            TextColumn::make('price_minor')->label(__('السعر'))->mono()->align('end')->sortable()
                ->using(fn ($v, Product $p): string => $p->isFree() ? __('مجاني') : $p->price()->format()),

            TextColumn::make('stock_qty')->label(__('المخزون'))->mono()->align('end')
                ->using(fn ($v, Product $p): string => $p->manage_stock ? (string) $v : __('غير محدود')),

            NumberColumn::make('sales_count')->label(__('المبيعات'))->sortable(),

            BadgeColumn::make('status')->label(__('الحالة'))->sortable()
                ->tones(['published' => 'success', 'draft' => 'neutral', 'archived' => 'neutral'])
                ->labels(['draft' => __('مسودّة'), 'published' => __('منشور'), 'archived' => __('مؤرشف')]),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('type')->label(__('النوع'))
                ->options(array_map(fn (string $l): string => __($l), Product::TYPES)),

            SelectFilter::make('status')->label(__('الحالة'))
                ->options(['draft' => __('مسودّة'), 'published' => __('منشور'), 'archived' => __('مؤرشف')]),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('المنتج'))->fields([
                TranslatableField::make('title')->label(__('الاسم'))->required(),
                TextField::make('slug')->label(__('الرابط'))->required()->half()
                    ->rules(['alpha_dash', 'max:200']),
                SelectField::make('type')->label(__('النوع'))->half()->required()
                    ->options(array_map(fn (string $l): string => __($l), Product::TYPES))
                    ->default('digital'),
                TextField::make('sku')->label(__('كود المنتج'))->half(),
                TranslatableField::make('short_description')->label(__('وصف مختصر'))->long(),
                ImageField::make('cover_path')->label(__('الصورة'))->folder('products')->ratio('1/1')->half(),
            ]),

            Section::make(__('التسعير'))->fields([
                SelectField::make('currency')->label(__('العملة'))->half()->required()
                    ->options(collect((array) setting('currency.enabled', ['EGP']))
                        ->mapWithKeys(fn (string $c): array => [$c => $c])->all())
                    ->default((string) (tenant('currency') ?? 'EGP')),
                NumberField::make('price_minor')->label(__('السعر'))->half()
                    ->money((string) (tenant('currency') ?? 'EGP')),
                NumberField::make('sale_price_minor')->label(__('سعر التخفيض'))->half()
                    ->money((string) (tenant('currency') ?? 'EGP')),
                SwitchField::make('is_taxable')->label(__('خاضع للضريبة'))->default(true),
            ]),

            Section::make(__('المخزون والشحن'))->fields([
                SwitchField::make('manage_stock')->label(__('تتبّع المخزون'))
                    ->hint(__('الكورس والخدمة لا ينفدان — فعّله للمنتجات المادية.')),
                NumberField::make('stock_qty')->label(__('الكمية'))->range(0, 1000000)->half()->default(0),
                SwitchField::make('allow_backorder')->label(__('البيع بعد النفاد')),
                SwitchField::make('requires_shipping')->label(__('يحتاج شحناً')),
                NumberField::make('weight_grams')->label(__('الوزن'))->suffix(__('جرام'))->range(0, 500000)->half(),
            ]),

            Section::make(__('النشر'))->fields([
                SelectField::make('status')->label(__('الحالة'))->half()
                    ->options(['draft' => __('مسودّة'), 'published' => __('منشور'), 'archived' => __('مؤرشف')])
                    ->default('draft'),
                SwitchField::make('featured')->label(__('مميّز في الواجهة')),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا منتجات بعد'),
            'body' => __('كورساتك المنشورة تظهر هنا تلقائياً — وأضف ما عداها يدوياً.'),
        ];
    }
}
