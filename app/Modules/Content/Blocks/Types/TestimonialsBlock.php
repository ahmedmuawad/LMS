<?php

declare(strict_types=1);

namespace App\Modules\Content\Blocks\Types;

use App\Core\Admin\Fields\TextareaField;
use App\Core\Admin\Fields\TranslatableField;
use App\Modules\Content\Blocks\Block;

final class TestimonialsBlock extends Block
{
    public function key(): string
    {
        return 'testimonials';
    }

    public function label(): string
    {
        return __('آراء');
    }

    public function icon(): string
    {
        return '❝';
    }

    public function fields(): array
    {
        return [
            TranslatableField::make('heading')->label(__('العنوان')),
            TextareaField::make('items')->label(__('الآراء'))
                ->hint(__('الاسم | الصفة | الرأي — في كل سطر.')),
        ];
    }
}
