<?php

declare(strict_types=1);

namespace App\Core\Admin\Fields;

use App\Modules\Content\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * صورة — معاينة ورفع واختيار من مكتبة الوسائط.
 *
 * كان كل شعار وغلاف وأيقونة حقلاً نصّياً يُطالب المستخدم بأن يرفع
 * الملف في شاشة أخرى ثم ينسخ مساره ويعود فيلصقه. الحقل هنا يفعل
 * الثلاثة في مكانه، ويعرض ما اختير فيراه قبل الحفظ لا بعده.
 *
 * القيمة المخزّنة تبقى كما كانت — مسار أو رابط — إلا إن طُلب
 * `storesId()`، فيُخزَّن معرّف الوسيط. فلا يحتاج حقلٌ قائم هجرةً
 * ليصير مرئياً.
 */
final class ImageField extends Field
{
    private bool $storesId = false;

    private ?string $folder = null;

    private ?string $ratio = null;

    /** يخزّن معرّف الوسيط لا مساره — للحقول المرتبطة بجدول الوسائط. */
    public function storesId(bool $stores = true): self
    {
        $this->storesId = $stores;

        return $this;
    }

    /** مجلّد الرفع داخل المكتبة — لتبقى الشعارات مع الشعارات. */
    public function folder(string $folder): self
    {
        $this->folder = $folder;

        return $this;
    }

    /** نسبة المعاينة، مثل «16/9» أو «1/1» — لتُرى الصورة كما ستُعرض. */
    public function ratio(string $ratio): self
    {
        $this->ratio = $ratio;

        return $this;
    }

    public function component(): string
    {
        return 'admin.fields.image';
    }

    public function props(): array
    {
        return [
            'storesId' => $this->storesId,
            'folder' => $this->folder,
            'ratio' => $this->ratio ?? '16/9',
        ];
    }

    public function validationRules(string $context): array
    {
        $rules = parent::validationRules($context);
        $rules[] = $this->storesId ? 'integer' : 'string';

        if (! $this->storesId) {
            $rules[] = 'max:2048';
        }

        return array_values(array_unique($rules));
    }

    public function fill(mixed $input): mixed
    {
        if ($input === null || $input === '') {
            return null;
        }

        return $this->storesId ? (int) $input : (string) $input;
    }

    /**
     * رابط المعاينة للقيمة المحفوظة.
     *
     * القيمة قد تكون معرّف وسيط أو مساراً على القرص أو رابطاً كاملاً —
     * والمعاينة يجب أن تعمل في الثلاث، وإلا بدا الحقل فارغاً لمن سبق
     * أن ملأه.
     */
    public static function previewUrl(mixed $value, bool $storesId = false): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($storesId || is_numeric($value)) {
            return Media::find((int) $value)?->url();
        }

        $value = (string) $value;

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '/')) {
            return $value;
        }

        return Storage::disk((string) setting('integrations.storage_driver', 'public'))->url($value);
    }
}
