<?php

declare(strict_types=1);

namespace App\Core\Admin\Columns;

use Illuminate\Database\Eloquent\Model;

final class BooleanColumn extends Column
{
    public function component(): string
    {
        return 'admin.columns.boolean';
    }

    public function props(Model $record): array
    {
        return ['on' => (bool) data_get($record, $this->name)];
    }
}
