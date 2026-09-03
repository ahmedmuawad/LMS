<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Content;

use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\BooleanColumn;
use App\Core\Admin\Columns\DateColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Content\Models\Page;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class PageResource extends Resource
{
    public function model(): string
    {
        return Page::class;
    }

    public function label(): string
    {
        return __('الصفحات');
    }

    public function singularLabel(): string
    {
        return __('صفحة');
    }

    public function query(): Builder
    {
        return Page::query();
    }

    /** الصفحة تُحرَّر في الباني لا في نموذج حقول — الكتل هي محتواها. */
    public function recordUrl(Model $record, string $key): ?string
    {
        return url('/admin/page-builder/'.$record->getKey());
    }

    public function columns(): array
    {
        return [
            TextColumn::make('title')->label(__('العنوان'))->searchable()->wrap()->description('slug'),

            BadgeColumn::make('status')->label(__('الحالة'))->sortable()
                ->tones(['published' => 'success', 'draft' => 'neutral', 'scheduled' => 'info'])
                ->labels(array_map(fn (string $l): string => __($l), Page::STATUSES)),

            TextColumn::make('blocks')->label(__('الكتل'))->mono()->align('end')
                ->using(fn ($v, Page $p): string => (string) count($p->blocks ?? [])),

            BooleanColumn::make('is_system')->label(__('إلزامية')),

            DateColumn::make('updated_at')->label(__('آخر تعديل'))->sortable()->relative(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status')->label(__('الحالة'))
                ->options(array_map(fn (string $l): string => __($l), Page::STATUSES)),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('الصفحة'))
                ->description(__('المحتوى نفسه يُبنى بالكتل في المحرّر.'))
                ->fields([
                    TranslatableField::make('title')->label(__('العنوان'))->required(),
                    TextField::make('slug')->label(__('الرابط'))->required()->half()
                        ->rules(['alpha_dash', 'max:200', 'unique:pages,slug']),
                    SelectField::make('status')->label(__('الحالة'))->half()
                        ->options(array_map(fn (string $l): string => __($l), Page::STATUSES))
                        ->default('draft'),
                    TranslatableField::make('excerpt')->label(__('وصف مختصر'))->long(),
                ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا صفحات بعد'),
            'body' => __('«من نحن» و«اتصل بنا» تُنشأ تلقائياً — أضف ما عداها.'),
        ];
    }
}
