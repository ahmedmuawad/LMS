<?php

declare(strict_types=1);

namespace App\Core\Admin\Fields;

use Illuminate\Database\Eloquent\Model;

/**
 * سرّ يُخزَّن مشفّراً ولا يُعاد إلى المتصفح أبداً.
 * الفراغ يعني «أبقِ القائم»، لا «امحُ ما هو محفوظ».
 */
final class PasswordField extends Field
{
    public static function make(string $name): static
    {
        return parent::make($name)->skipWhenEmpty();
    }

    public function isSecret(): bool
    {
        return true;
    }

    public function component(): string
    {
        return 'admin.fields.password';
    }

    public function valueFor(?Model $record): mixed
    {
        return null;   // لا يُعاد سرّ محفوظ إلى الصفحة
    }

    public function validationRules(string $context): array
    {
        return ['nullable', 'string', 'max:1000'];
    }
}
