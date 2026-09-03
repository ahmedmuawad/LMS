<?php

declare(strict_types=1);

namespace App\Core\Access;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * الجواب الوحيد على «هل يستطيع؟».
 *
 * كل حراسة في المشروع تنتهي إلى هنا: بوابات Laravel، وحراس المسارات،
 * والقوائم، وشاشات الإعدادات. وجود مصدر واحد للحكم هو ما يمنع أن
 * تُسدّ ثغرة في مكان وتبقى مفتوحة في مكانين.
 */
final class Roles
{
    /**
     * `Authenticatable` لا `User` عمداً.
     *
     * اللوحة العليا تُصادِق بنموذج `SuperAdmin` وحارس آخر؛ ومصفوفة
     * أدوار المشترك لا تنطبق عليه، فيُردّ بالمنع هنا ويحرسه حارسه.
     * التضييق إلى `User` كان يُسقط لوحتنا نحن بـ TypeError.
     */
    public function allows(?Authenticatable $user, string $ability): bool
    {
        if (! $user instanceof User || $user->status !== 'active') {
            return false;
        }

        // صاحب المنصّة يملك كل شيء بحكم كونه صاحبها
        if ($user->role === 'owner') {
            return true;
        }

        return in_array($ability, $this->abilitiesFor((string) $user->role), true);
    }

    /** @return list<string> */
    public function abilitiesFor(string $role): array
    {
        if ($role === 'owner') {
            return Ability::all();
        }

        return array_values((array) config('roles.abilities.'.$role, []));
    }

    public function mayEnterPanel(?Authenticatable $user): bool
    {
        return $user instanceof User
            && $user->status === 'active'
            && in_array((string) $user->role, (array) config('roles.panel', []), true);
    }

    /**
     * هل هذا الدور محصور بما يملكه؟
     *
     * سؤال عن الدور لا عن الصلاحية: المدرّس محصور في كل ما يفعله،
     * ومدير المنصّة غير محصور في شيء.
     */
    public function isScoped(?Authenticatable $user): bool
    {
        return $user instanceof User && (string) $user->role === 'instructor';
    }

    public function label(string $role): string
    {
        return __((string) config('roles.labels.'.$role, $role));
    }

    /** @return array<string, string> الأدوار التي يجوز إسنادها من اللوحة */
    public function assignable(?Authenticatable $actor = null): array
    {
        $roles = (array) config('roles.labels', []);

        // لا يُنشئ أحدٌ صاحبَ منصّة ثانياً من الشاشة
        unset($roles['owner']);

        return collect($roles)->map(fn (string $label): string => __($label))->all();
    }
}
