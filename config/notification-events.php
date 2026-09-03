<?php

declare(strict_types=1);

/*
 | كتالوج أحداث الإشعارات. القائمة مغلقة عمداً:
 | لا يصل مفتاح حدث من المستخدم إلى مُرسِل ليُنفَّذ.
 |
 | لكل حدث:
 |   group     → قسمه في مصفوفة الإشعارات
 |   label     → اسمه للمشترك
 |   audience  → من يستقبله: student · instructor · guardian · staff · customer
 |   channels  → القنوات المسموح بها لهذا الحدث
 |   default   → القنوات المفعّلة ابتداءً
 |   variables → المتغيّرات المتاحة في قالبه — وما عداها لا يُستبدل
 |   module    → لا يظهر الحدث إن كان الموديول مطفأً
 */

$person = ['name', 'first_name', 'email', 'site_name', 'site_url'];

return [

    // ---------- الحساب ----------
    'account.welcome' => [
        'group' => 'account', 'label' => 'ترحيب بحساب جديد', 'audience' => 'student',
        'channels' => ['mail', 'whatsapp', 'sms', 'database'], 'default' => ['mail', 'database'],
        'variables' => [...$person, 'login_url'],
    ],
    'account.verify_email' => [
        'group' => 'account', 'label' => 'تأكيد البريد', 'audience' => 'student',
        'channels' => ['mail'], 'default' => ['mail'],
        'variables' => [...$person, 'verify_url', 'expires_in'],
    ],
    'account.reset_password' => [
        'group' => 'account', 'label' => 'استعادة كلمة المرور', 'audience' => 'student',
        'channels' => ['mail', 'sms'], 'default' => ['mail'],
        'variables' => [...$person, 'reset_url', 'expires_in'],
    ],
    'account.password_changed' => [
        'group' => 'account', 'label' => 'تغيّرت كلمة المرور', 'audience' => 'student',
        'channels' => ['mail', 'database'], 'default' => ['mail', 'database'],
        'variables' => [...$person, 'changed_at', 'ip'],
    ],
    'account.new_login' => [
        'group' => 'account', 'label' => 'دخول من جهاز جديد', 'audience' => 'student',
        'channels' => ['mail', 'database'], 'default' => ['mail'],
        'variables' => [...$person, 'device', 'ip', 'at'],
    ],

    // ---------- التعلّم ----------
    'lms.enrolled' => [
        'group' => 'lms', 'label' => 'تسجيل في كورس', 'audience' => 'student', 'module' => 'lms',
        'channels' => ['mail', 'whatsapp', 'database', 'web_push'], 'default' => ['mail', 'database'],
        'variables' => [...$person, 'course_title', 'course_url', 'instructor_name'],
    ],
    'lms.lesson_ready' => [
        'group' => 'lms', 'label' => 'درس جديد في كورسك', 'audience' => 'student', 'module' => 'lms',
        'channels' => ['mail', 'database', 'web_push'], 'default' => ['database'],
        'variables' => [...$person, 'course_title', 'lesson_title', 'lesson_url'],
    ],
    'lms.course_completed' => [
        'group' => 'lms', 'label' => 'إتمام كورس', 'audience' => 'student', 'module' => 'lms',
        'channels' => ['mail', 'whatsapp', 'database'], 'default' => ['mail', 'database'],
        'variables' => [...$person, 'course_title', 'certificate_url'],
    ],
    'lms.certificate_issued' => [
        'group' => 'lms', 'label' => 'إصدار شهادة', 'audience' => 'student', 'module' => 'certificates',
        'channels' => ['mail', 'whatsapp', 'database'], 'default' => ['mail', 'database'],
        'variables' => [...$person, 'course_title', 'certificate_code', 'certificate_url'],
    ],
    'lms.quiz_graded' => [
        'group' => 'lms', 'label' => 'تصحيح اختبار', 'audience' => 'student', 'module' => 'quizzes',
        'channels' => ['mail', 'database', 'web_push'], 'default' => ['database'],
        'variables' => [...$person, 'quiz_title', 'score', 'passed', 'attempt_url'],
    ],
    'lms.assignment_graded' => [
        'group' => 'lms', 'label' => 'تصحيح واجب', 'audience' => 'student', 'module' => 'assignments',
        'channels' => ['mail', 'database'], 'default' => ['mail', 'database'],
        'variables' => [...$person, 'assignment_title', 'score', 'feedback', 'submission_url'],
    ],
    'lms.assignment_submitted' => [
        'group' => 'lms', 'label' => 'واجب بانتظار التصحيح', 'audience' => 'instructor', 'module' => 'assignments',
        'channels' => ['mail', 'database'], 'default' => ['database'],
        'variables' => [...$person, 'student_name', 'assignment_title', 'grading_url'],
    ],
    'lms.idle_reminder' => [
        'group' => 'lms', 'label' => 'تذكير بعد خمول', 'audience' => 'student', 'module' => 'lms',
        'channels' => ['mail', 'whatsapp', 'database'], 'default' => ['mail'],
        'variables' => [...$person, 'course_title', 'course_url', 'idle_days', 'progress'],
    ],
    'lms.access_expiring' => [
        'group' => 'lms', 'label' => 'قرب انتهاء صلاحية الكورس', 'audience' => 'student', 'module' => 'lms',
        'channels' => ['mail', 'whatsapp', 'database'], 'default' => ['mail'],
        'variables' => [...$person, 'course_title', 'course_url', 'days_left'],
    ],

    // ---------- التجارة ----------
    'commerce.order_placed' => [
        'group' => 'commerce', 'label' => 'تأكيد طلب', 'audience' => 'customer', 'module' => 'commerce',
        'channels' => ['mail', 'whatsapp', 'sms', 'database'], 'default' => ['mail', 'database'],
        'variables' => [...$person, 'order_number', 'order_total', 'order_url', 'items_count'],
    ],
    'commerce.payment_received' => [
        'group' => 'commerce', 'label' => 'تأكيد دفع', 'audience' => 'customer', 'module' => 'commerce',
        'channels' => ['mail', 'whatsapp', 'sms', 'database'], 'default' => ['mail', 'whatsapp', 'database'],
        'variables' => [...$person, 'order_number', 'amount', 'method', 'invoice_url'],
    ],
    'commerce.payment_failed' => [
        'group' => 'commerce', 'label' => 'فشل الدفع', 'audience' => 'customer', 'module' => 'commerce',
        'channels' => ['mail', 'whatsapp', 'database'], 'default' => ['mail'],
        'variables' => [...$person, 'order_number', 'amount', 'retry_url', 'reason'],
    ],
    'commerce.order_refunded' => [
        'group' => 'commerce', 'label' => 'تنفيذ استرداد', 'audience' => 'customer', 'module' => 'commerce',
        'channels' => ['mail', 'database'], 'default' => ['mail', 'database'],
        'variables' => [...$person, 'order_number', 'amount', 'reason'],
    ],
    'commerce.abandoned_cart' => [
        'group' => 'commerce', 'label' => 'سلة متروكة', 'audience' => 'customer', 'module' => 'commerce',
        'channels' => ['mail', 'whatsapp', 'database'], 'default' => ['mail'],
        'variables' => [...$person, 'cart_url', 'items_count', 'cart_total', 'coupon_code'],
    ],
    'commerce.wallet_topped_up' => [
        'group' => 'commerce', 'label' => 'شحن المحفظة', 'audience' => 'customer', 'module' => 'commerce',
        'channels' => ['mail', 'whatsapp', 'database'], 'default' => ['database'],
        'variables' => [...$person, 'amount', 'balance', 'wallet_url'],
    ],
    'commerce.payout_sent' => [
        'group' => 'commerce', 'label' => 'تحويل عمولة المدرّس', 'audience' => 'instructor', 'module' => 'payouts',
        'channels' => ['mail', 'database'], 'default' => ['mail', 'database'],
        'variables' => [...$person, 'amount', 'period', 'method', 'reference'],
    ],

    // ---------- الخدمات ----------
    'services.booking_placed' => [
        'group' => 'services', 'label' => 'استلام طلب حجز', 'audience' => 'customer', 'module' => 'bookings',
        'channels' => ['mail', 'whatsapp', 'sms', 'database'], 'default' => ['mail', 'database'],
        'variables' => [...$person, 'service_title', 'booking_reference', 'booking_url', 'booking_at'],
    ],
    'services.booking_confirmed' => [
        'group' => 'services', 'label' => 'تأكيد الحجز', 'audience' => 'customer', 'module' => 'bookings',
        'channels' => ['mail', 'whatsapp', 'sms', 'database'], 'default' => ['mail', 'whatsapp', 'database'],
        'variables' => [...$person, 'service_title', 'booking_reference', 'booking_at', 'meeting_url'],
    ],
    'services.booking_reminder' => [
        'group' => 'services', 'label' => 'تذكير قبل الموعد', 'audience' => 'customer', 'module' => 'bookings',
        'channels' => ['mail', 'whatsapp', 'sms', 'web_push'], 'default' => ['whatsapp', 'mail'],
        'variables' => [...$person, 'service_title', 'booking_at', 'meeting_url', 'hours_left'],
    ],
    'services.booking_cancelled' => [
        'group' => 'services', 'label' => 'إلغاء حجز', 'audience' => 'customer', 'module' => 'bookings',
        'channels' => ['mail', 'whatsapp', 'database'], 'default' => ['mail', 'database'],
        'variables' => [...$person, 'service_title', 'booking_reference', 'reason'],
    ],
    'services.booking_for_provider' => [
        'group' => 'services', 'label' => 'حجز جديد لمقدّم الخدمة', 'audience' => 'staff', 'module' => 'bookings',
        'channels' => ['mail', 'whatsapp', 'database'], 'default' => ['mail', 'database'],
        'variables' => [...$person, 'service_title', 'customer_name', 'booking_at', 'booking_url'],
    ],

    // ---------- السنتر ----------
    'center.absence' => [
        'group' => 'center', 'label' => 'غياب الطالب', 'audience' => 'guardian', 'module' => 'attendance',
        'channels' => ['whatsapp', 'sms', 'mail', 'database'], 'default' => ['whatsapp'],
        'variables' => [...$person, 'student_name', 'group_name', 'session_at', 'absence_rate'],
    ],
    'center.late' => [
        'group' => 'center', 'label' => 'تأخّر الطالب', 'audience' => 'guardian', 'module' => 'attendance',
        'channels' => ['whatsapp', 'sms', 'database'], 'default' => ['whatsapp'],
        'variables' => [...$person, 'student_name', 'group_name', 'late_minutes'],
    ],
    'center.fee_due' => [
        'group' => 'center', 'label' => 'قسط مستحق', 'audience' => 'guardian', 'module' => 'center-finance',
        'channels' => ['whatsapp', 'sms', 'mail', 'database'], 'default' => ['whatsapp', 'mail'],
        'variables' => [...$person, 'student_name', 'amount', 'due_date', 'invoice_number'],
    ],
    'center.fee_overdue' => [
        'group' => 'center', 'label' => 'قسط متأخّر', 'audience' => 'guardian', 'module' => 'center-finance',
        'channels' => ['whatsapp', 'sms', 'mail'], 'default' => ['whatsapp'],
        'variables' => [...$person, 'student_name', 'amount', 'days_late', 'late_fee'],
    ],
    'center.payment_receipt' => [
        'group' => 'center', 'label' => 'إيصال تحصيل', 'audience' => 'guardian', 'module' => 'center-finance',
        'channels' => ['whatsapp', 'mail', 'database'], 'default' => ['whatsapp'],
        'variables' => [...$person, 'student_name', 'amount', 'receipt_number', 'balance'],
    ],
    'center.session_cancelled' => [
        'group' => 'center', 'label' => 'إلغاء حصة', 'audience' => 'guardian', 'module' => 'center',
        'channels' => ['whatsapp', 'sms', 'database'], 'default' => ['whatsapp'],
        'variables' => [...$person, 'group_name', 'session_at', 'reason'],
    ],
    'center.grade_published' => [
        'group' => 'center', 'label' => 'رصد درجة', 'audience' => 'guardian', 'module' => 'center',
        'channels' => ['whatsapp', 'mail', 'database'], 'default' => ['database'],
        'variables' => [...$person, 'student_name', 'exam_title', 'score', 'max_score'],
    ],
    'center.monthly_report' => [
        'group' => 'center', 'label' => 'التقرير الشهري', 'audience' => 'guardian', 'module' => 'center',
        'channels' => ['whatsapp', 'mail'], 'default' => ['mail'],
        'variables' => [...$person, 'student_name', 'month', 'attendance_rate', 'report_url'],
    ],

    // ---------- المحتوى والمجتمع ----------
    'content.form_submitted' => [
        'group' => 'content', 'label' => 'رسالة من نموذج', 'audience' => 'staff', 'module' => 'forms',
        'channels' => ['mail', 'database'], 'default' => ['mail', 'database'],
        'variables' => [...$person, 'form_name', 'summary', 'submission_url'],
    ],
    'content.comment_pending' => [
        'group' => 'content', 'label' => 'تعليق بانتظار المراجعة', 'audience' => 'staff', 'module' => 'blog',
        'channels' => ['mail', 'database'], 'default' => ['database'],
        'variables' => [...$person, 'post_title', 'author_name', 'excerpt', 'moderation_url'],
    ],
    'content.comment_replied' => [
        'group' => 'content', 'label' => 'ردّ على تعليقك', 'audience' => 'student', 'module' => 'blog',
        'channels' => ['mail', 'database'], 'default' => ['database'],
        'variables' => [...$person, 'post_title', 'replier_name', 'excerpt', 'post_url'],
    ],
    'community.question_asked' => [
        'group' => 'community', 'label' => 'سؤال إلى المدرّس', 'audience' => 'instructor', 'module' => 'community',
        'channels' => ['mail', 'database', 'web_push'], 'default' => ['database'],
        'variables' => [...$person, 'student_name', 'course_title', 'excerpt', 'thread_url'],
    ],
    'community.question_answered' => [
        'group' => 'community', 'label' => 'ردّ على سؤالك', 'audience' => 'student', 'module' => 'community',
        'channels' => ['mail', 'database', 'web_push'], 'default' => ['mail', 'database'],
        'variables' => [...$person, 'course_title', 'answerer_name', 'excerpt', 'thread_url'],
    ],
    'community.review_requested' => [
        'group' => 'community', 'label' => 'طلب تقييم بعد الإتمام', 'audience' => 'student', 'module' => 'lms',
        'channels' => ['mail', 'database'], 'default' => ['mail'],
        'variables' => [...$person, 'course_title', 'review_url'],
    ],

    // ---------- التحفيز والمجتمع ----------
    'gamification.badge_earned' => [
        'group' => 'community', 'label' => 'شارة جديدة', 'audience' => 'student', 'module' => 'gamification',
        'channels' => ['mail', 'database', 'web_push'], 'default' => ['database'],
        'variables' => [...$person, 'badge_name', 'badge_description'],
    ],
    'gamification.level_up' => [
        'group' => 'community', 'label' => 'مستوى جديد', 'audience' => 'student', 'module' => 'gamification',
        'channels' => ['mail', 'database', 'web_push'], 'default' => ['database'],
        'variables' => [...$person, 'level', 'points'],
    ],

    // ---------- التسويق بالعمولة ----------
    'affiliate.conversion' => [
        'group' => 'affiliate', 'label' => 'تحويل ناجح', 'audience' => 'staff', 'module' => 'affiliates',
        'channels' => ['mail', 'database'], 'default' => ['mail', 'database'],
        'variables' => [...$person, 'amount', 'commission', 'order_number'],
    ],
    'affiliate.payout' => [
        'group' => 'affiliate', 'label' => 'صرف عمولة المسوّق', 'audience' => 'staff', 'module' => 'affiliates',
        'channels' => ['mail', 'database'], 'default' => ['mail', 'database'],
        'variables' => [...$person, 'amount', 'period', 'method'],
    ],
];
