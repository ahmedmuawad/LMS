<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Lms;

use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\DateColumn;
use App\Core\Admin\Columns\NumberColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\Instructor;
use App\Modules\Lms\Models\Taxonomy;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CourseResource extends Resource
{
    public function model(): string
    {
        return Course::class;
    }

    public function label(): string
    {
        return __('الكورسات');
    }

    public function singularLabel(): string
    {
        return __('كورس');
    }

    public function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    public function query(): Builder
    {
        return Course::query()->with(['instructor.user', 'category']);
    }

    public function recordUrl(Model $record, string $key): ?string
    {
        return url('/admin/courses/'.$record->getKey().'/curriculum');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('title')->label(__('الكورس'))->searchable()->wrap()
                ->description('slug')->sortable(),

            TextColumn::make('instructor_id')->label(__('المدرّس'))
                ->using(fn ($v, Course $c): string => $c->instructor?->name() ?? '—'),

            BadgeColumn::make('status')->label(__('الحالة'))->sortable()
                ->tones(['draft' => 'neutral', 'pending' => 'warning', 'published' => 'success', 'archived' => 'neutral'])
                ->labels(array_map(fn (string $l): string => __($l), Course::STATUSES)),

            NumberColumn::make('students_count')->label(__('الطلاب'))->sortable(),
            NumberColumn::make('lessons_count')->label(__('العناصر'))->sortable(),

            TextColumn::make('price_minor')->label(__('السعر'))->mono()->align('end')->sortable()
                ->using(fn ($v, Course $c): string => $c->isFree() ? __('مجاني') : $c->price()->format()),

            DateColumn::make('published_at')->label(__('تاريخ النشر'))->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status')->label(__('الحالة'))
                ->options(array_map(fn (string $l): string => __($l), Course::STATUSES)),

            SelectFilter::make('enrollment_type')->label(__('نوع التسجيل'))
                ->options(['free' => __('مجاني'), 'paid' => __('مدفوع'), 'invite' => __('بدعوة')]),

            SelectFilter::make('category_id')->label(__('القسم'))
                ->options(Taxonomy::ofType('category')->get()
                    ->mapWithKeys(fn (Taxonomy $t): array => [$t->getKey() => (string) $t->name])->all()),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('الأساسيات'))->fields([
                TranslatableField::make('title')->label(__('عنوان الكورس'))->required(),
                TextField::make('slug')->label(__('الرابط'))->required()->half()
                    ->rules(['alpha_dash', 'max:200', 'unique:courses,slug'])
                    ->hint(__('يظهر في رابط الكورس — تغييره بعد النشر يكسر ما نُشر منه.')),
                SelectField::make('instructor_id')->label(__('المدرّس'))->half()
                    ->options(Instructor::with('user')->get()
                        ->mapWithKeys(fn (Instructor $i): array => [$i->getKey() => $i->name()])->all()),
                TranslatableField::make('excerpt')->label(__('نبذة مختصرة'))->long(),
                TranslatableField::make('description')->label(__('الوصف الكامل'))->long(),
            ]),

            Section::make(__('التصنيف'))->fields([
                SelectField::make('category_id')->label(__('القسم'))->half()
                    ->options(Taxonomy::ofType('category')->get()
                        ->mapWithKeys(fn (Taxonomy $t): array => [$t->getKey() => (string) $t->name])->all()),
                SelectField::make('level_id')->label(__('المستوى'))->half()
                    ->options(Taxonomy::ofType('level')->get()
                        ->mapWithKeys(fn (Taxonomy $t): array => [$t->getKey() => (string) $t->name])->all()),
                SelectField::make('language')->label(__('لغة المحتوى'))->half()
                    ->options(collect(config('locales.supported', []))
                        ->map(fn (array $m, string $k): string => $m['native'] ?? $k)->all())
                    ->default('ar'),
                TextField::make('cover_path')->label(__('صورة الغلاف'))->url()->half(),
            ]),

            Section::make(__('النشر والتسعير'))->fields([
                SelectField::make('status')->label(__('الحالة'))->half()
                    ->options(array_map(fn (string $l): string => __($l), Course::STATUSES))->default('draft'),
                SelectField::make('visibility')->label(__('الظهور'))->half()
                    ->options(['public' => __('عام'), 'private' => __('خاص'), 'hidden' => __('مخفي')])
                    ->default('public'),
                SelectField::make('enrollment_type')->label(__('نوع التسجيل'))->half()
                    ->options(['free' => __('مجاني'), 'paid' => __('مدفوع'), 'invite' => __('بدعوة')])
                    ->default('paid'),
                NumberField::make('price_minor')->label(__('السعر'))->half()
                    ->money((string) (tenant('currency') ?? 'EGP')),
                NumberField::make('compare_price_minor')->label(__('السعر قبل الخصم'))->half()
                    ->money((string) (tenant('currency') ?? 'EGP'))
                    ->hint(__('يظهر مشطوباً بجوار السعر.')),
                NumberField::make('max_students')->label(__('حد الطلاب'))->range(0, 100000)->half()
                    ->hint(__('اتركه فارغاً لبلا حد.')),
            ]),

            Section::make(__('الوصول والتقدّم'))->fields([
                NumberField::make('access_days')->label(__('مدة الوصول'))->suffix(__('يوم'))
                    ->range(0, 3650)->half()->default(0)->hint(__('صفر يعني وصولاً مدى الحياة.')),
                NumberField::make('passing_percentage')->label(__('نسبة النجاح'))->suffix('%')
                    ->range(0, 100)->half()->default(60),
                SwitchField::make('sequential')->label(__('تسلسل إجباري'))
                    ->hint(__('لا يُفتح عنصر قبل إكمال ما قبله.')),
                SwitchField::make('drip_enabled')->label(__('فتح تدريجي'))
                    ->hint(__('يصل المحتوى على مهل بدل أن يُفتح كاملاً.')),
                SwitchField::make('certificate_enabled')->label(__('إصدار شهادة عند الإكمال'))->default(true),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا كورسات بعد'),
            'body' => __('ابدأ بكورس واحد: عنوان ومنهج ودرس أول — والباقي يأتي.'),
        ];
    }
}
