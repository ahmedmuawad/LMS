<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use App\Modules\Content\Actions\StoreMedia;
use App\Modules\Content\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

/** مكتبة الوسائط: الرفع والاستعراض والحذف. */
final class MediaController
{
    public function index(Request $request): View
    {
        $media = Media::query()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->when($request->input('kind') === 'image', fn ($q) => $q->images())
            ->when($request->input('kind') === 'file', fn ($q) => $q->where('mime', 'not like', 'image/%'))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return view('content.media', ['media' => $media]);
    }

    /**
     * قائمة الوسائط لمنتقي حقل الصورة.
     *
     * منفصلة عن `index` عمداً: تلك تعيد صفحة كاملة بقالبها، وهذه تعيد
     * ما يحتاجه المنتقي وحده — فلا يُحمَّل نصف اللوحة داخل نافذة صغيرة.
     */
    public function browse(Request $request): JsonResponse
    {
        $media = Media::query()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->when($request->input('kind', 'image') === 'image', fn ($q) => $q->images())
            ->latest()
            ->paginate(30);

        return response()->json([
            'items' => $media->getCollection()->map($this->toPayload(...))->values(),
            'next' => $media->hasMorePages() ? $media->currentPage() + 1 : null,
        ]);
    }

    /**
     * رفع من داخل حقل الصورة — يعيد الملف المرفوع لا تحويلاً.
     *
     * ملف واحد لا مصفوفة: الحقل يحمل قيمة واحدة، وقبول عشرين ملفاً
     * هنا يعني اختيار أحدها عشوائياً.
     */
    public function upload(Request $request, StoreMedia $store): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file'],
            'folder' => ['nullable', 'string', 'alpha_dash', 'max:60'],
        ]);

        try {
            $media = $store->handle(
                $request->file('file'),
                $request->user(),
                $request->string('folder')->value() ?: null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->toPayload($media), 201);
    }

    /** @return array<string, mixed> */
    private function toPayload(Media $media): array
    {
        return [
            'id' => $media->getKey(),
            'name' => $media->name,
            'url' => $media->url(),
            'mime' => $media->mime,
            'size' => $media->humanSize(),
            'width' => $media->width,
            'height' => $media->height,
        ];
    }

    public function store(Request $request, StoreMedia $store): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['required', 'file'],
            'folder' => ['nullable', 'string', 'alpha_dash', 'max:60'],
        ]);

        $stored = 0;
        $errors = [];

        foreach ($request->file('files') as $file) {
            try {
                $store->handle($file, $request->user(), $request->string('folder')->value() ?: null);
                $stored++;
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            return back()->withErrors(['files' => implode(' — ', array_unique($errors))]);
        }

        return back()->with('status', trans_choice(
            '{0} لم يُرفع شيء|{1} رُفع ملف واحد|{2} رُفع ملفان|[3,10] رُفعت :count ملفات|[11,*] رُفع :count ملفاً',
            $stored,
            ['count' => $stored],
        ));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $media = Media::findOrFail($id);

        $input = $request->validate([
            'alt' => ['nullable', 'array'],
            'alt.*' => ['nullable', 'string', 'max:200'],
        ]);

        $media->forceFill(['alt' => array_filter($input['alt'] ?? [])])->save();

        return back()->with('status', __('حُفظ النص البديل.'));
    }

    public function destroy(string $id): RedirectResponse
    {
        $media = Media::findOrFail($id);

        // الملف يُحذف بعد السجلّ لا قبله: سجلّ يتيم أهون من ملف يتيم
        $disk = $media->disk;
        $paths = [$media->path, ...array_values((array) $media->conversions)];

        $media->delete();

        Storage::disk($disk)->delete($paths);

        return back()->with('status', __('حُذف الملف.'));
    }
}
