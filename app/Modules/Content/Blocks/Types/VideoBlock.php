<?php

declare(strict_types=1);

namespace App\Modules\Content\Blocks\Types;

use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Modules\Content\Blocks\Block;

final class VideoBlock extends Block
{
    public function key(): string
    {
        return 'video';
    }

    public function label(): string
    {
        return __('فيديو');
    }

    public function icon(): string
    {
        return '▶';
    }

    public function fields(): array
    {
        return [
            TranslatableField::make('heading')->label(__('العنوان')),
            SelectField::make('provider')->label(__('المزوّد'))->half()
                ->options(['youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'file' => __('ملف')])
                ->default('youtube'),
            TextField::make('video_id')->label(__('معرّف الفيديو أو الرابط'))->half(),
        ];
    }
}
