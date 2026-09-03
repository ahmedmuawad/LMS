<?php

declare(strict_types=1);

use App\Core\Access\Ability;

/*
 | من يملك ماذا.
 |
 | المرونة هنا في توزيع الصلاحيات لا في تعريفها: الأسماء ثوابت في
 | الكود (Ability)، والتوزيع قابل للتعديل — وهذا يمنع أن يفتح تعديلُ
 | صفٍّ في قاعدة البيانات خزنةَ المنصّة.
 |
 | `owner` غير مذكور عمداً: صاحب المنصّة يملك كل شيء بحكم كونه صاحبها،
 | ولو عُدّدت صلاحياته لنُسي منها واحدة عند إضافة كل صلاحية جديدة.
 */

return [

    /* من يدخل اللوحة أصلاً — والطالب وولي الأمر لهما بواباتهما. */
    'panel' => ['owner', 'admin', 'instructor', 'staff'],

    'abilities' => [

        /*
         | مدير المنصّة: كل شيء إلا فوترة الاشتراك نفسه.
         | الاشتراك عقد بين صاحب المنصّة وبيننا، ولا يُعدّله موظّف.
         */
        'admin' => array_values(array_diff(Ability::all(), [Ability::BILLING_MANAGE])),

        /*
         | المدرّس: يرى ما يملكه وحده — كورساته وطلابه وأرباحه.
         | الصلاحية تفتح الشاشة، وحصر النطاق (Scope) يحدّد الصفوف؛
         | وبغير الثاني تصير الأولى بلا معنى.
         */
        'instructor' => [
            Ability::PANEL,
            Ability::DASHBOARD,

            Ability::COURSES_VIEW,
            Ability::COURSES_MANAGE,
            Ability::CURRICULUM_MANAGE,
            Ability::LESSONS_MANAGE,
            Ability::QUIZZES_MANAGE,
            Ability::ASSIGNMENTS_MANAGE,
            Ability::ENROLLMENTS_VIEW,
            Ability::CERTIFICATES_VIEW,
            Ability::GRADING,

            Ability::SERVICES_MANAGE,
            Ability::BOOKINGS_MANAGE,

            Ability::REVIEWS_MODERATE,
            Ability::PAYOUTS_VIEW,

            Ability::STUDENTS_VIEW,
            Ability::DISCUSSIONS_MODERATE,
            Ability::ANNOUNCEMENTS_MANAGE,
            Ability::STATISTICS_VIEW,
            Ability::EARNINGS_VIEW,

            /*
             | لا `REPORTS_VIEW`.
             |
             | شاشة التقارير أداة صاحب المنصّة: تبويبها المالي يجمع
             | إيراد المنصّة كلّه، وتبويبها التسويقي يجمع مسوّقيها —
             | ولا معنى لحصر ذلك بمدرّس. إحصاءاته له في لوحته هو.
             */
        ],

        /*
         | موظّف السنتر: التشغيل اليومي — حضور وأقساط وخزنة ومجموعات.
         | لا يرى إعدادات ولا مفاتيح ولا إيراد المنصّة.
         */
        'staff' => [
            Ability::PANEL,
            Ability::DASHBOARD,

            Ability::CENTER_VIEW,
            Ability::CENTER_MANAGE,
            Ability::ATTENDANCE_TAKE,
            Ability::FEES_COLLECT,
            Ability::CASHBOX_MANAGE,

            Ability::ENROLLMENTS_VIEW,
            Ability::ENROLLMENTS_MANAGE,
            Ability::USERS_VIEW,
            Ability::ORDERS_VIEW,
            Ability::CERTIFICATES_VIEW,
            Ability::STUDENTS_VIEW,
        ],

        // الطالب وولي الأمر: لا صلاحية لوحة، ولهما بواباتهما العامة
        'student' => [],
        'guardian' => [],
    ],

    /*
     | أسماء الأدوار للعرض — تُترجَم عند الاستعمال.
     */
    'labels' => [
        'owner' => 'صاحب المنصّة',
        'admin' => 'مدير',
        'instructor' => 'مدرّس',
        'staff' => 'موظّف',
        'student' => 'طالب',
        'guardian' => 'ولي أمر',
    ],
];
