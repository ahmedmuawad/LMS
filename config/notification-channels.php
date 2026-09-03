<?php

declare(strict_types=1);

use App\Core\Notifications\Channels\DatabaseChannel;
use App\Core\Notifications\Channels\MailChannel;
use App\Core\Notifications\Channels\SmsChannel;
use App\Core\Notifications\Channels\WebPushChannel;
use App\Core\Notifications\Channels\WhatsAppChannel;

/*
 | القنوات المتاحة. القائمة مغلقة عمداً:
 | لا يصل اسم قناة من المستخدم إلى الحاوية ليُحلّ كصنف.
 |
 | الترتيب هنا هو ترتيب الأعمدة في مصفوفة الإشعارات.
 */

return [
    'mail' => MailChannel::class,
    'whatsapp' => WhatsAppChannel::class,
    'sms' => SmsChannel::class,
    'web_push' => WebPushChannel::class,
    'database' => DatabaseChannel::class,
];
