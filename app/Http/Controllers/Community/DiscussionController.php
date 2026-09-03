<?php

declare(strict_types=1);

namespace App\Http\Controllers\Community;

use App\Modules\Community\Actions\PostDiscussion;
use App\Modules\Community\Models\Discussion;
use App\Modules\Community\Models\DiscussionReply;
use App\Modules\Community\Models\DiscussionVote;
use App\Modules\Lms\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** نقاش الكورسات: القائمة والموضوع والردّ والقبول والتصويت. */
final class DiscussionController
{
    public function __construct(private readonly PostDiscussion $post) {}

    public function index(Request $request): View
    {
        $discussions = Discussion::visible()
            ->with(['user', 'course'])
            ->when($request->filled('course'), fn ($q) => $q->where('course_id', $request->integer('course')))
            ->when($request->input('filter') === 'unanswered', fn ($q) => $q->unanswered())
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('body', 'like', $term));
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_reply_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('community.discussions', [
            'discussions' => $discussions,
            'courses' => Course::where('status', 'published')->get(['id', 'title']),
        ]);
    }

    public function show(Request $request, string $id): View
    {
        $discussion = Discussion::visible()
            ->with(['user', 'course.instructor.user'])
            ->findOrFail($id);

        $discussion->incrementQuietly('views_count');

        return view('community.discussion', [
            'discussion' => $discussion,
            'replies' => $discussion->replies()
                ->with('user')
                ->orderByDesc('is_answer')
                ->orderByDesc('votes_count')
                ->orderBy('id')
                ->get(),
            'myVotes' => $this->myVotes($request, $discussion),
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        $input = $request->validate([
            'title' => ['required', 'string', 'min:5', 'max:200'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            'item_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'in:question,discussion'],
        ]);

        try {
            $discussion = $this->post->ask($request->user(), $course, $input);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['discussion' => $e->getMessage()]);
        }

        return redirect(url('/discussions/'.$discussion->getKey()))
            ->with('status', $discussion->status === 'hidden'
                ? __('وصل سؤالك وسيظهر بعد المراجعة.')
                : __('نُشر سؤالك.'));
    }

    public function reply(Request $request, string $id): RedirectResponse
    {
        $discussion = Discussion::visible()->findOrFail($id);

        $input = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        try {
            $this->post->reply($request->user(), $discussion, $input);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['reply' => $e->getMessage()]);
        }

        return back()->with('status', __('نُشر ردّك.'));
    }

    public function accept(Request $request, string $id, string $replyId): RedirectResponse
    {
        $reply = DiscussionReply::where('discussion_id', $id)->findOrFail($replyId);

        try {
            $this->post->accept($request->user(), $reply);
        } catch (RuntimeException $e) {
            return back()->withErrors(['reply' => $e->getMessage()]);
        }

        return back()->with('status', __('قُبلت الإجابة.'));
    }

    public function vote(Request $request, string $id, ?string $replyId = null): RedirectResponse
    {
        $votable = $replyId === null
            ? Discussion::visible()->findOrFail($id)
            : DiscussionReply::where('discussion_id', $id)->findOrFail($replyId);

        $this->post->vote($request->user(), $votable);

        return back();
    }

    /**
     * ما صوّت عليه هذا المستخدم — ليعرف الزرّ حاله.
     *
     * @return array<string, bool>
     */
    private function myVotes(Request $request, Discussion $discussion): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        $replyIds = $discussion->replies()->pluck('id');

        return DiscussionVote::where('user_id', $user->getKey())
            ->where(function ($query) use ($discussion, $replyIds): void {
                $query->where(fn ($q) => $q->where('votable_type', Discussion::class)->where('votable_id', $discussion->getKey()))
                    ->orWhere(fn ($q) => $q->where('votable_type', DiscussionReply::class)->whereIn('votable_id', $replyIds));
            })
            ->get()
            ->mapWithKeys(fn ($vote): array => [$vote->votable_type.':'.$vote->votable_id => true])
            ->all();
    }
}
