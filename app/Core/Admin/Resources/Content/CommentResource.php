<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Content;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\DateColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\TextareaField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Content\Models\Comment;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class CommentResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::COMMENTS_MODERATE;
    }

    public function feature(): ?string
    {
        return 'blog';
    }

    public function model(): string
    {
        return Comment::class;
    }

    public function label(): string
    {
        return __('التعليقات');
    }

    public function singularLabel(): string
    {
        return __('تعليق');
    }

    public function query(): Builder
    {
        return Comment::query()->with('user');
    }

    public function defaultSort(): array
    {
        // بانتظار المراجعة أولاً: الشاشة طابور عمل لا أرشيف
        return ['status', 'asc'];
    }

    public function columns(): array
    {
        return [
            TextColumn::make('body')->label(__('التعليق'))->searchable()->wrap()->limit(140),

            TextColumn::make('author_name')->label(__('الكاتب'))
                ->using(fn ($v, Comment $c): string => $c->authorName()),

            BadgeColumn::make('status')->label(__('الحالة'))->sortable()
                ->tones(['approved' => 'success', 'pending' => 'warning', 'spam' => 'danger', 'trash' => 'neutral'])
                ->labels(array_map(fn (string $l): string => __($l), Comment::STATUSES)),

            DateColumn::make('created_at')->label(__('وصل في'))->sortable()->relative(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status')->label(__('الحالة'))
                ->options(array_map(fn (string $l): string => __($l), Comment::STATUSES)),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('المراجعة'))->fields([
                TextareaField::make('body')->label(__('نصّ التعليق'))->required(),
                SelectField::make('status')->label(__('الحالة'))->half()
                    ->options(array_map(fn (string $l): string => __($l), Comment::STATUSES))
                    ->default('pending'),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return ['title' => __('لا تعليقات'), 'body' => __('ما إن يبدأ النقاش حتى يظهر هنا للمراجعة.')];
    }
}
