<?php

declare(strict_types=1);

namespace App\Modules\Content\Blocks\Types;

use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\TranslatableField;
use App\Modules\Content\Blocks\Block;

final class RichTextBlock extends Block
{
    public function key(): string
    {
        return 'text';
    }

    public function label(): string
    {
        return __('نص');
    }

    public function icon(): string
    {
        return '¶';
    }

    public function fields(): array
    {
        return [
            TranslatableField::make('heading')->label(__('عنوان القسم')),
            TranslatableField::make('body')->label(__('النص'))->long(),
            SelectField::make('columns')->label(__('عدد الأعمدة'))->half()
                ->options(['1' => '1', '2' => '2'])->default('1'),
        ];
    }
}
