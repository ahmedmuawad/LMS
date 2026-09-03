<?php

declare(strict_types=1);

namespace App\Core\Notifications\Models;

use App\Core\Support\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

final class NotificationTemplate extends Model
{
    use HasTranslations;

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['subject', 'body'];

    protected function casts(): array
    {
        return ['subject' => 'array', 'body' => 'array', 'is_enabled' => 'boolean'];
    }
}
