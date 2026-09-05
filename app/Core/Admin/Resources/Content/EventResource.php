<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Content;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\BooleanColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\DateField;
use App\Core\Admin\Fields\ImageField;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Content\Models\Event;
use App\Modules\Lms\Models\Course;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class EventResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::CONTENT_MANAGE;
    }

    public function model(): string
    {
        return Event::class;
    }

    public function label(): string
    {
        return __('الفعاليات');
    }

    public function singularLabel(): string
    {
        return __('فعالية');
    }

    public function query(): Builder
    {
        return Event::query()->with('course')->orderByDesc('starts_at');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('title')->label(__('الفعالية'))->searchable()->wrap(),

            BadgeColumn::make('kind')->label(__('النوع'))
                ->tones(['holiday' => 'warning', 'exam' => 'danger', 'webinar' => 'info'])
                ->labels(array_map(fn (string $l): string => __($l), Event::KINDS)),

            TextColumn::make('starts_at')->label(__('الموعد'))
                ->using(fn ($v, Event $e): string => $e->starts_at?->translatedFormat('j M Y · H:i') ?? '—')
                ->sortable(),

            TextColumn::make('registered_count')->label(__('المسجّلون'))->mono()->align('end')
                ->using(fn ($v, Event $e): string => $e->takesRegistrations()
                    ? $e->registered_count.' / '.$e->capacity
                    : __('بلا تسجيل')),

            BooleanColumn::make('is_published')->label(__('منشورة')),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('kind')->label(__('النوع'))
                ->options(array_map(fn (string $l): string => __($l), Event::KINDS)),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('الفعالية'))->fields([
                TranslatableField::make('title')->label(__('العنوان'))->required(),
                TextField::make('slug')->label(__('الرابط'))->required()->half()
                    ->rules(['alpha_dash', 'max:200', 'unique:events,slug']),
                SelectField::make('kind')->label(__('النوع'))->half()
                    ->options(array_map(fn (string $l): string => __($l), Event::KINDS))
                    ->default('workshop'),
                TranslatableField::make('description')->label(__('الوصف'))->long(),
                ImageField::make('cover_path')->label(__('صورة الغلاف')),
            ]),

            Section::make(__('الموعد والمكان'))->fields([
                DateField::make('starts_at')->label(__('يبدأ'))->withTime()->required()->half(),
                DateField::make('ends_at')->label(__('ينتهي'))->withTime()->half()
                    ->hint(__('اتركه فارغاً لموعدٍ بلا نهاية معلومة.')),
                TextField::make('location')->label(__('المكان'))->half()
                    ->hint(__('القاعة أو العنوان — للفعالية الحضورية.')),
                TextField::make('url')->label(__('الرابط'))->url()->half()
                    ->hint(__('رابط الاجتماع أو البثّ — للفعالية الأونلاين.')),
            ]),

            Section::make(__('التسجيل والنشر'))
                ->description(__('السعة صفرٌ تعني إعلاناً بلا تسجيل — كإجازةٍ تُعلَن ولا يُحجز فيها مقعد.'))
                ->fields([
                    NumberField::make('capacity')->label(__('عدد المقاعد'))->range(0, 100000)->half()->default(0),
                    SelectField::make('course_id')->label(__('كورس مرتبط'))->half()
                        ->options(Course::orderBy('id')->limit(300)->get()
                            ->mapWithKeys(fn (Course $c): array => [$c->getKey() => (string) $c->title])->all())
                        ->placeholder(__('بلا كورس')),
                    SwitchField::make('is_published')->label(__('منشورة'))->default(true),
                    SwitchField::make('is_public')->label(__('يراها الزائر'))->default(true)
                        ->hint(__('أطفئها لتكون لطلبتك وحدهم.')),
                ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا فعاليات بعد'),
            'body' => __('الندوات والورش وأيام الامتحانات والإجازات — كل موعد ليس حصةً ولا درساً، مكانه هنا بدل منشورٍ يضيع.'),
        ];
    }
}
