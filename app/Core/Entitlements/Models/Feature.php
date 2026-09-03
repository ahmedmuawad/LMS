<?php

declare(strict_types=1);

namespace App\Core\Entitlements\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property string $key
 * @property array $name
 * @property string $type boolean|limit|quota
 */
final class Feature extends Model
{
    // جدول مركزي — يُقرأ من قاعدة المركز حتى داخل سياق مشترك
    use CentralConnection;

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
