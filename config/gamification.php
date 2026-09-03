<?php

declare(strict_types=1);

/*
 | قواعد النقاط. القائمة مغلقة عمداً:
 | لا يصل اسم قاعدة من المستخدم فيمنح نفسه نقاطاً.
 |
 | لكل قاعدة:
 |   points → النقاط الافتراضية (يعدّلها المشترك من الإعدادات)
 |   label  → اسمها في اللوحة وفي سجلّ الطالب
 |   once   → تُمنح مرة واحدة لكل مصدر (درس، كورس، اختبار)
 |   daily  → أقصى مرات في اليوم، صفر يعني بلا حد
 */

return [
    'rules' => [
        'lesson.completed' => ['points' => 10, 'label' => 'إتمام درس', 'once' => true, 'daily' => 0],
        'quiz.passed' => ['points' => 25, 'label' => 'اجتياز اختبار', 'once' => true, 'daily' => 0],
        'quiz.perfect' => ['points' => 15, 'label' => 'درجة كاملة', 'once' => true, 'daily' => 0],
        'assignment.submitted' => ['points' => 15, 'label' => 'تسليم واجب', 'once' => true, 'daily' => 0],
        'course.completed' => ['points' => 100, 'label' => 'إتمام كورس', 'once' => true, 'daily' => 0],
        'certificate.earned' => ['points' => 50, 'label' => 'الحصول على شهادة', 'once' => true, 'daily' => 0],
        'review.written' => ['points' => 20, 'label' => 'كتابة تقييم', 'once' => true, 'daily' => 0],
        'question.asked' => ['points' => 5, 'label' => 'طرح سؤال', 'once' => false, 'daily' => 3],
        'answer.accepted' => ['points' => 30, 'label' => 'ردّ قُبل كإجابة', 'once' => true, 'daily' => 0],
        'answer.written' => ['points' => 5, 'label' => 'مساعدة زميل', 'once' => false, 'daily' => 5],
        'streak.day' => ['points' => 5, 'label' => 'يوم متتابع', 'once' => false, 'daily' => 1],
        'streak.week' => ['points' => 40, 'label' => 'أسبوع كامل بلا انقطاع', 'once' => false, 'daily' => 1],
        'profile.completed' => ['points' => 15, 'label' => 'إكمال الملف الشخصي', 'once' => true, 'daily' => 0],
    ],

    /*
     | الشارات الافتراضية — تُنشأ للمشترك عند التهيئة ويحرّرها كما يشاء.
     | condition_rule + condition_value: كم مرة تحقّقت القاعدة.
     */
    'badges' => [
        ['key' => 'first-lesson', 'icon' => '◐', 'tone' => 'primary', 'rule' => 'lesson.completed', 'value' => 1,
            'name' => ['ar' => 'الخطوة الأولى', 'en' => 'First step'],
            'description' => ['ar' => 'أتممت أول درس.', 'en' => 'Completed your first lesson.']],

        ['key' => 'ten-lessons', 'icon' => '◑', 'tone' => 'primary', 'rule' => 'lesson.completed', 'value' => 10,
            'name' => ['ar' => 'مواظب', 'en' => 'Consistent'],
            'description' => ['ar' => 'أتممت عشرة دروس.', 'en' => 'Completed ten lessons.']],

        ['key' => 'first-course', 'icon' => '◈', 'tone' => 'success', 'rule' => 'course.completed', 'value' => 1,
            'name' => ['ar' => 'أنهيت كورساً', 'en' => 'Course finisher'],
            'description' => ['ar' => 'أتممت كورساً كاملاً.', 'en' => 'Finished a full course.']],

        ['key' => 'quiz-ace', 'icon' => '★', 'tone' => 'accent', 'rule' => 'quiz.perfect', 'value' => 3,
            'name' => ['ar' => 'دقيق', 'en' => 'Precise'],
            'description' => ['ar' => 'ثلاث درجات كاملة في الاختبارات.', 'en' => 'Three perfect quiz scores.']],

        ['key' => 'helper', 'icon' => '☗', 'tone' => 'info', 'rule' => 'answer.accepted', 'value' => 5,
            'name' => ['ar' => 'سند الزملاء', 'en' => 'Helper'],
            'description' => ['ar' => 'قُبلت خمس من إجاباتك.', 'en' => 'Five of your answers were accepted.']],

        ['key' => 'week-streak', 'icon' => '⚡', 'tone' => 'warning', 'rule' => 'streak.week', 'value' => 1,
            'name' => ['ar' => 'أسبوع بلا انقطاع', 'en' => 'Week streak'],
            'description' => ['ar' => 'سبعة أيام متتابعة من التعلّم.', 'en' => 'Seven days in a row.']],

        ['key' => 'certified', 'icon' => '◆', 'tone' => 'success', 'rule' => 'certificate.earned', 'value' => 3,
            'name' => ['ar' => 'ثلاث شهادات', 'en' => 'Triple certified'],
            'description' => ['ar' => 'حصلت على ثلاث شهادات.', 'en' => 'Earned three certificates.']],
    ],
];
