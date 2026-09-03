<?php

declare(strict_types=1);

use App\Core\Settings\Groups\AnalyticsSettings;
use App\Core\Settings\Groups\AppearanceSettings;
use App\Core\Settings\Groups\CommerceSettings;
use App\Core\Settings\Groups\CommunitySettings;
use App\Core\Settings\Groups\ContentSettings;
use App\Core\Settings\Groups\CurrencySettings;
use App\Core\Settings\Groups\GamificationSettings;
use App\Core\Settings\Groups\GeneralSettings;
use App\Core\Settings\Groups\GrowthSettings;
use App\Core\Settings\Groups\IntegrationSettings;
use App\Core\Settings\Groups\LmsSettings;
use App\Core\Settings\Groups\LocaleSettings;
use App\Core\Settings\Groups\NotificationSettings;
use App\Core\Settings\Groups\PaymentSettings;
use App\Core\Settings\Groups\PerformanceSettings;
use App\Core\Settings\Groups\SecuritySettings;
use App\Core\Settings\Groups\SeoSettings;
use App\Core\Settings\Groups\ServiceSettings;
use App\Core\Settings\Groups\UserSettings;

/*
 | وثيقة 05 — شاشات الإعدادات. القائمة مغلقة عمداً:
 | لا يصل مفتاح من المستخدم إلى الحاوية ليُحلّ كصنف.
 |
 | الترتيب هنا هو ترتيب القائمة في الشاشة.
 */

return [
    'general' => GeneralSettings::class,
    'locale' => LocaleSettings::class,
    'currency' => CurrencySettings::class,
    'users' => UserSettings::class,
    'lms' => LmsSettings::class,
    'commerce' => CommerceSettings::class,
    'services' => ServiceSettings::class,
    'payments' => PaymentSettings::class,
    'seo' => SeoSettings::class,
    'analytics' => AnalyticsSettings::class,
    'notifications' => NotificationSettings::class,
    'appearance' => AppearanceSettings::class,
    'content' => ContentSettings::class,
    'community' => CommunitySettings::class,
    'gamification' => GamificationSettings::class,
    'growth' => GrowthSettings::class,
    'security' => SecuritySettings::class,
    'performance' => PerformanceSettings::class,
    'integrations' => IntegrationSettings::class,
];
