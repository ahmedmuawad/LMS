<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use App\Modules\Content\Blocks\BlockRegistry;
use App\Modules\Content\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * باني الصفحات.
 *
 * الترتيب والمحتوى يُحفظان معاً في عمود واحد: الصفحة وحدة واحدة،
 * وتفريقها على جداول يجعل «معاينة قبل النشر» و«تراجع» أصعب بلا داعٍ.
 */
final class PageBuilderController
{
    public function __construct(private readonly BlockRegistry $blocks) {}

    public function index(): View
    {
        return view('content.pages', [
            'pages' => Page::orderBy('is_system', 'desc')->orderBy('position')->orderBy('id')->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:200', 'unique:pages,slug'],
        ]);

        $page = Page::create([
            'slug' => $input['slug'],
            'title' => [config('locales.default', 'ar') => $input['title']],
            'status' => 'draft',
            'blocks' => [],
            'author_id' => $request->user()?->getKey(),
        ]);

        return redirect()->route('admin.page-builder.edit', ['id' => $page->getKey()]);
    }

    public function edit(string $id): View
    {
        return view('content.page-builder', [
            'page' => Page::findOrFail($id),
            'registry' => $this->blocks,
            'groups' => $this->blocks->grouped(),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $page = Page::findOrFail($id);

        $input = $request->validate([
            'title' => ['required', 'array'],
            'title.*' => ['nullable', 'string', 'max:200'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:200'],
            'status' => ['required', 'string', 'in:draft,published,scheduled'],
            // حدّ أعلى معلن: صفحة بمئة كتلة عبء على المحرّر وعلى الزائر
            'blocks' => ['nullable', 'array', 'max:60'],
            'blocks.*' => ['string', 'max:65535'],
        ]);

        // الصفحة الإلزامية لا يُغيَّر رابطها: التذييل والبوابات تشير إليه
        $slug = $page->is_system ? $page->slug : $input['slug'];

        $page->forceFill([
            'title' => array_filter($input['title']),
            'slug' => $slug,
            'status' => $input['status'],
            'published_at' => $input['status'] === 'published' ? ($page->published_at ?? now()) : $page->published_at,
            'blocks' => $this->blocks->sanitizeAll($this->decode($input['blocks'] ?? [])),
        ])->save();

        return back()->with('status', __('حُفظت الصفحة.'));
    }

    /**
     * المحرّر يرسل الكتل كنصّ JSON واحد: الترتيب جزء من البنية،
     * وإرساله حقولاً منفصلة يفقده عند أول سحب.
     *
     * @param  array<int|string, mixed>  $raw
     * @return list<array<string, mixed>>
     */
    private function decode(array $raw): array
    {
        $decoded = [];

        foreach ($raw as $entry) {
            if (is_string($entry)) {
                $entry = json_decode($entry, true);
            }

            if (is_array($entry)) {
                $decoded[] = $entry;
            }
        }

        return $decoded;
    }
}
