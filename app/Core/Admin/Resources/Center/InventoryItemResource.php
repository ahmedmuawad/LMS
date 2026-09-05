<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Center;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\BooleanColumn;
use App\Core\Admin\Columns\NumberColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextareaField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Center\Models\Branch;
use App\Modules\Center\Models\InventoryItem;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class InventoryItemResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::CENTER_MANAGE;
    }

    public function feature(): ?string
    {
        return 'inventory';
    }

    public function model(): string
    {
        return InventoryItem::class;
    }

    /**
     * الرصيد لا يُكتب في النموذج — يُحرَّك بحركة.
     *
     * رقمٌ يُعدَّل بيدٍ في شاشة الصنف يجعل الجرد لا يُفسَّر: نقص
     * عشرة ولا أحد يعرف أبيعت أم تلفت أم سُرقت. فالإنشاء يفتح شاشة
     * الحركات، وأوّل حركةٍ فيها توريد.
     */
    public function nextStep(Model $record, string $key): ?array
    {
        return [
            'url' => url('/admin/inventory/'.$record->getKey().'/movements'),
            'label' => __('حركات الصنف'),
            'hint' => __('سجّل التوريد الأول هنا — الرصيد يتحرّك بالحركات لا بالكتابة، فيبقى الجرد مفسَّراً.'),
        ];
    }

    public function label(): string
    {
        return __('المخزون');
    }

    public function singularLabel(): string
    {
        return __('صنف');
    }

    public function query(): Builder
    {
        return InventoryItem::query()->with('branch');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->label(__('الصنف'))->searchable()->wrap()->sortable(),

            BadgeColumn::make('kind')->label(__('النوع'))
                ->tones(['tool' => 'accent', 'book' => 'primary'])
                ->labels(array_map(fn (string $l): string => __($l), InventoryItem::KINDS)),

            NumberColumn::make('stock_qty')->label(__('الرصيد'))->sortable(),

            TextColumn::make('reorder_level')->label(__('حالة الرصيد'))
                ->using(fn ($v, InventoryItem $i): string => $i->isLow() ? __('منخفض') : __('كافٍ')),

            TextColumn::make('price_minor')->label(__('السعر'))->align('end')
                ->using(fn ($v, InventoryItem $i): string => $i->price()->format()),

            BooleanColumn::make('is_sellable')->label(__('يُباع')),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('kind')->label(__('النوع'))
                ->options(array_map(fn (string $l): string => __($l), InventoryItem::KINDS)),

            SelectFilter::make('branch_id')->label(__('الفرع'))
                ->options(Branch::get()->mapWithKeys(fn (Branch $b): array => [$b->getKey() => (string) $b->name])->all()),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('الصنف'))->fields([
                TextField::make('name')->label(__('الاسم'))->required(),
                TextField::make('sku')->label(__('الكود'))->half()
                    ->hint(__('اختياري — كودك أنت أو الباركود المطبوع.')),
                SelectField::make('kind')->label(__('النوع'))->half()
                    ->options(array_map(fn (string $l): string => __($l), InventoryItem::KINDS))
                    ->default('notes'),
                SelectField::make('branch_id')->label(__('الفرع'))->half()
                    ->options(Branch::get()->mapWithKeys(fn (Branch $b): array => [$b->getKey() => (string) $b->name])->all())
                    ->placeholder(__('كل الفروع')),
            ]),

            Section::make(__('السعر والتنبيه'))
                ->description(__('الرصيد يتحرّك من شاشة الحركات لا من هنا — فيبقى معروفاً لماذا نقص.'))
                ->fields([
                    NumberField::make('price_minor')->label(__('سعر البيع'))->suffix(__('قرش'))
                        ->range(0, 100000000)->half()->default(0),
                    NumberField::make('cost_minor')->label(__('سعر الشراء'))->suffix(__('قرش'))
                        ->range(0, 100000000)->half()->default(0),
                    NumberField::make('reorder_level')->label(__('نبّهني عند'))->suffix(__('قطعة'))
                        ->range(0, 100000)->half()->default(0)
                        ->hint(__('صفر يعني بلا تنبيه.')),
                    SwitchField::make('is_sellable')->label(__('يُباع للطلبة'))->default(true),
                    TextareaField::make('note')->label(__('ملاحظة'))->rows(2),
                ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا أصناف في المخزن'),
            'body' => __('المذكّرات والكتب التي تبيعها، والأدوات التي تسلّمها عهدةً لمدرّسيك — كلّها هنا برصيدها وحركاتها.'),
        ];
    }
}
