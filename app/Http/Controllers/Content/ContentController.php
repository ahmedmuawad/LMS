<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use App\Models\User;
use App\Modules\Content\Actions\StoreMedia;
use App\Modules\Content\Models\Comment;
use App\Modules\Content\Models\Form;
use App\Modules\Content\Models\FormSubmission;
use App\Modules\Content\Models\Page;
use App\Modules\Content\Models\Post;
use App\Modules\Lms\Models\Taxonomy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use RuntimeException;

/** الواجهة العامة للمحتوى: الصفحات والمدونة والنماذج. */
final class ContentController
{
    public function page(Request $request, string $slug, RedirectController $redirects): View|RedirectResponse
    {
        $page = Page::where('slug', $slug)->with('cover')->first();

        /*
         | لا صفحة بهذا الرابط: قد يكون رابطاً قديماً له مقابل.
         | المسار العام يلتقط المقطع الواحد قبل الـfallback، فلو لم
         | نسأل جدول التحويلات هنا لضاع كل تحويل من مقطع واحد.
         */
        if ($page === null) {
            return $redirects($request);
        }

        // المسودّة يراها من يملك اللوحة وحده — للمعاينة قبل النشر
        abort_unless($page->isLive() || $request->user()?->canAccessPanel() === true, 404);

        return view('content.page', ['page' => $page]);
    }

    public function blog(Request $request): View
    {
        $posts = Post::live()
            ->with(['cover', 'category', 'author'])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('excerpt', 'like', $term));
            })
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->integer('category')))
            ->latest('published_at')
            ->paginate((int) setting('content.posts_per_page', 12))
            ->withQueryString();

        return view('content.blog', [
            'posts' => $posts,
            'categories' => Taxonomy::ofType('category')->orderBy('position')->get(),
        ]);
    }

    public function post(Request $request, string $slug): View
    {
        $post = Post::where('slug', $slug)->with(['cover', 'category', 'author', 'tags'])->firstOrFail();

        // المجدول ليس منشوراً بعد: نشره قبل موعده يفسد الجدولة نفسها
        $isLive = $post->status === 'published'
            && ($post->published_at === null || $post->published_at->isPast());

        abort_unless($isLive || $request->user()?->canAccessPanel() === true, 404);

        $post->incrementQuietly('views_count');

        return view('content.post', [
            'post' => $post,
            'comments' => $post->comments()->approved()->whereNull('parent_id')
                ->with(['user', 'replies.user'])->latest()->get(),
            'related' => Post::live()->where('id', '!=', $post->id)
                ->when($post->category_id !== null, fn ($q) => $q->where('category_id', $post->category_id))
                ->latest('published_at')->limit(3)->get(),
        ]);
    }

    public function comment(Request $request, string $slug): RedirectResponse
    {
        $post = Post::where('slug', $slug)->firstOrFail();

        $policy = (string) setting('content.comments', 'users');

        if ($policy === 'off' || ! $post->allow_comments) {
            return back()->withErrors(['comment' => __('التعليقات مغلقة على هذا المقال.')]);
        }

        if ($policy === 'users' && $request->user() === null) {
            return redirect(url('/login'))->with('status', __('سجّل دخولك للتعليق.'));
        }

        $key = 'comment:'.($request->user()?->getKey() ?? $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['comment' => __('تعليقات كثيرة في وقت قصير. حاول بعد قليل.')]);
        }

        $input = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'author_name' => ['nullable', 'string', 'max:80'],
            'author_email' => ['nullable', 'email'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ]);

        RateLimiter::hit($key, 600);

        $moderate = (bool) setting('content.moderate', true);
        $firstOnly = (bool) setting('content.moderate_first_only', true);

        // مراجعة أول تعليق فقط: تقلّل العبء بلا فتح الباب للسبام
        $approvedBefore = $request->user() !== null && Comment::where('user_id', $request->user()->getKey())
            ->where('status', 'approved')->exists();

        $post->comments()->create([
            'user_id' => $request->user()?->getKey(),
            'parent_id' => $input['parent_id'] ?? null,
            'author_name' => $input['author_name'] ?? null,
            'author_email' => $input['author_email'] ?? null,
            'body' => $input['body'],
            'status' => ! $moderate || ($firstOnly && $approvedBefore) ? 'approved' : 'pending',
            'ip' => $request->ip(),
        ]);

        if ($moderate && ! ($firstOnly && $approvedBefore)) {
            $moderators = User::whereIn('role', ['owner', 'admin'])->where('status', 'active')->get();

            if ($moderators->isNotEmpty()) {
                notify('content.comment_pending', $moderators, [
                    'post_title' => (string) $post->title,
                    'author_name' => (string) ($request->user()?->name ?? $input['author_name'] ?? __('زائر')),
                    'excerpt' => mb_substr($input['body'], 0, 200),
                    'moderation_url' => url('/admin/comments'),
                    'url' => url('/admin/comments'),
                ]);
            }
        }

        return back()->with('status', $moderate && ! ($firstOnly && $approvedBefore)
            ? __('وصل تعليقك وسيظهر بعد المراجعة.')
            : __('نُشر تعليقك.'));
    }

    public function submitForm(Request $request, string $key): RedirectResponse
    {
        $form = Form::where('key', $key)->where('is_active', true)->firstOrFail();

        $limitKey = 'form:'.$key.':'.$request->ip();

        if (RateLimiter::tooManyAttempts($limitKey, 5)) {
            return back()->withErrors(['form' => __('محاولات كثيرة. حاول بعد قليل.')]);
        }

        $validated = $request->validate($form->validationRules());

        RateLimiter::hit($limitKey, 600);

        if ($form->store_submissions) {
            FormSubmission::create([
                'form_id' => $form->getKey(),
                'data' => $this->storeAttachments($validated['data'] ?? [], $request),
                'user_id' => $request->user()?->getKey(),
                'ip' => $request->ip(),
            ]);
        }

        $this->tellTheTeam($form, $validated['data'] ?? []);

        return back()->with('status', setting()->translated('forms.'.$key.'.success')
            ?: ($form->success_message[app()->getLocale()] ?? __('وصلتنا رسالتك. شكراً لك.')));
    }

    /**
     * تنبيه الفريق برسالة وصلت.
     *
     * نموذج «اتصل بنا» يمتلئ بلا أن يعلم أحد هو أسوأ من ألّا يكون
     * في الموقع نموذج أصلاً: العميل ينتظر ردّاً لن يأتي.
     *
     * @param  array<string, mixed>  $data
     */
    private function tellTheTeam(Form $form, array $data): void
    {
        $recipients = User::whereIn('role', ['owner', 'admin'])
            ->where('status', 'active')
            ->when(filled($form->notify_email), fn ($q) => $q->orWhere('email', $form->notify_email))
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $summary = collect($data)
            ->map(fn ($value, string $field): string => $field.': '.(is_scalar($value) ? (string) $value : '—'))
            ->take(5)
            ->implode("\n");

        notify('content.form_submitted', $recipients, [
            'form_name' => (string) $form->name,
            'summary' => mb_substr($summary, 0, 500),
            'submission_url' => url('/admin/forms/'.$form->getKey().'/edit'),
            'url' => url('/admin/forms/'.$form->getKey().'/edit'),
        ]);
    }

    /**
     * الملف المرفق يُخزَّن في المكتبة ويبقى في الرسالة معرّفه ورابطه.
     *
     * كتابة كائن الملف نفسه في عمود JSON تُنتج قيمة لا تُقرأ ولا
     * تُنزَّل — والمرفق حينها ضائع وإن بدت الرسالة محفوظة.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function storeAttachments(array $data, Request $request): array
    {
        foreach ($data as $field => $value) {
            if (! $value instanceof UploadedFile) {
                continue;
            }

            try {
                $media = app(StoreMedia::class)->handle($value, $request->user(), 'forms');
                $data[$field] = ['media_id' => $media->getKey(), 'name' => $media->name, 'url' => $media->url()];
            } catch (RuntimeException $e) {
                $data[$field] = ['error' => $e->getMessage()];
            }
        }

        return $data;
    }
}
