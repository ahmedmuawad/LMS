<?php

declare(strict_types=1);
use App\Core\Admin\Resources\Central\InvoiceResource;
use App\Core\Admin\Resources\Central\SubscriptionResource;
use App\Core\Admin\Resources\Central\TenantResource;
use App\Core\Admin\Resources\Lms\CertificateResource;
use App\Core\Admin\Resources\Lms\CourseResource;
use App\Core\Admin\Resources\Lms\EnrollmentResource;
use App\Core\Admin\Resources\Lms\LessonResource;
use App\Core\Admin\Resources\Lms\QuestionResource;
use App\Core\Admin\Resources\Lms\QuizResource;
use App\Core\Admin\Resources\Lms\TaxonomyResource;
use App\Core\Admin\Resources\UserResource;

/*
 | خريطة الموارد لكل سياق. القائمة مغلقة عمداً:
 | لا يصل مفتاح من المستخدم إلى الحاوية ليُحلّ كصنف.
 */

return [

    // لوحة المشترك — داخل قاعدته
    'tenant' => [
        'users' => UserResource::class,

        // التعليم
        'courses' => CourseResource::class,
        'lessons' => LessonResource::class,
        'quizzes' => QuizResource::class,
        'questions' => QuestionResource::class,
        'enrollments' => EnrollmentResource::class,
        'certificates' => CertificateResource::class,
        'taxonomies' => TaxonomyResource::class,
    ],

    // اللوحة العليا — القاعدة المركزية
    'central' => [
        'tenants' => TenantResource::class,
        'subscriptions' => SubscriptionResource::class,
        'invoices' => InvoiceResource::class,
    ],
];
