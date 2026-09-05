<?php

declare(strict_types=1);

namespace App\Modules\Search;

use App\Modules\Commerce\Models\Product;
use App\Modules\Content\Models\Post;
use App\Modules\Lms\Models\Course;
use App\Modules\Services\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * بحث موحّد في موقع المشترك.
 *
 * كان الزائر يجد كتالوج الكورسات وحده: يبحث عن اسم مقال أو خدمة
 * فلا يجده، فيظنّ أنها غير موجودة ويغادر.
 *
 * ## لماذا بلا فهرس
 *
 * المشترك الواحد عنده مئاتٌ لا ملايين. و`LIKE` على مئات الصفوف
 * أسرع من أن يُحَسّ، وفهرسٌ خارجي يعني خدمةً ثالثة تُدار وتُراقَب
 * وتُعاد بناؤها بعد كل استيراد. فحين يصير عند مشتركٍ عشرات الآلاف
 * يُستبدل هذا الصنف وحده، والواجهة لا تتغيّر.
 *
 * والعناوين مخزَّنة JSON مترجَمة، فالبحث فيها نصّي على أي حال —
 * ولا فهرس نصّي يفهم `{"ar":"..."}` بلا استخراج.
 */
final class SiteSearch
{
    /** الأنواع وترتيب ظهورها: ما يُشترى قبل ما يُقرأ */
    private const TYPES = ['courses', 'services', 'products', 'posts'];

    /** @return array{results: Collection<int, SearchHit>, counts: array<string, int>} */
    public function search(string $term, ?string $only = null, int $perType = 8): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return ['results' => collect(), 'counts' => []];
        }

        $counts = [];
        $hits = collect();

        foreach (self::TYPES as $type) {
            $query = $this->queryFor($type, $term);

            if ($query === null) {
                continue;
            }

            $counts[$type] = (clone $query)->count();

            if ($only !== null && $only !== $type) {
                continue;
            }

            $hits = $hits->merge(
                $query->limit($perType)->get()->map(fn (Model $m): SearchHit => $this->hit($type, $m, $term)),
            );
        }

        /*
         | الترتيب بمطابقة العنوان لا بالتاريخ.
         |
         | من كتب «جبر» يريد كورس الجبر لا آخر مقالٍ ذُكرت فيه الكلمة.
         | والمطابقة في العنوان أقوى دلالةً من أي إشارة أخرى نملكها
         | بلا فهرس.
         */
        return [
            'results' => $hits->sortByDesc(fn (SearchHit $h): int => $h->score)->values(),
            'counts' => $counts,
        ];
    }

    private function queryFor(string $type, string $term): ?Builder
    {
        return match ($type) {
            'courses' => module_enabled('lms')
                ? $this->like(Course::query()->where('status', 'published')->where('visibility', 'public'), $term, ['title', 'excerpt'])
                : null,

            'services' => module_enabled('services') && (tenant()?->allows('services_module') ?? true)
                ? $this->like(Service::query()->where('status', 'published'), $term, ['title', 'excerpt'])
                : null,

            /*
             | منتجات الكورسات تُستثنى.
             |
             | لكل كورس صفُّ منتجٍ يمثّله في السلة، فبحثٌ عن «لارافيل»
             | كان يردّ الكورس ومنتجه — نتيجتان لشيء واحد تجعلان
             | الباحث يظنّهما شيئين ويحتار أيّهما يفتح.
             */
            'products' => module_enabled('commerce')
                ? $this->like(
                    Product::query()->where('status', 'published')->where('type', '!=', 'course'),
                    $term,
                    ['title', 'short_description'],
                )
                : null,

            'posts' => module_enabled('blog') && (tenant()?->allows('blog') ?? true)
                ? $this->like(Post::query()->where('status', 'published'), $term, ['title', 'excerpt'])
                : null,

            default => null,
        };
    }

    /** @param list<string> $columns */
    private function like(Builder $query, string $term, array $columns): Builder
    {
        $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($columns, $needle): void {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', $needle);
            }
        });
    }

    private function hit(string $type, Model $model, string $term): SearchHit
    {
        $title = (string) ($model->title ?? '');

        return new SearchHit(
            type: $type,
            title: $title,
            excerpt: (string) ($model->excerpt ?? $model->short_description ?? ''),
            url: $this->urlFor($type, $model),
            // مطابقةٌ في العنوان تسبق مطابقةً في الوصف، وبدايتُه تسبق وسطه
            score: match (true) {
                mb_stripos($title, $term) === 0 => 3,
                mb_stripos($title, $term) !== false => 2,
                default => 1,
            },
        );
    }

    private function urlFor(string $type, Model $model): string
    {
        $slug = (string) ($model->slug ?? $model->getKey());

        return match ($type) {
            'courses' => url('/courses/'.$slug),
            'services' => url('/services/'.$slug),
            'products' => url('/shop/'.$slug),
            'posts' => url('/blog/'.$slug),
            default => url('/'),
        };
    }
}
