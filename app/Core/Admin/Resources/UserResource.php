<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources;

use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\BooleanColumn;
use App\Core\Admin\Columns\DateColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Filters\BooleanFilter;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Models\User;

/**
 * أول مورد حقيقي — يثبت أن تعريفاً واحداً في PHP يكفي لتوليد
 * شاشة كاملة ببحث وفلاتر وفرز وترقيم وحالة فارغة.
 */
final class UserResource extends Resource
{
    public function model(): string
    {
        return User::class;
    }

    public function label(): string
    {
        return __('المستخدمون');
    }

    public function singularLabel(): string
    {
        return __('مستخدم');
    }

    public function icon(): string
    {
        return '👥';
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('الاسم'))
                ->description('email')
                ->sortable()
                ->searchable()
                ->wrap(),

            TextColumn::make('phone')
                ->label(__('الهاتف'))
                ->mono()
                ->searchable(),

            BadgeColumn::make('status')
                ->label(__('الحالة'))
                ->tones(['active' => 'success', 'pending' => 'warning', 'suspended' => 'danger'])
                ->labels([
                    'active' => __('نشط'),
                    'pending' => __('بانتظار التفعيل'),
                    'suspended' => __('موقوف'),
                ])
                ->sortable(),

            BooleanColumn::make('email_verified_at')
                ->label(__('بريد مُفعَّل'))
                ->align('center'),

            DateColumn::make('last_seen_at')
                ->label(__('آخر ظهور'))
                ->relative(),

            DateColumn::make('created_at')
                ->label(__('تاريخ التسجيل'))
                ->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status')
                ->label(__('الحالة'))
                ->options([
                    'active' => __('نشط'),
                    'pending' => __('بانتظار التفعيل'),
                    'suspended' => __('موقوف'),
                ]),

            BooleanFilter::make('legacy_hash')
                ->label(__('مُرحَّل من ووردبريس')),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا يوجد مستخدمون بعد'),
            'body' => __('سيظهر هنا كل من يسجّل في منصّتك، أو يمكنك دعوتهم بأنفسك.'),
        ];
    }
}
