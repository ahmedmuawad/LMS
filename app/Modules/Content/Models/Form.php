<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Core\Support\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Form extends Model
{
    use HasTranslations;

    public const FIELD_TYPES = [
        'text' => 'نص', 'email' => 'بريد', 'tel' => 'هاتف',
        'textarea' => 'نص طويل', 'select' => 'قائمة', 'checkbox' => 'اختيار',
        'date' => 'تاريخ', 'file' => 'ملف',
    ];

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['name', 'success_message'];

    protected function casts(): array
    {
        return [
            'name' => 'array', 'fields' => 'array', 'success_message' => 'array',
            'store_submissions' => 'boolean', 'is_active' => 'boolean',
        ];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /** @return array<string, list<string>> قواعد التحقق مبنية من تعريف الحقول */
    public function validationRules(): array
    {
        $rules = [];

        foreach ($this->fields ?? [] as $field) {
            $name = $field['name'] ?? null;

            if (! is_string($name)) {
                continue;
            }

            $set = [($field['required'] ?? false) ? 'required' : 'nullable'];

            $set[] = match ($field['type'] ?? 'text') {
                'email' => 'email',
                'date' => 'date',
                'file' => 'file',
                'checkbox' => 'boolean',
                default => 'string',
            };

            if (! in_array($field['type'] ?? '', ['file', 'checkbox'], true)) {
                $set[] = 'max:'.(int) ($field['max'] ?? 2000);
            }

            $rules['data.'.$name] = $set;
        }

        return $rules;
    }
}
