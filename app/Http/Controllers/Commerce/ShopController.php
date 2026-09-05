<?php

declare(strict_types=1);

namespace App\Http\Controllers\Commerce;

use App\Modules\Commerce\Models\Product;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * المتجر العام — صفحاته لم تكن موجودة.
 *
 * المنتجات تُدار في اللوحة وتُباع في السلة، ولا صفحة تعرضها: من
 * يريد شراء كتابٍ لا يجد رابطاً إليه إلا أن يعطيه المدرّس إياه
 * يدوياً.
 *
 * والمنتجات المرتبطة بكورس (`type = course`) تُستثنى: لها صفحتها
 * في الكتالوج، وعرضُها مرّتين يجعل الزائر يظنّهما شيئين.
 */
final class ShopController
{
    public function index(Request $request): View
    {
        $sort = (string) $request->query('sort', 'latest');

        $products = $this->base()
            ->when($request->filled('q'), function (Builder $q) use ($request): void {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], trim((string) $request->query('q'))).'%';
                $q->where(fn (Builder $w) => $w->where('title', 'like', $term)->orWhere('short_description', 'like', $term));
            })
            ->when($request->filled('type'), fn (Builder $q) => $q->where('type', $request->query('type')))
            // الأرخص أولاً خيارٌ يطلبه من يقارن، والأحدث افتراضٌ يخدم من يتصفّح
            ->when($sort === 'cheapest', fn (Builder $q) => $q->orderBy('price_minor'))
            ->when($sort === 'dearest', fn (Builder $q) => $q->orderByDesc('price_minor'))
            ->when($sort === 'popular', fn (Builder $q) => $q->orderByDesc('sales_count'))
            ->when($sort === 'latest', fn (Builder $q) => $q->orderByDesc('featured')->latest('id'))
            ->paginate(12)
            ->withQueryString();

        return view('commerce.shop', [
            'products' => $products,
            'sort' => $sort,
            'types' => $this->base()->select('type')->distinct()->pluck('type'),
        ]);
    }

    public function show(string $slug): View
    {
        $product = $this->base()->where('slug', $slug)->firstOrFail();

        return view('commerce.product', [
            'product' => $product,
            'related' => $this->base()
                ->where('type', $product->type)
                ->whereKeyNot($product->getKey())
                ->limit(4)->get(),
        ]);
    }

    private function base(): Builder
    {
        abort_unless(module_enabled('commerce'), 404);

        return Product::query()->published()->where('type', '!=', 'course');
    }
}
