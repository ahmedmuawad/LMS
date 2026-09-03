<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use App\Modules\Content\Models\Page;
use App\Modules\Content\Models\Post;
use App\Modules\Lms\Models\Course;
use App\Modules\Services\Models\Service;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * خريطة الموقع وrobots.
 *
 * تُولَّد عند الطلب لا تُكتب ملفاً: المشترك ينشر كورساً فيظهر في
 * الخريطة فوراً، وملف يُبنى ليلاً يعني يوماً كاملاً بلا فهرسة.
 */
final class SitemapController
{
    /** أنواع المحتوى المسموح بها — قائمة مغلقة لا تُشتقّ من الطلب. */
    private const SECTIONS = ['pages', 'courses', 'posts', 'services'];

    public function index(): Response
    {
        abort_unless((bool) setting('seo.sitemap', true), 404);

        $sections = [];

        foreach (self::SECTIONS as $section) {
            if ($this->countFor($section) > 0) {
                $sections[] = [
                    'loc' => url('/sitemap-'.$section.'.xml'),
                    'lastmod' => $this->lastModified($section),
                ];
            }
        }

        $xml = view('seo.sitemap-index', ['sections' => $sections])->render();

        return $this->xml($xml);
    }

    public function section(string $section): Response
    {
        abort_unless((bool) setting('seo.sitemap', true), 404);

        if (! in_array($section, self::SECTIONS, true)) {
            throw new NotFoundHttpException("قسم غير معروف: [{$section}]");
        }

        return $this->xml(view('seo.sitemap', [
            'urls' => $this->urlsFor($section),
        ])->render());
    }

    public function robots(): Response
    {
        $custom = (string) setting('seo.robots_txt', '');

        if (trim($custom) !== '') {
            return response($custom, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        // موقع غير مفهرس لا يُترك بلا ملف: الصمت يُفسَّر سماحاً
        $lines = (bool) setting('seo.indexable', true)
            ? ['User-agent: *', 'Disallow: /admin/', 'Disallow: /checkout', 'Disallow: /cart', 'Disallow: /learn/']
            : ['User-agent: *', 'Disallow: /'];

        if ((bool) setting('seo.sitemap', true)) {
            $lines[] = '';
            $lines[] = 'Sitemap: '.url('/sitemap.xml');
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /** @return list<array{loc:string, lastmod:?string, changefreq:string, priority:string}> */
    private function urlsFor(string $section): array
    {
        $limit = max(100, (int) setting('seo.sitemap_per_file', 5000));
        $noindex = (array) setting('seo.noindex_types', []);

        if (in_array($section, $noindex, true)) {
            return [];
        }

        return match ($section) {
            'pages' => Page::where('status', 'published')
                ->get(['slug', 'updated_at', 'published_at', 'status'])
                ->filter(fn (Page $page): bool => $page->isLive())
                ->take($limit)
                ->map(fn (Page $page): array => $this->url('/'.$page->slug, $page->updated_at, 'monthly', '0.6'))
                ->values()->all(),

            'courses' => Course::where('status', 'published')->where('visibility', 'public')
                ->limit($limit)->get(['slug', 'updated_at'])
                ->map(fn (Course $course): array => $this->url('/courses/'.$course->slug, $course->updated_at, 'weekly', '0.9'))
                ->all(),

            'posts' => Post::live()->limit($limit)->get(['slug', 'updated_at'])
                ->map(fn (Post $post): array => $this->url('/blog/'.$post->slug, $post->updated_at, 'monthly', '0.7'))
                ->all(),

            'services' => Service::published()->limit($limit)->get(['slug', 'updated_at'])
                ->map(fn (Service $service): array => $this->url('/services/'.$service->slug, $service->updated_at, 'monthly', '0.8'))
                ->all(),

            default => [],
        };
    }

    /** @return array{loc:string, lastmod:?string, changefreq:string, priority:string} */
    private function url(string $path, mixed $updatedAt, string $frequency, string $priority): array
    {
        return [
            'loc' => url($path),
            'lastmod' => $updatedAt?->toAtomString(),
            'changefreq' => $frequency,
            'priority' => $priority,
        ];
    }

    private function countFor(string $section): int
    {
        return match ($section) {
            'pages' => Page::where('status', 'published')->count(),
            'courses' => Course::where('status', 'published')->where('visibility', 'public')->count(),
            'posts' => Post::live()->count(),
            'services' => Service::published()->count(),
            default => 0,
        };
    }

    private function lastModified(string $section): ?string
    {
        $value = match ($section) {
            'pages' => Page::max('updated_at'),
            'courses' => Course::max('updated_at'),
            'posts' => Post::max('updated_at'),
            'services' => Service::max('updated_at'),
            default => null,
        };

        return $value === null ? null : Carbon::parse($value)->toAtomString();
    }

    private function xml(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=1800',
        ]);
    }
}
