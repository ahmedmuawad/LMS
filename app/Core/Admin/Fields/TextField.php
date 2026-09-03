<?php

declare(strict_types=1);

namespace App\Core\Admin\Fields;

use Illuminate\Database\Eloquent\Model;

final class TextField extends Field
{
    private string $type = 'text';

    private ?string $placeholder = null;

    public function email(): self
    {
        $this->type = 'email';
        $this->rules[] = 'email';

        return $this;
    }

    public function tel(): self
    {
        $this->type = 'tel';

        return $this;
    }

    public function password(): self
    {
        $this->type = 'password';
        // تُترك فارغة عند التعديل للإبقاء على كلمة المرور الحالية
        $this->skipWhenEmpty = true;

        return $this;
    }

    public function url(): self
    {
        $this->type = 'url';
        $this->rules[] = 'url';

        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function component(): string
    {
        return 'admin.fields.text';
    }

    public function props(): array
    {
        return ['type' => $this->type, 'placeholder' => $this->placeholder];
    }

    public function valueFor(?Model $record): mixed
    {
        // لا نُعيد كلمة مرور مجزّأة إلى الواجهة أبداً
        return $this->type === 'password' ? null : parent::valueFor($record);
    }
}
