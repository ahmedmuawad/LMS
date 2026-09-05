<?php

declare(strict_types=1);

namespace App\Core\Billing;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * إعدادات المنصّة — تُقرأ مرة واحدة في الطلب.
 *
 * على الاتصال المركزي صراحةً: تُقرأ أحياناً من داخل سياق مشترك
 * (شاشة الفوترة عنده)، و`DB::table()` هناك تسأل قاعدته هو.
 */
final class PlatformSettings
{
    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        // قبل الهجرة: نعمل بالافتراضيات ولا نُسقط الشاشة
        if (! Schema::connection($this->connection())->hasTable('platform_settings')) {
            return $this->cache = [];
        }

        $rows = DB::connection($this->connection())->table('platform_settings')->get();
        $out = [];

        foreach ($rows as $row) {
            $out[$row->key] = $row->is_encrypted ? $this->decrypt($row->value) : $row->value;
        }

        return $this->cache = $out;
    }

    public function set(string $key, mixed $value, bool $encrypted = false): void
    {
        DB::connection($this->connection())->table('platform_settings')->updateOrInsert(
            ['key' => $key],
            [
                'value' => $value === null ? null : ($encrypted ? Crypt::encryptString((string) $value) : (string) $value),
                'is_encrypted' => $encrypted,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $this->cache = null;
    }

    public function forget(string $key): void
    {
        DB::connection($this->connection())->table('platform_settings')->where('key', $key)->delete();
        $this->cache = null;
    }

    /** هل الطريقة مفعّلة ومكتملة البيانات؟ */
    public function methodReady(string $method): bool
    {
        if (! filled($this->get($method.'.enabled'))) {
            return false;
        }

        return match ($method) {
            'instapay' => filled($this->get('instapay.address')),
            'bank' => filled($this->get('bank.account_number')) || filled($this->get('bank.iban')),
            'wallet' => filled($this->get('wallet.number')),
            default => false,
        };
    }

    private function decrypt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException|Throwable) {
            // مفتاح التطبيق تغيّر: نُعيد فارغاً ولا نُسقط الشاشة
            return null;
        }
    }

    private function connection(): string
    {
        return (string) config('tenancy.database.central_connection', config('database.default'));
    }
}
