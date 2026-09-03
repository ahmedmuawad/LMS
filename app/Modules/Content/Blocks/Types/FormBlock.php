<?php

declare(strict_types=1);

namespace App\Modules\Content\Blocks\Types;

use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\TranslatableField;
use App\Modules\Content\Blocks\Block;
use App\Modules\Content\Models\Form;

final class FormBlock extends Block
{
    public function key(): string
    {
        return 'form';
    }

    public function label(): string
    {
        return __('نموذج');
    }

    public function icon(): string
    {
        return '✉';
    }

    public function group(): string
    {
        return 'dynamic';
    }

    public function fields(): array
    {
        return [
            TranslatableField::make('heading')->label(__('العنوان')),
            SelectField::make('form_key')->label(__('النموذج'))->half()
                ->options(Form::where('is_active', true)->get()
                    ->mapWithKeys(fn (Form $f): array => [$f->key => (string) $f->name])->all()),
        ];
    }
}
