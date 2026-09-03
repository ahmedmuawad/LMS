<?php

declare(strict_types=1);

namespace App\Modules\Content\Blocks\Types;

use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\TextareaField;
use App\Core\Admin\Fields\TranslatableField;
use App\Modules\Content\Blocks\Block;

final class FeaturesBlock extends Block
{
    public function key(): string
    {
        return 'features';
    }

    public function label(): string
    {
        return __('مزايا');
    }

    public function icon(): string
    {
        return '⁙';
    }

    public function fields(): array
    {
        return [
            TranslatableField::make('heading')->label(__('العنوان')),
            TextareaField::make('items')->label(__('المزايا'))
                ->hint(__('ميزة في كل سطر: الأيقونة | العنوان | الوصف')),
            SelectField::make('columns')->label(__('عدد الأعمدة'))->half()
                ->options(['2' => '2', '3' => '3', '4' => '4'])->default('3'),
        ];
    }
}
