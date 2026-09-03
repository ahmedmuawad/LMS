<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Lms;

use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Lms\Models\Lesson;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class LessonResource extends Resource
{
    public function model(): string
    {
        return Lesson::class;
    }

    public function label(): string
    {
        return __('الدروس');
    }

    public function singularLabel(): string
    {
        return __('درس');
    }

    public function query(): Builder
    {
        return Lesson::query();
    }

    public function columns(): array
    {
        return [
            TextColumn::make('title')->label(__('الدرس'))->searchable()->wrap()->sortable(),

            BadgeColumn::make('type')->label(__('النوع'))->sortable()
                ->tones(['live' => 'live', 'video' => 'primary', 'scorm' => 'accent'])
                ->labels(array_map(fn (string $l): string => __($l), Lesson::TYPES)),

            TextColumn::make('duration_seconds')->label(__('المدة'))->mono()->align('end')->sortable()
                ->using(fn ($v, Lesson $l): string => $l->durationLabel()),

            TextColumn::make('is_downloadable')->label(__('قابل للتنزيل'))
                ->using(fn ($v): string => $v ? __('نعم') : __('لا')),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('type')->label(__('النوع'))
                ->options(array_map(fn (string $l): string => __($l), Lesson::TYPES)),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('الدرس'))->fields([
                TranslatableField::make('title')->label(__('العنوان'))->required(),
                SelectField::make('type')->label(__('النوع'))->half()
                    ->options(array_map(fn (string $l): string => __($l), Lesson::TYPES))->default('video'),
                NumberField::make('duration_seconds')->label(__('المدة'))->suffix(__('ثانية'))
                    ->range(0, 86400)->half()->default(0),
                TranslatableField::make('content')->label(__('المحتوى النصّي'))->long(),
            ]),

            Section::make(__('الفيديو'))
                ->description(__('الرابط المباشر لا يُخزَّن: يُوقَّع عند كل مشاهدة وينتهي بدقائق.'))
                ->fields([
                    SelectField::make('video_provider')->label(__('المزوّد'))->half()
                        ->options([
                            'bunny' => 'Bunny Stream', 'cloudflare' => 'Cloudflare Stream',
                            'vimeo' => 'Vimeo', 'youtube' => 'YouTube', 'file' => __('ملف مرفوع'),
                        ]),
                    TextField::make('video_id')->label(__('معرّف الفيديو'))->half(),
                    SwitchField::make('is_downloadable')->label(__('يسمح بالتنزيل'))
                        ->hint(__('التنزيل يعني خروج الملف من حمايتك.')),
                ]),
        ];
    }

    public function emptyState(): array
    {
        return ['title' => __('لا دروس بعد'), 'body' => __('الدرس هو أصغر وحدة تعليمية — أنشئه ثم ضعه في منهج كورس.')];
    }
}
