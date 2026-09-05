<?php

declare(strict_types=1);

namespace App\Core\Access;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Throwable;

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
     * تُقرأ مرّةً في الطلب.
     *
     * `Roles` مفردةٌ في الحاوية، والحراسة تُنادى عشرات المرّات في
     * الصفحة الواحدة.
     *
     * @var array<string, list<string>>|null
     */
    private ?array $overrides = null;

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

    /**
     * @return list<string>
     */
    public function abilitiesFor(string $role): array
    {
        /*
         | صاحب المنصّة قبل كل شيء.
         |
         | ولا يُقرأ له صفٌّ في القاعدة: مشتركٌ ينزع بالخطأ صلاحيةً من
         | صاحب المنصّة يُقفل نفسه خارج لوحته، ولا بابَ إلا نحن.
         */
        if ($role === 'owner') {
            return Ability::all();
        }

        return $this->overrides()[$role] ?? array_values((array) config('roles.abilities.'.$role, []));
    }

    /**
     * ما عدّله المشترك — يعلو على `config/roles.php`.
     *
     * ويُقرأ مرّةً في الطلب: الحراسة تُنادى عشرات المرّات في الصفحة
     * الواحدة، واستعلامٌ لكلٍّ منها يجعل اللوحة تزحف.
     *
     * @return array<string, list<string>>
     */
    private function overrides(): array
    {
        if ($this->overrides !== null) {
            return $this->overrides;
        }

        if (tenant() === null) {
            return $this->overrides = [];
        }

        try {
            return $this->overrides = DB::table('role_abilities')->get()
                ->mapWithKeys(function (object $row): array {
                    $abilities = json_decode((string) $row->abilities, true);

                    /*
                     | ولا يُقبَل إلا ما يعرفه الكود.
                     |
                     | صلاحيةٌ حُذفت من `Ability` وبقيت في صفٍّ قديم
                     | تُمنح لاسمٍ لا يحرسه شيء — وهي لا تفتح باباً،
                     | لكنّها تُظهر في الشاشة صلاحيةً لا وجود لها.
                     */
                    return [(string) $row->role => array_values(array_intersect(
                        is_array($abilities) ? $abilities : [],
                        Ability::all(),
                    ))];
                })->all();
        } catch (Throwable) {
            // مشتركٌ لم تصله الهجرة بعد يعمل بتوزيع الإعدادات
            return $this->overrides = [];
        }
    }

    /** يُنسى المقروء بعد كل تعديل */
    public function forgetOverrides(): void
    {
        $this->overrides = null;
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
