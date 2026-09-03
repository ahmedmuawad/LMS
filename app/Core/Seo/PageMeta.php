<?php

declare(strict_types=1);

namespace App\Core\Seo;

/**
 * وصف صفحة واحدة لمحرّكات البحث والمشاركة.
 *
 * قيمة تُبنى في المتحكّم وتُقرأ في القالب: بناؤها داخل القالب يجعل
 * كل صفحة تعيد اختراع وسومها، وأول من ينسى وسماً لا يكتشفه أحد.
 */
final class PageMeta
{
    /** @param  list<array{name:string, url:?string}>  $breadcrumbs */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $image = null,
        public readonly ?string $canonical = null,
        public readonly string $type = 'website',
        public readonly bool $noindex = false,
        public readonly array $breadcrumbs = [],
        /** @var list<array<string, mixed>> */
        public readonly array $schema = [],
        public readonly ?string $publishedAt = null,
        public readonly ?string $modifiedAt = null,
        public readonly ?string $author = null,
    ) {}

    /** @param  array<string, mixed>  $overrides */
    public function with(array $overrides): self
    {
        return new self(
            title: $overrides['title'] ?? $this->title,
            description: $overrides['description'] ?? $this->description,
            image: $overrides['image'] ?? $this->image,
            canonical: $overrides['canonical'] ?? $this->canonical,
            type: $overrides['type'] ?? $this->type,
            noindex: $overrides['noindex'] ?? $this->noindex,
            breadcrumbs: $overrides['breadcrumbs'] ?? $this->breadcrumbs,
            schema: $overrides['schema'] ?? $this->schema,
            publishedAt: $overrides['publishedAt'] ?? $this->publishedAt,
            modifiedAt: $overrides['modifiedAt'] ?? $this->modifiedAt,
            author: $overrides['author'] ?? $this->author,
        );
    }
}
