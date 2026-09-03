<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Content;

use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\DateColumn;
use App\Core\Admin\Columns\NumberColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Content\Models\Post;
use App\Modules\Lms\Models\Taxonomy;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class PostResource extends Resource
{
    public function model(): string
    {
        return Post::class;
    }

    public function label(): string
    {
        return __('المدونة');
    }

    public function singularLabel(): string
    {
        return __('مقال');
    }

    public function query(): Builder
    {
        return Post::query()->with(['category', 'author']);
    }

    public function columns(): array
    {
        return [
            TextColumn::make('title')->label(__('العنوان'))->searchable()->wrap(),

            TextColumn::make('category.name')->label(__('القسم'))
                ->using(fn ($v, Post $p): string => (string) ($p->category?->name ?? '—')),

            BadgeColumn::make('status')->label(__('الحالة'))->sortable()
                ->tones(['published' => 'success', 'pending' => 'warning', 'draft' => 'neutral', 'scheduled' => 'info'])
                ->labels(array_map(fn (string $l): string => __($l), Post::STATUSES)),

            NumberColumn::make('views_count')->label(__('المشاهدات'))->sortable(),

            DateColumn::make('published_at')->label(__('نُشر في'))->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status')->label(__('الحالة'))
                ->options(array_map(fn (string $l): string => __($l), Post::STATUSES)),

            SelectFilter::make('category_id')->label(__('القسم'))
                ->options(Taxonomy::ofType('category')->pluck('name', 'id')->all()),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('المقال'))->fields([
                TranslatableField::make('title')->label(__('العنوان'))->required(),
                TextField::make('slug')->label(__('الرابط'))->required()->half()
                    ->rules(['alpha_dash', 'max:200', 'unique:posts,slug']),
                TranslatableField::make('excerpt')->label(__('المقتطف'))->long()
                    ->hint(__('يظهر في البطاقة وفي نتائج البحث — اكتبه ولا تتركه للاقتطاع الآلي.')),
                TranslatableField::make('body')->label(__('النص'))->long(),
            ]),

            Section::make(__('التصنيف'))->fields([
                SelectField::make('category_id')->label(__('القسم'))->half()
                    ->options(Taxonomy::ofType('category')->pluck('name', 'id')->all())
                    ->placeholder(__('بلا قسم')),
                TextField::make('cover_id')->label(__('معرّف صورة الغلاف'))->half()
                    ->rules(['integer'])->hint(__('من مكتبة الوسائط.')),
            ]),

            Section::make(__('النشر'))->fields([
                SelectField::make('status')->label(__('الحالة'))->half()
                    ->options(array_map(fn (string $l): string => __($l), Post::STATUSES))
                    ->default('draft'),
                SwitchField::make('allow_comments')->label(__('السماح بالتعليقات'))->default(true),
                SwitchField::make('featured')->label(__('مميّز في الواجهة')),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا مقالات بعد'),
            'body' => __('المدونة أرخص طريق للظهور في جوجل — ابدأ بمقال واحد.'),
        ];
    }
}
