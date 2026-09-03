<?php

declare(strict_types=1);

namespace App\Modules\Content\Blocks\Types;

use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextareaField;
use App\Core\Admin\Fields\TranslatableField;
use App\Modules\Content\Blocks\Block;

final class FaqBlock extends Block
{
    public function key(): string
    {
        return 'faq';
    }

    public function label(): string
    {
        return __('أسئلة شائعة');
    }

    public function icon(): string
    {
        return '؟';
    }

    public function fields(): array
    {
        return [
            TranslatableField::make('heading')->label(__('العنوان')),
            TextareaField::make('items')->label(__('الأسئلة'))
                ->hint(__('سؤال | جواب — في كل سطر.')),
            SwitchField::make('schema')->label(__('بيانات منظّمة للبحث'))->default(true)
                ->hint(__('تُظهر الأسئلة في نتائج جوجل.')),
        ];
    }
}
