<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use App\Modules\Search\SiteSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * شاشة البحث الموحّد.
 */
final class SearchController
{
    public function __invoke(Request $request, SiteSearch $search): View
    {
        $term = trim((string) $request->query('q', ''));
        $only = $request->query('type');
        $only = in_array($only, ['courses', 'services', 'products', 'posts'], true) ? $only : null;

        ['results' => $results, 'counts' => $counts] = $search->search($term, $only);

        return view('search', [
            'term' => $term,
            'only' => $only,
            'results' => $results,
            'counts' => $counts,
        ]);
    }
}
