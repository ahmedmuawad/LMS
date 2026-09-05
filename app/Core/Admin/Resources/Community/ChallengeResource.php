<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Community;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BooleanColumn;
use App\Core\Admin\Columns\NumberColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\DateField;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Resource;
use App\Modules\Gamification\Models\Badge;
use App\Modules\Gamification\Models\Challenge;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class ChallengeResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::MODULES_MANAGE;
    }

    public function feature(): ?string
    {
        return 'gamification';
    }

    public function model(): string
    {
        return Challenge::class;
    }

    public function label(): string
    {
        return __('التحدّيات');
    }

    public function singularLabel(): string
    {
        return __('تحدٍّ');
    }

    public function query(): Builder
    {
        return Challenge::query()->with('badge');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('title')->label(__('التحدي'))->searchable()->wrap(),

            TextColumn::make('rule')->label(__('يقيس'))
                ->using(fn ($v, Challenge $c): string => $c->ruleLabel()),

            NumberColumn::make('target')->label(__('الهدف'))->sortable(),
            NumberColumn::make('reward_points')->label(__('الجائزة'))->sortable(),

            TextColumn::make('ends_at')->label(__('ينتهي'))
                ->using(fn ($v, Challenge $c): string => $c->ends_at?->translatedFormat('j M Y') ?? __('بلا مهلة')),

            BooleanColumn::make('is_active')->label(__('مفعّل')),
        ];
    }

    public function form(): array
    {
        /*
         | القواعد من ملفّ التلعيب لا مكتوبةً هنا.
         |
         | إضافةُ قاعدةٍ جديدة هناك تظهر هنا بلا تعديل — ونسخُها
         | يعني قائمتين تفترقان، فيختار المدرّس قاعدةً لا تُقاس.
         */
        $rules = collect(config('gamification.rules', []))
            ->mapWithKeys(fn (array $rule, string $key): array => [$key => __($rule['label'] ?? $key)])
            ->all();

        return [
            Section::make(__('التحدي'))->fields([
                TranslatableField::make('title')->label(__('العنوان'))->required()
                    ->hint(__('اجعله فعلاً بعدد ومهلة — مثل «أتمّ خمسة دروس هذا الأسبوع».')),
                TranslatableField::make('description')->label(__('الوصف'))->long(),
                TextField::make('icon')->label(__('الأيقونة'))->half()
                    ->hint(__('رمزٌ واحد — مثل ◎ أو ★.')),
                SwitchField::make('is_active')->label(__('مفعّل'))->default(true),
            ]),

            Section::make(__('الشرط'))
                ->description(__('التحدي يقيس ما يفعله الطالب فعلاً — والتقدّم يُحسب من سجلّ نقاطه، فلا يحتاج ضبطاً.'))
                ->fields([
                    SelectField::make('rule')->label(__('يقيس'))->required()->half()
                        ->options($rules)->default('lesson.completed'),
                    NumberField::make('target')->label(__('العدد المطلوب'))->range(1, 1000)->half()->default(5),
                    DateField::make('starts_at')->label(__('يبدأ'))->withTime()->half()
                        ->hint(__('اتركه فارغاً ليبدأ فوراً.')),
                    DateField::make('ends_at')->label(__('ينتهي'))->withTime()->half()
                        ->hint(__('المهلة هي ما يُحرّك — والتحدي بلا نهاية أمنيةٌ لا تحدٍّ.')),
                ]),

            Section::make(__('الجائزة'))->fields([
                NumberField::make('reward_points')->label(__('نقاط'))->range(0, 100000)->half()->default(50),
                SelectField::make('badge_id')->label(__('شارة'))->half()
                    ->options(Badge::where('is_active', true)->get()
                        ->mapWithKeys(fn (Badge $b): array => [$b->getKey() => (string) $b->name])->all())
                    ->placeholder(__('بلا شارة')),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا تحدّيات بعد'),
            'body' => __('النقاط والشارات تقيس ما وقع؛ والتحدّي يصنع ما يقع — هدفٌ محدّد بمهلة يُحرّك المتردّد.'),
        ];
    }
}
