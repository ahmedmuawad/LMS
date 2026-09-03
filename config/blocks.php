<?php

declare(strict_types=1);

use App\Modules\Content\Blocks\Types\CoursesBlock;
use App\Modules\Content\Blocks\Types\CtaBlock;
use App\Modules\Content\Blocks\Types\FaqBlock;
use App\Modules\Content\Blocks\Types\FeaturesBlock;
use App\Modules\Content\Blocks\Types\FormBlock;
use App\Modules\Content\Blocks\Types\HeroBlock;
use App\Modules\Content\Blocks\Types\RichTextBlock;
use App\Modules\Content\Blocks\Types\ServicesBlock;
use App\Modules\Content\Blocks\Types\StatsBlock;
use App\Modules\Content\Blocks\Types\TestimonialsBlock;
use App\Modules\Content\Blocks\Types\VideoBlock;

/*
 | ADR-005 — كتل باني الصفحات. القائمة مغلقة عمداً:
 | لا يصل اسم كتلة من المستخدم إلى الحاوية ليُحلّ كصنف.
 */

return [
    'available' => [
        'hero' => HeroBlock::class,
        'text' => RichTextBlock::class,
        'features' => FeaturesBlock::class,
        'stats' => StatsBlock::class,
        'testimonials' => TestimonialsBlock::class,
        'faq' => FaqBlock::class,
        'cta' => CtaBlock::class,
        'video' => VideoBlock::class,
        'courses' => CoursesBlock::class,
        'services' => ServicesBlock::class,
        'form' => FormBlock::class,
    ],

    'groups' => [
        'layout' => 'التخطيط',
        'general' => 'المحتوى',
        'dynamic' => 'محتوى ديناميكي',
    ],
];
