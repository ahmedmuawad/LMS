<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Content;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\DateColumn;
use App\Core\Admin\Columns\NumberColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Resource;
use App\Modules\Content\Models\Redirect;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class RedirectResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::CONTENT_MANAGE;
    }

    public function model(): string
    {
        return Redirect::class;
    }

    public function label(): string
    {
        return __('تحويلات الروابط');
    }

    public function singularLabel(): string
    {
        return __('تحويل');
    }

    public function query(): Builder
    {
        return Redirect::query();
    }

    public function columns(): array
    {
        return [
            TextColumn::make('from')->label(__('من'))->searchable()->mono()->wrap(),
            TextColumn::make('to')->label(__('إلى'))->searchable()->mono()->wrap(),
            TextColumn::make('code')->label(__('النوع'))->mono()->align('end'),
            NumberColumn::make('hits')->label(__('الزيارات'))->sortable(),
            DateColumn::make('last_hit_at')->label(__('آخر زيارة'))->sortable()->relative(),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('التحويل'))
                ->description(__('كل رابط من الموقع القديم يجب أن يصل إلى مقابله، وإلا ضاع ترتيب سنوات في جوجل.'))
                ->fields([
                    TextField::make('from')->label(__('الرابط القديم'))->required()
                        ->rules(['max:500', 'unique:redirects,from'])->hint('/old-course-page'),
                    TextField::make('to')->label(__('الرابط الجديد'))->required()->rules(['max:500']),
                    SelectField::make('code')->label(__('نوع التحويل'))->half()
                        ->options([
                            '301' => __('دائم (301) — ينقل ترتيب البحث'),
                            '302' => __('مؤقّت (302)'),
                        ])->default('301'),
                ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا تحويلات'),
            'body' => __('عند الترحيل من موقع قديم، أضف هنا كل رابط تغيّر.'),
        ];
    }
}
