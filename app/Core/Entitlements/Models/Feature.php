<?php

declare(strict_types=1);

namespace App\Core\Entitlements\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property array $name
 * @property string $type boolean|limit|quota
 */
final class Feature extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'is_visible' => 'boolean',
        ];
    }

    public function isQuota(): bool
    {
        return $this->type === 'quota';
    }
}
