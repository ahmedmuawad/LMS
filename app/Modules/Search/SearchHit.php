<?php

declare(strict_types=1);

namespace App\Modules\Search;

/**
 * نتيجة بحث واحدة — مجرّدةً عن نموذجها.
 *
 * الشاشة تعرض كورساً ومقالاً وخدمة في قائمة واحدة، فلو حملت
 * النماذج نفسها لاحتاجت أن تعرف كل نوع وحقوله.
 */
final class SearchHit
{
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly string $excerpt,
        public readonly string $url,
        public readonly int $score = 1,
    ) {}

    public function typeLabel(): string
    {
        return match ($this->type) {
            'courses' => __('كورس'),
            'services' => __('خدمة'),
            'products' => __('منتج'),
            'posts' => __('مقال'),
            default => __('نتيجة'),
        };
    }

    public function icon(): string
    {
        return match ($this->type) {
            'courses' => '▤',
            'services' => '◇',
            'products' => '◪',
            'posts' => '✎',
            default => '◦',
        };
    }
}
