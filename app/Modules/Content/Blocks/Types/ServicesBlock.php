<?php

declare(strict_types=1);

namespace App\Modules\Content\Blocks\Types;

use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\TranslatableField;
use App\Modules\Content\Blocks\Block;

final class ServicesBlock extends Block
{
    public function key(): string
    {
        return 'services';
    }

    public function label(): string
    {
        return __('خدمات');
    }

    public function icon(): string
    {
        return '◇';
    }

    public function group(): string
    {
        return 'dynamic';
    }

    public function fields(): array
    {
        return [
            TranslatableField::make('heading')->label(__('العنوان')),
            NumberField::make('limit')->label(__('العدد'))->range(1, 24)->half()->default(6),
            SelectField::make('columns')->label(__('عدد الأعمدة'))->half()
                ->options(['2' => '2', '3' => '3', '4' => '4'])->default('3'),
        ];
    }
}
