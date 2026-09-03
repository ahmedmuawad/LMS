<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Content;

use App\Core\Admin\Columns\BooleanColumn;
use App\Core\Admin\Columns\NumberColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\CodeField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Filters\BooleanFilter;
use App\Core\Admin\Resource;
use App\Modules\Content\Models\Form;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class FormResource extends Resource
{
    public function model(): string
    {
        return Form::class;
    }

    public function label(): string
    {
        return __('النماذج');
    }

    public function singularLabel(): string
    {
        return __('نموذج');
    }

    public function query(): Builder
    {
        return Form::query()->withCount('submissions');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->label(__('النموذج'))->searchable()->description('key'),

            TextColumn::make('fields')->label(__('الحقول'))->mono()->align('end')
                ->using(fn ($v, Form $f): string => (string) count($f->fields ?? [])),

            NumberColumn::make('submissions_count')->label(__('الرسائل'))->sortable(),

            BooleanColumn::make('is_active')->label(__('مفعّل')),
        ];
    }

    public function filters(): array
    {
        return [
            BooleanFilter::make('is_active')->label(__('مفعّل')),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('النموذج'))->fields([
                TranslatableField::make('name')->label(__('الاسم'))->required(),
                TextField::make('key')->label(__('المفتاح'))->required()->half()
                    ->rules(['alpha_dash', 'max:48', 'unique:forms,key'])
                    ->hint(__('يُستدعى به داخل كتلة النموذج في الصفحات.')),
            ]),

            Section::make(__('الحقول'))
                ->description(__('كل حقل كائن فيه name وlabel وtype، والأنواع: ').implode('، ', array_keys(Form::FIELD_TYPES)))
                ->fields([
                    CodeField::make('fields')->label(__('تعريف الحقول'))->json()->required(),
                ]),

            Section::make(__('بعد الإرسال'))->fields([
                TranslatableField::make('success_message')->label(__('رسالة النجاح'))->long(),
                TextField::make('notify_email')->label(__('بريد التنبيه'))->half()->rules(['email']),
                SwitchField::make('store_submissions')->label(__('حفظ الرسائل في اللوحة'))->default(true),
                SwitchField::make('is_active')->label(__('مفعّل'))->default(true),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا نماذج بعد'),
            'body' => __('نموذج «اتصل بنا» أول ما يحتاجه موقعك.'),
        ];
    }
}
