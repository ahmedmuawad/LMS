<?php

declare(strict_types=1);

namespace App\Core\Seo;

use App\Modules\Content\Models\Page;
use App\Modules\Content\Models\Post;
use App\Modules\Lms\Models\Course;
use App\Modules\Services\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * إضافة السيو الداخلية.
 *
 * ADR-006: الأداء والسيو أولوية أولى، وإضافة خارجية تعني قالباً
 * لا نتحكّم فيه وحقولاً لا تُترجَم. هنا العنوان والوصف والقانوني
 * وhreflang والبيانات المنظّمة تُبنى من نفس الإعدادات التي يراها
 * المشترك في شاشته.
 */
final class Seo
{
    /** ما يُكتب يدوياً في عمود seo يسبق كل اشتقاق. */
    public function forModel(Model $record, array $overrides = []): PageMeta
    {
        $manual = (array) ($record->seo ?? []);
        $locale = app()->getLocale();

        $derived = match (true) {
            $record instanceof Course => $this->fromCourse($record),
            $record instanceof Post => $this->fromPost($record),
            $record instanceof Service => $this->fromService($record),
            $record instanceof Page => $this->fromPage($record),
            default => new PageMeta,
        };

        return $derived->with(array_filter([
            'title' => $this->translated($manual, 'title', $locale),
            'description' => $this->translated($manual, 'description', $locale),
            'image' => $manual['image'] ?? null,
            'noindex' => ($manual['noindex'] ?? null) === true ? true : null,
        ], fn ($value): bool => $value !== null && $value !== '') + $overrides);
    }

    public function forPage(string $title, ?string $description = null, array $overrides = []): PageMeta
    {
        return (new PageMeta(
            title: $title,
            description: $description ?: (string) setting()->translated('seo.default_description'),
            image: setting('seo.og_image') ?: null,
            canonical: $this->canonical(),
        ))->with($overrides);
    }

    /** العنوان الكامل كما يظهر في التبويب ونتيجة البحث. */
    public function title(?string $title, string $template = 'seo.title_template'): string
    {
        $site = site_name();

        if ($title === null || $title === '') {
            return $site;
        }

        $pattern = (string) setting($template, '{title} {separator} {site}');
        $separator = (string) setting('seo.separator', '—');

        $full = strtr($pattern, [
            '{title}' => $title,
            '{site}' => $site,
            '{separator}' => $separator,
        ]);

        // ٦٠ محرفاً حدّ جوجل العملي: ما بعده يُقصّ في النتيجة
        return Str::limit(trim($full), 65, '');
    }

    public function description(?string $description): ?string
    {
        $text = $description ?: (string) setting()->translated('seo.default_description');

        return $text === '' ? null : Str::limit(trim(strip_tags($text)), 158);
    }

    public function canonical(): ?string
    {
        if (! (bool) setting('seo.canonical', true)) {
            return null;
        }

        // الاستعلامات لا تدخل الرابط القانوني: صفحة بفلتر ليست صفحة أخرى
        return url(request()->path());
    }

    /**
     * روابط اللغات.
     *
     * ADR-003: العربية بلا بادئة والإنجليزية تحت /en/. نبنيها من
     * المسار الحالي بعد نزع البادئة، فلا تُكتب يدوياً في كل صفحة.
     *
     * @return array<string, string>
     */
    public function alternates(): array
    {
        if (! (bool) setting('seo.hreflang', true)) {
            return [];
        }

        $path = trim(request()->path(), '/');
        $prefixes = (array) config('locales.prefixed', []);

        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                $path = trim(Str::after($path, $prefix), '/');

                break;
            }
        }

        $links = [config('locales.default', 'ar') => url($path)];

        foreach ($prefixes as $prefix) {
            $links[$prefix] = url($prefix.($path === '' ? '' : '/'.$path));
        }

        $links['x-default'] = $links[config('locales.default', 'ar')];

        return $links;
    }

    public function isIndexable(PageMeta $meta): bool
    {
        return (bool) setting('seo.indexable', true) && ! $meta->noindex;
    }

    /**
     * البيانات المنظّمة الأساسية لكل صفحة: المنظّمة والموقع.
     *
     * @return list<array<string, mixed>>
     */
    public function siteSchema(): array
    {
        $name = site_name();

        $organisation = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $name,
            'url' => url('/'),
            'logo' => setting('appearance.logo_path') ?: url('/icon.svg'),
            'email' => setting('general.admin_email') ?: null,
            'telephone' => setting('general.phone') ?: null,
        ]);

        return [
            $organisation,
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $name,
                'url' => url('/'),
                'inLanguage' => app()->getLocale(),
            ],
        ];
    }

    /**
     * @param  list<array{name:string, url:?string}>  $items
     * @return array<string, mixed>|null
     */
    public function breadcrumbSchema(array $items): ?array
    {
        if ($items === [] || ! (bool) setting('seo.breadcrumbs_schema', true)) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_values(array_map(
                fn (array $item, int $index): array => array_filter([
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['url'] ?? null,
                ]),
                $items,
                array_keys($items),
            )),
        ];
    }

    private function fromCourse(Course $course): PageMeta
    {
        $schema = [];

        if ((bool) setting('seo.course_schema', true)) {
            $schema[] = array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Course',
                'name' => (string) $course->title,
                'description' => $this->description((string) $course->excerpt),
                'url' => url('/courses/'.$course->slug),
                'inLanguage' => app()->getLocale(),
                'provider' => [
                    '@type' => 'Organization',
                    'name' => site_name(),
                    'sameAs' => url('/'),
                ],
                'aggregateRating' => (int) $course->ratings_count > 0 ? [
                    '@type' => 'AggregateRating',
                    'ratingValue' => (float) $course->rating_avg,
                    'ratingCount' => (int) $course->ratings_count,
                ] : null,
                'offers' => [
                    '@type' => 'Offer',
                    'price' => number_format((int) $course->price_minor / 100, 2, '.', ''),
                    'priceCurrency' => (string) $course->currency,
                    'availability' => 'https://schema.org/InStock',
                    'url' => url('/courses/'.$course->slug),
                ],
            ]);
        }

        return new PageMeta(
            title: $this->title((string) $course->title, 'seo.course_title_template'),
            description: $this->description((string) $course->excerpt),
            image: $course->cover_path ?: (setting('seo.og_image') ?: null),
            canonical: url('/courses/'.$course->slug),
            type: 'article',
            schema: $schema,
            modifiedAt: $course->updated_at?->toIso8601String(),
            author: $course->instructor?->name(),
        );
    }

    private function fromPost(Post $post): PageMeta
    {
        return new PageMeta(
            title: $this->title((string) $post->title, 'seo.blog_title_template'),
            description: $this->description((string) $post->excerpt),
            image: $post->cover?->url() ?: (setting('seo.og_image') ?: null),
            canonical: url('/blog/'.$post->slug),
            type: 'article',
            schema: [array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => Str::limit((string) $post->title, 110, ''),
                'description' => $this->description((string) $post->excerpt),
                'image' => $post->cover?->url(),
                'datePublished' => $post->published_at?->toIso8601String(),
                'dateModified' => $post->updated_at?->toIso8601String(),
                'author' => $post->author === null ? null : [
                    '@type' => 'Person',
                    'name' => (string) $post->author->name,
                ],
                'inLanguage' => app()->getLocale(),
            ])],
            publishedAt: $post->published_at?->toIso8601String(),
            modifiedAt: $post->updated_at?->toIso8601String(),
            author: $post->author?->name,
        );
    }

    private function fromService(Service $service): PageMeta
    {
        return new PageMeta(
            title: $this->title((string) $service->title),
            description: $this->description((string) $service->excerpt),
            image: $service->cover?->url() ?: (setting('seo.og_image') ?: null),
            canonical: url('/services/'.$service->slug),
            schema: [array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Service',
                'name' => (string) $service->title,
                'description' => $this->description((string) $service->excerpt),
                'url' => url('/services/'.$service->slug),
                'provider' => [
                    '@type' => 'Organization',
                    'name' => site_name(),
                ],
                'offers' => $service->needsQuote() ? null : [
                    '@type' => 'Offer',
                    'price' => number_format((int) $service->price_minor / 100, 2, '.', ''),
                    'priceCurrency' => (string) $service->currency,
                ],
            ])],
            modifiedAt: $service->updated_at?->toIso8601String(),
        );
    }

    private function fromPage(Page $page): PageMeta
    {
        return new PageMeta(
            title: $this->title((string) $page->title),
            description: $this->description((string) $page->excerpt),
            image: $page->cover?->url() ?: (setting('seo.og_image') ?: null),
            canonical: url('/'.$page->slug),
            noindex: ! $page->isLive(),
            modifiedAt: $page->updated_at?->toIso8601String(),
        );
    }

    /** @param  array<string, mixed>  $manual */
    private function translated(array $manual, string $key, string $locale): ?string
    {
        $value = $manual[$key] ?? null;

        if (is_array($value)) {
            $value = $value[$locale] ?? $value[config('locales.default', 'ar')] ?? null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
