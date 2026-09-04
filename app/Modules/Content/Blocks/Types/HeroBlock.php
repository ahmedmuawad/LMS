<?php

declare(strict_types=1);

namespace App\Modules\Content\Blocks\Types;

use App\Core\Admin\Fields\ImageField;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Modules\Content\Blocks\Block;

final class HeroBlock extends Block
{
    public function key(): string
    {
        return 'hero';
    }

    public function label(): string
    {
        return __('واجهة رئيسية');
    }

    public function icon(): string
    {
        return '◨';
    }

    public function group(): string
    {
        return 'layout';
    }

    public function fields(): array
    {
        return [
            TranslatableField::make('heading')->label(__('العنوان'))->required(),
            TranslatableField::make('subheading')->label(__('العنوان الفرعي'))->long(),
            TranslatableField::make('cta_label')->label(__('نص الزر')),
            TextField::make('cta_url')->label(__('رابط الزر'))->half(),
            ImageField::make('image')->label(__('الصورة'))->folder('blocks')->half(),
            SelectField::make('align')->label(__('المحاذاة'))->half()
                ->options(['start' => __('البداية'), 'center' => __('الوسط')])->default('start'),
        ];
    }
}
