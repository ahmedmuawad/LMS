<?php

declare(strict_types=1);

namespace App\Modules\Content\Blocks\Types;

use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Modules\Content\Blocks\Block;

final class CtaBlock extends Block
{
    public function key(): string
    {
        return 'cta';
    }

    public function label(): string
    {
        return __('دعوة لإجراء');
    }

    public function icon(): string
    {
        return '➤';
    }

    public function fields(): array
    {
        return [
            TranslatableField::make('heading')->label(__('العنوان'))->required(),
            TranslatableField::make('body')->label(__('النص'))->long(),
            TranslatableField::make('button_label')->label(__('نص الزر')),
            TextField::make('button_url')->label(__('رابط الزر'))->half(),
            SelectField::make('tone')->label(__('اللون'))->half()
                ->options(['primary' => __('الأساسي'), 'accent' => __('التمييز'), 'surface' => __('محايد')])
                ->default('primary'),
        ];
    }
}
