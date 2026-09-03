<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Services;

use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\NumberColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\CodeField;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Lms\Models\Taxonomy;
use App\Modules\Services\Models\Service;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class ServiceResource extends Resource
{
    public function model(): string
    {
        return Service::class;
    }

    public function label(): string
    {
        return __('الخدمات');
    }

    public function singularLabel(): string
    {
        return __('خدمة');
    }

    public function query(): Builder
    {
        return Service::query()->with('category');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('title')->label(__('الخدمة'))->searchable()->wrap()->description('slug'),

            BadgeColumn::make('type')->label(__('النوع'))->sortable()
                ->tones(['appointment' => 'primary', 'delivery' => 'accent', 'subscription' => 'info'])
                ->labels(array_map(fn (string $l): string => __($l), Service::TYPES)),

            TextColumn::make('price_minor')->label(__('السعر'))->mono()->align('end')->sortable()
                ->using(fn ($v, Service $s): string => $s->needsQuote() ? __('بعرض سعر') : $s->price()->format()),

            NumberColumn::make('bookings_count')->label(__('الحجوزات'))->sortable(),

            BadgeColumn::make('status')->label(__('الحالة'))->sortable()
                ->tones(['published' => 'success', 'draft' => 'neutral', 'archived' => 'neutral'])
                ->labels(['draft' => __('مسودّة'), 'published' => __('منشورة'), 'archived' => __('مؤرشفة')]),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('type')->label(__('النوع'))
                ->options(array_map(fn (string $l): string => __($l), Service::TYPES)),

            SelectFilter::make('status')->label(__('الحالة'))
                ->options(['draft' => __('مسودّة'), 'published' => __('منشورة'), 'archived' => __('مؤرشفة')]),
        ];
    }

    public function form(): array
    {
        $currency = (string) (tenant('currency') ?? config('money.default', 'EGP'));

        return [
            Section::make(__('الخدمة'))->fields([
                TranslatableField::make('title')->label(__('الاسم'))->required(),
                TextField::make('slug')->label(__('الرابط'))->required()->half()
                    ->rules(['alpha_dash', 'max:200', 'unique:services,slug']),
                SelectField::make('type')->label(__('النوع'))->half()->required()
                    ->options(array_map(fn (string $l): string => __($l), Service::TYPES))
                    ->default('appointment'),
                SelectField::make('category_id')->label(__('القسم'))->half()
                    ->options(Taxonomy::ofType('category')->pluck('name', 'id')->all())
                    ->placeholder(__('بلا قسم')),
                TranslatableField::make('excerpt')->label(__('وصف مختصر'))->long(),
                TranslatableField::make('description')->label(__('الوصف الكامل'))->long(),
            ]),

            Section::make(__('التسعير'))->fields([
                SelectField::make('currency')->label(__('العملة'))->half()->required()
                    ->options(collect((array) setting('currency.enabled', [$currency]))
                        ->mapWithKeys(fn (string $c): array => [$c => $c])->all())
                    ->default($currency),
                SelectField::make('price_type')->label(__('طريقة التسعير'))->half()
                    ->options(array_map(fn (string $l): string => __($l), Service::PRICE_TYPES))
                    ->default('fixed'),
                NumberField::make('price_minor')->label(__('السعر'))->half()->money($currency),
            ]),

            Section::make(__('المواعيد'))
                ->description(__('تخصّ الخدمات الموعدية: المخزون هنا ساعات المقدّم لا عدد النسخ.'))
                ->fields([
                    NumberField::make('duration_minutes')->label(__('مدة الجلسة'))->suffix(__('دقيقة'))
                        ->range(5, 600)->half()->default(60),
                    NumberField::make('buffer_minutes')->label(__('فاصل بين الجلسات'))->suffix(__('دقيقة'))
                        ->range(0, 240)->half()->default(0),
                    NumberField::make('lead_hours')->label(__('أقل مهلة للحجز'))->suffix(__('ساعة'))
                        ->range(0, 720)->half()->default(24),
                    NumberField::make('cancel_hours')->label(__('مهلة الإلغاء المجاني'))->suffix(__('ساعة'))
                        ->range(0, 720)->half()->default(24),
                    NumberField::make('max_per_slot')->label(__('حجوزات في الموعد الواحد'))
                        ->range(1, 100)->half()->default(1),
                    NumberField::make('delivery_days')->label(__('مدة التسليم'))->suffix(__('يوم'))
                        ->range(0, 365)->half()->default(0)
                        ->hint(__('للخدمات غير الموعدية.')),
                ]),

            Section::make(__('التفاصيل'))->fields([
                SelectField::make('location')->label(__('مكان التقديم'))->half()
                    ->options([
                        'online' => __('أونلاين'),
                        'onsite' => __('حضورياً'),
                        'both' => __('أونلاين أو حضورياً'),
                    ])->default('online'),
                CodeField::make('requirements')->label(__('ما نطلبه من العميل'))->json()->rows(5)
                    ->hint(__('قائمة نصوص — تظهر حقولاً في نموذج الحجز.')),
                CodeField::make('deliverables')->label(__('ما يحصل عليه'))->json()->rows(5),
            ]),

            Section::make(__('النشر'))->fields([
                SelectField::make('status')->label(__('الحالة'))->half()
                    ->options(['draft' => __('مسودّة'), 'published' => __('منشورة'), 'archived' => __('مؤرشفة')])
                    ->default('draft'),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا خدمات بعد'),
            'body' => __('الاستشارة وجلسة التقوية ومراجعة الملف كلّها خدمات تُباع بالوقت.'),
        ];
    }
}
