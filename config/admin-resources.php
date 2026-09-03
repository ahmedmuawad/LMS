<?php

declare(strict_types=1);
use App\Core\Admin\Resources\Center\BranchResource;
use App\Core\Admin\Resources\Center\CenterInvoiceResource;
use App\Core\Admin\Resources\Center\CenterStudentResource;
use App\Core\Admin\Resources\Center\GroupResource;
use App\Core\Admin\Resources\Center\RoomResource;
use App\Core\Admin\Resources\Central\InvoiceResource;
use App\Core\Admin\Resources\Central\SubscriptionResource;
use App\Core\Admin\Resources\Central\TenantResource;
use App\Core\Admin\Resources\Commerce\CouponResource;
use App\Core\Admin\Resources\Commerce\OrderResource;
use App\Core\Admin\Resources\Commerce\PayoutResource;
use App\Core\Admin\Resources\Commerce\ProductResource;
use App\Core\Admin\Resources\Commerce\RechargeCodeResource;
use App\Core\Admin\Resources\Commerce\RefundResource;
use App\Core\Admin\Resources\Community\BadgeResource;
use App\Core\Admin\Resources\Content\CommentResource;
use App\Core\Admin\Resources\Content\FormResource;
use App\Core\Admin\Resources\Content\PageResource;
use App\Core\Admin\Resources\Content\PostResource;
use App\Core\Admin\Resources\Content\RedirectResource;
use App\Core\Admin\Resources\Lms\CertificateResource;
use App\Core\Admin\Resources\Lms\CourseResource;
use App\Core\Admin\Resources\Lms\EnrollmentResource;
use App\Core\Admin\Resources\Lms\LessonResource;
use App\Core\Admin\Resources\Lms\QuestionResource;
use App\Core\Admin\Resources\Lms\QuizResource;
use App\Core\Admin\Resources\Lms\TaxonomyResource;
use App\Core\Admin\Resources\Services\BookingResource;
use App\Core\Admin\Resources\Services\ServiceResource;
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

        // التجارة
        'orders' => OrderResource::class,
        'products' => ProductResource::class,
        'coupons' => CouponResource::class,
        'recharge-codes' => RechargeCodeResource::class,
        'refunds' => RefundResource::class,
        'payouts' => PayoutResource::class,

        // الخدمات
        'services' => ServiceResource::class,
        'bookings' => BookingResource::class,

        // المحتوى
        'posts' => PostResource::class,
        'pages' => PageResource::class,
        'comments' => CommentResource::class,
        'forms' => FormResource::class,
        'redirects' => RedirectResource::class,

        // المجتمع والتحفيز
        'badges' => BadgeResource::class,

        // السنتر
        'groups' => GroupResource::class,
        'center-students' => CenterStudentResource::class,
        'branches' => BranchResource::class,
        'rooms' => RoomResource::class,
        'center-invoices' => CenterInvoiceResource::class,
    ],

    // اللوحة العليا — القاعدة المركزية
    'central' => [
        'tenants' => TenantResource::class,
        'subscriptions' => SubscriptionResource::class,
        'invoices' => InvoiceResource::class,
    ],
];
