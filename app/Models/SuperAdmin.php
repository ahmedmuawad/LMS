<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * فريقنا نحن على القاعدة المركزية — لا علاقة له بمستخدمي المشتركين.
 */
final class SuperAdmin extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    public function getConnectionName(): ?string
    {
        // يبقى على القاعدة المركزية حتى داخل سياق مشترك
        return config('tenancy.database.central_connection');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function canAccessSuperPanel(): bool
    {
        return $this->is_active;
    }
}
