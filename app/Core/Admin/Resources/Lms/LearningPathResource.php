<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Lms;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\NumberColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\ImageField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Lms\Models\LearningPath;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class LearningPathResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::COURSES_MANAGE;
    }

    public function model(): string
    {
        return LearningPath::class;
    }

    /** المسار بلا كورسات لا شيء — فيُدَلّ على بانيه فور إنشائه. */
    public function nextStep(Model $record, string $key): ?array
    {
        return [
            'url' => url('/admin/paths/'.$record->getKey().'/courses'),
            'label' => __('كورسات المسار'),
            'hint' => __('رتّب كورساته — المسار رحلةٌ بترتيب، وبلا كورسات هو عنوانٌ فارغ.'),
        ];
    }

    public function label(): string
    {
        return __('المسارات');
    }

    public function singularLabel(): string
    {
        return __('مسار');
    }

    public function query(): Builder
    {
        return LearningPath::query()->withCount(['items', 'enrollments']);
    }

    public function columns(): array
    {
        return [
            TextColumn::make('title')->label(__('المسار'))->searchable()->wrap(),

            NumberColumn::make('items_count')->label(__('كورساته'))->sortable(),
            NumberColumn::make('enrollments_count')->label(__('الملتحقون'))->sortable(),

            BadgeColumn::make('status')->label(__('الحالة'))
                ->tones(['published' => 'success', 'archived' => 'neutral'])
                ->labels(array_map(fn (string $l): string => __($l), LearningPath::STATUSES)),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status')->label(__('الحالة'))
                ->options(array_map(fn (string $l): string => __($l), LearningPath::STATUSES)),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('المسار'))
                ->description(__('الكورس مادّةٌ واحدة، والمسار رحلةٌ عبر كورسات بترتيب — «الثانوية العامة رياضيات» ثلاثة كورسات يعرف الطالب أيّها أوّلاً.'))
                ->fields([
                    TranslatableField::make('title')->label(__('العنوان'))->required(),
                    TextField::make('slug')->label(__('الرابط'))->required()->half()
                        ->rules(['alpha_dash', 'max:200', 'unique:learning_paths,slug']),
                    SelectField::make('status')->label(__('الحالة'))->half()
                        ->options(array_map(fn (string $l): string => __($l), LearningPath::STATUSES))
                        ->default('draft'),
                    TranslatableField::make('description')->label(__('الوصف'))->long(),
                    ImageField::make('cover_path')->label(__('صورة الغلاف')),
                ]),

            Section::make(__('السلوك'))->fields([
                SwitchField::make('is_sequential')->label(__('تسلسل إجباري'))->default(true)
                    ->hint(__('لا يُفتح كورسٌ حتى يُتمّ ما قبله. أطفئه لمسار مراجعةٍ يُفتح كلّه دفعةً.')),
                SwitchField::make('is_public')->label(__('يراه الزائر'))->default(true),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا مسارات بعد'),
            'body' => __('الطالب يشتري كورساتك مفرّقةً ولا يعرف أيّها أوّلاً. والمسار يرتّبها له — وهو ما يُباع للشركات كذلك: تدريبٌ مساراً لا كورساً.'),
        ];
    }
}
