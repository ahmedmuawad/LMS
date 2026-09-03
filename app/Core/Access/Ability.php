<?php

declare(strict_types=1);

namespace App\Core\Access;

/**
 * كل ما يمكن فعله في اللوحة — قائمة مغلقة.
 *
 * الصلاحية اسم ثابت في الكود لا نصّ في قاعدة البيانات: مصفوفة
 * صلاحيات محرّرة من الشاشة تبدو مرنة، ثم يكتشف أحدهم أن تعديل صفّ
 * فيها يفتح خزنة المنصّة. المرونة هنا في **من** يملك الصلاحية لا في
 * **ما** الصلاحيات.
 */
final class Ability
{
    // ---------- اللوحة ----------
    public const PANEL = 'panel.access';

    public const DASHBOARD = 'dashboard.view';

    // ---------- التعليم ----------
    public const COURSES_VIEW = 'courses.view';

    public const COURSES_MANAGE = 'courses.manage';

    public const CURRICULUM_MANAGE = 'curriculum.manage';

    public const LESSONS_MANAGE = 'lessons.manage';

    public const QUIZZES_MANAGE = 'quizzes.manage';

    public const ASSIGNMENTS_MANAGE = 'assignments.manage';

    public const ENROLLMENTS_VIEW = 'enrollments.view';

    public const ENROLLMENTS_MANAGE = 'enrollments.manage';

    public const CERTIFICATES_VIEW = 'certificates.view';

    public const CERTIFICATES_MANAGE = 'certificates.manage';

    public const GRADING = 'grading.handle';

    public const TAXONOMIES_MANAGE = 'taxonomies.manage';

    // ---------- التجارة ----------
    public const ORDERS_VIEW = 'orders.view';

    public const ORDERS_MANAGE = 'orders.manage';

    public const PRODUCTS_MANAGE = 'products.manage';

    public const COUPONS_MANAGE = 'coupons.manage';

    public const REFUNDS_HANDLE = 'refunds.handle';

    public const CODES_MANAGE = 'codes.manage';

    public const PAYOUTS_VIEW = 'payouts.view';

    public const PAYOUTS_MANAGE = 'payouts.manage';

    public const FINANCE_VIEW = 'finance.view';

    // ---------- الخدمات ----------
    public const SERVICES_MANAGE = 'services.manage';

    public const BOOKINGS_MANAGE = 'bookings.manage';

    // ---------- السنتر ----------
    public const CENTER_VIEW = 'center.view';

    public const CENTER_MANAGE = 'center.manage';

    public const ATTENDANCE_TAKE = 'attendance.take';

    public const FEES_COLLECT = 'fees.collect';

    public const CASHBOX_MANAGE = 'cashbox.manage';

    // ---------- المحتوى ----------
    public const CONTENT_MANAGE = 'content.manage';

    public const MEDIA_MANAGE = 'media.manage';

    public const COMMENTS_MODERATE = 'comments.moderate';

    public const REVIEWS_MODERATE = 'reviews.moderate';

    // ---------- النمو ----------
    public const AFFILIATES_MANAGE = 'affiliates.manage';

    public const CAMPAIGNS_MANAGE = 'campaigns.manage';

    public const NOTIFICATIONS_MANAGE = 'notifications.manage';

    public const GAMIFICATION_MANAGE = 'gamification.manage';

    // ---------- الناس ----------
    public const USERS_VIEW = 'users.view';

    public const USERS_MANAGE = 'users.manage';

    public const INSTRUCTORS_MANAGE = 'instructors.manage';

    // ---------- النظام ----------
    public const SETTINGS_MANAGE = 'settings.manage';

    public const BILLING_MANAGE = 'billing.manage';

    public const MODULES_MANAGE = 'modules.manage';

    public const APPEARANCE_MANAGE = 'appearance.manage';

    public const REPORTS_VIEW = 'reports.view';

    public const AUDIT_VIEW = 'audit.view';

    /**
     * كل الصلاحيات المعرَّفة — تُقرأ من ثوابت الصنف نفسه، فلا تُنسى
     * واحدة في قائمة موازية.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }

    /**
     * الصلاحيات التي لا يجوز لصاحبها إلا رؤية ما يملكه.
     *
     * وجودها هنا لا يمنح شيئاً؛ إنما يذكّر أن الصلاحية وحدها لا تكفي
     * وأن حصر النطاق واجب مع كل واحدة منها.
     *
     * @return list<string>
     */
    public static function scoped(): array
    {
        return [
            self::COURSES_VIEW, self::COURSES_MANAGE, self::CURRICULUM_MANAGE,
            self::LESSONS_MANAGE, self::QUIZZES_MANAGE, self::ASSIGNMENTS_MANAGE,
            self::ENROLLMENTS_VIEW, self::CERTIFICATES_VIEW, self::GRADING,
            self::PAYOUTS_VIEW, self::SERVICES_MANAGE, self::BOOKINGS_MANAGE,
            self::REVIEWS_MODERATE, self::REPORTS_VIEW,
        ];
    }
}
