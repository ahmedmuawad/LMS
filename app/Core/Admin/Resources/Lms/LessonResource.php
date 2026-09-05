<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Lms;

use App\Core\Access\Ability;
use App\Core\Access\Scope;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\DateField;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Models\User;
use App\Modules\Lms\Models\Lesson;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class LessonResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::LESSONS_MANAGE;
    }

    public function scopeFor(Builder $query, ?User $user): Builder
    {
        return app(Scope::class)->byCreator($query, $user);
    }

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
        $form = [
            Section::make(__('الدرس'))->fields([
                TranslatableField::make('title')->label(__('العنوان'))->required(),
                SelectField::make('type')->label(__('النوع'))->half()
                    ->options($this->types())->default('video')
                    ->hint($this->manages()
                        ? __('الحصة موعدٌ لمجموعة، لا درساً: مواعيدها وحضورها وروابطها في «الحصص والمجموعات».')
                        : null),
                NumberField::make('duration_seconds')->label(__('المدة'))->suffix(__('ثانية'))
                    ->range(0, 86400)->half()->default(0),
                TranslatableField::make('content')->label(__('المحتوى النصّي'))->long(),
            ]),
        ];

        /*
         | قسم الحصة لمن لا يدير مجموعات فقط.
         |
         | من عنده «الحصص والمجموعات» له مكانٌ واحد للحصة يجمع موعدها
         | وحضورها ورابطها. وتكرار الرابط هنا يصنع نسختين من الحقيقة،
         | فيدخل نصف الطلبة على رابط ونصفهم على آخر — والأكاديمية
         | الأونلاين الصِّرفة وحدها هي التي تحتاج الحصة داخل المنهج.
         */
        if (! $this->manages()) {
            $form[] = Section::make(__('الحصة المباشرة'))
                ->description(__('لدرسٍ نوعه «حصة مباشرة» — يُفتح الرابط للطالب المشترك في الكورس.'))
                ->fields([
                    TextField::make('live_url')->label(__('رابط الاجتماع'))
                        ->placeholder('https://meet.google.com/…'),
                    DateField::make('live_starts_at')->label(__('موعد البدء'))->withTime()->half(),
                    NumberField::make('live_minutes')->label(__('مدة الحصة'))
                        ->suffix(__('دقيقة'))->range(0, 600)->half(),
                ]);
        }

        $form[] = Section::make(__('الفيديو'))
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
            ]);

        return $form;
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا دروس بعد'),
            'body' => $this->manages()
                ? __('الدرس محتوى داخل كورس: فيديو أو ملف أو نص يفتحه الطالب متى شاء. أمّا حصص مجموعاتك ومواعيدها وحضورها ففي «الحصص والمجموعات».')
                : __('الدرس هو أصغر وحدة تعليمية — أنشئه ثم ضعه في منهج كورس.'),
        ];
    }

    /**
     * أنواع الدروس المتاحة لهذا المشترك.
     *
     * «حصة مباشرة» تُحذف ممّن يدير مجموعات: للحصة عنده مكانٌ واحد
     * فيه الموعد والحضور والرابط معاً، وإبقاء النوع هنا يفتح للحصة
     * باباً ثانياً لا يعرف أيّهما الصحيح.
     *
     * @return array<string, string>
     */
    private function types(): array
    {
        $types = Lesson::TYPES;

        if ($this->manages()) {
            unset($types['live']);
        }

        return array_map(fn (string $label): string => __($label), $types);
    }

    private function manages(): bool
    {
        return tenant()?->managesCenter() ?? false;
    }
}
