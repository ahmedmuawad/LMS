<?php

declare(strict_types=1);

namespace App\Core\Settings;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * وثيقة 05 — طبقة الإعدادات.
 *
 * كل ما يخصّ الأعمال هنا، لا في ملفات config (تلك للثوابت التقنية فقط).
 * القراءة تمرّ بكاش كامل، فالطلب العادي لا يستعلم عن الإعدادات إطلاقاً.
 *
 *     setting('lms.passing_percentage', 60)
 *     setting()->translated('seo.default_description')
 *     setting()->set('commerce.guest_checkout', true)
 */
final class SettingsRepository
{
    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    public function all(): array
    {
        return $this->loaded ??= Cache::remember(
            $this->cacheKey(),
            now()->addDay(),
            function (): array {
                $out = [];

                foreach (DB::table('settings')->get() as $row) {
                    $value = json_decode((string) $row->value, true);

                    if ($row->is_encrypted && is_string($value)) {
                        $value = rescue(fn () => Crypt::decryptString($value), null, false);
                    }

                    $out["{$row->group}.{$row->key}"] = $value;
                }

                return $out;
            },
        );
    }

    public function get(string $path, mixed $default = null): mixed
    {
        return $this->all()[$path] ?? $default;
    }

    public function has(string $path): bool
    {
        return array_key_exists($path, $this->all());
    }

    /** قيمة الحقل المترجم بلغة العرض الحالية، مع الاحتياط للغة الافتراضية. */
    public function translated(string $path, ?string $locale = null, mixed $default = null): mixed
    {
        $value = $this->get($path);

        if (! is_array($value)) {
            return $value ?? $default;
        }

        $locale ??= app()->getLocale();

        return $value[$locale]
            ?? $value[config('locales.default', 'ar')]
            ?? reset($value)
            ?: $default;
    }

    public function set(string $path, mixed $value, bool $encrypted = false, bool $translatable = false): void
    {
        [$group, $key] = $this->split($path);

        $stored = $encrypted && is_string($value) ? Crypt::encryptString($value) : $value;

        DB::table('settings')->updateOrInsert(
            ['group' => $group, 'key' => $key],
            [
                'value' => json_encode($stored, JSON_UNESCAPED_UNICODE),
                'is_encrypted' => $encrypted,
                'is_translatable' => $translatable,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $this->flush();
    }

    /** @param  array<string, mixed>  $values */
    public function setMany(array $values): void
    {
        foreach ($values as $path => $value) {
            [$group, $key] = $this->split($path);

            DB::table('settings')->updateOrInsert(
                ['group' => $group, 'key' => $key],
                ['value' => json_encode($value, JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $this->flush();
    }

    public function forget(string $path): void
    {
        [$group, $key] = $this->split($path);

        DB::table('settings')->where('group', $group)->where('key', $key)->delete();

        $this->flush();
    }

    /** @return array<string, mixed> كل إعدادات مجموعة واحدة بمفاتيح قصيرة */
    public function group(string $group): array
    {
        $out = [];

        foreach ($this->all() as $path => $value) {
            if (str_starts_with($path, $group.'.')) {
                $out[substr($path, strlen($group) + 1)] = $value;
            }
        }

        return $out;
    }

    public function flush(): void
    {
        $this->loaded = null;
        Cache::forget($this->cacheKey());
    }

    /** @return array{0:string, 1:string} */
    private function split(string $path): array
    {
        if (! str_contains($path, '.')) {
            throw new \InvalidArgumentException("مسار إعداد غير صالح: [{$path}] — المتوقع «مجموعة.مفتاح».");
        }

        return explode('.', $path, 2);
    }

    /**
     * المفتاح يحمل هوية المشترك صراحةً.
     * الاعتماد على بادئة الكاش وحدها لا يكفي: نسخة المستودع تعيش
     * داخل الحاوية عبر تبديل السياق، فيتسرّب إعداد مشترك إلى آخر.
     */
    private function cacheKey(): string
    {
        return 'settings:all:'.(tenant('id') ?? 'central');
    }
}
