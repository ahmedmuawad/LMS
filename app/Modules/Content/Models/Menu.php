<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Core\Support\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

final class Menu extends Model
{
    use HasTranslations;

    public const LOCATIONS = [
        'main' => 'القائمة الرئيسية',
        'footer' => 'التذييل',
        'mobile' => 'قائمة الموبايل',
        'account' => 'قائمة الحساب',
    ];

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected function casts(): array
    {
        return ['name' => 'array', 'items' => 'array'];
    }

    public static function forLocation(string $key): ?self
    {
        return self::where('key', $key)->first();
    }
}
