<?php

declare(strict_types=1);

namespace App\Core\Auth;

use Illuminate\Validation\Rules\Password;

/**
 * سياسة كلمة المرور كما ضبطها المشترك.
 *
 * تُبنى من الإعدادات لا تُكتب في كل نموذج: سياسة مكرّرة في خمس
 * شاشات تعني خمس فرص لأن تُشدّد واحدة وتُنسى الأربع — وأضعفها
 * هو ما يحدّد أمان الحساب فعلاً.
 */
final class PasswordPolicy
{
    /** @return list<mixed> */
    public static function rules(): array
    {
        $min = max(8, (int) setting('users.password_min', 8));
        $requires = (array) setting('users.password_requires', ['letters', 'numbers']);

        $rule = Password::min($min);

        if (in_array('letters', $requires, true)) {
            $rule = $rule->letters();
        }

        if (in_array('mixed', $requires, true)) {
            $rule = $rule->mixedCase();
        }

        if (in_array('numbers', $requires, true)) {
            $rule = $rule->numbers();
        }

        if (in_array('symbols', $requires, true)) {
            $rule = $rule->symbols();
        }

        /*
         | «هل ظهرت في تسريب؟» تُفحص إن طلبها المشترك.
         |
         | تستدعي خدمة خارجية، فتُترك خياراً لا افتراضاً: موقع بلا
         | إنترنت صادر يجب أن يبقى قادراً على تسجيل مستخدم.
         */
        if (in_array('uncompromised', $requires, true)) {
            $rule = $rule->uncompromised();
        }

        return ['required', 'string', 'confirmed', $rule];
    }

    /** نصّ يشرح السياسة للمستخدم قبل أن يخطئ لا بعده. */
    public static function hint(): string
    {
        $min = max(8, (int) setting('users.password_min', 8));
        $requires = (array) setting('users.password_requires', ['letters', 'numbers']);

        $parts = [__(':count محرفاً على الأقل', ['count' => $min])];

        $labels = [
            'letters' => __('حروف'),
            'mixed' => __('حرف كبير وصغير'),
            'numbers' => __('أرقام'),
            'symbols' => __('رمز'),
        ];

        foreach ($labels as $key => $label) {
            if (in_array($key, $requires, true)) {
                $parts[] = $label;
            }
        }

        return implode(' · ', $parts);
    }
}
