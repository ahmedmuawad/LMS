<?php

declare(strict_types=1);

namespace App\Http\Controllers\Instructor;

use App\Core\Access\Scope;
use App\Models\User;
use App\Modules\Community\Models\Discussion;
use App\Modules\Community\Models\DiscussionReply;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * الأسئلة والردود — صندوق وارد المدرّس.
 *
 * السؤال بلا جواب سببٌ مباشر لتوقّف الطالب، فالشاشة مرتّبة بما لم
 * يُجب أولاً لا بالأحدث: الأحدث ترتيب أرشيف، والمعلّق ترتيب عمل.
 */
final class DiscussionController
{
    public function __construct(private readonly Scope $scope) {}

    public function index(Request $request): View
    {
        $user = $this->user($request);
        $status = (string) $request->input('status', 'open');

        $query = $this->scoped($user)
            ->where('type', '!=', 'announcement')
            ->with(['user', 'course'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status));

        return view('instructor.discussions', [
            'discussions' => $query
                ->orderByRaw("case when status = 'open' then 0 else 1 end")
                ->latest('last_reply_at')
                ->latest('id')
                ->paginate(20)->withQueryString(),
            'status' => $status,
            'openCount' => $this->scoped($user)->unanswered()->count(),
        ]);
    }

    public function show(Request $request, string $id): View
    {
        return view('instructor.discussion', [
            'discussion' => $this->scoped($this->user($request))
                ->with(['user', 'course', 'replies.user'])
                ->findOrFail($id),
        ]);
    }

    public function reply(Request $request, string $id): RedirectResponse
    {
        $discussion = $this->scoped($this->user($request))->findOrFail($id);

        $input = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_answer' => ['nullable', 'boolean'],
        ]);

        $isAnswer = (bool) ($input['is_answer'] ?? false);

        DB::transaction(function () use ($discussion, $input, $isAnswer, $request): void {
            DiscussionReply::create([
                'discussion_id' => $discussion->getKey(),
                'user_id' => $request->user()?->getKey(),
                'body' => $input['body'],
                'is_answer' => $isAnswer,
                // وسم «من المدرّس» يُحسم هنا لا من الطلب: الطالب لا
                // يمنح نفسه صوت المدرّس بحقل مخفيّ
                'is_instructor' => true,
                'status' => 'visible',
            ]);

            $discussion->forceFill([
                'replies_count' => $discussion->replies()->count(),
                'last_reply_at' => now(),
                'status' => $isAnswer ? 'answered' : $discussion->status,
            ])->save();
        });

        notify('community.question_answered', $discussion->user, [
            'course_title' => (string) ($discussion->course?->title ?? ''),
            'answerer_name' => (string) ($request->user()?->name ?? ''),
            'excerpt' => Str::limit($input['body'], 160),
            'thread_url' => url('/discussions/'.$discussion->getKey()),
        ]);

        return back()->with('status', __('أُرسل ردّك.'));
    }

    /** الإغلاق والإخفاء: ما لا يُفيد الطلاب لا يبقى معلّقاً في الصدارة. */
    public function update(Request $request, string $id): RedirectResponse
    {
        $discussion = $this->scoped($this->user($request))->findOrFail($id);

        $input = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(Discussion::STATUSES))],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        $discussion->forceFill([
            'status' => $input['status'],
            'is_pinned' => (bool) ($input['is_pinned'] ?? $discussion->is_pinned),
        ])->save();

        return back()->with('status', __('حُدّثت الحالة.'));
    }

    /** مناقشات كورساته وحدها — والمالك يرى الكل. */
    private function scoped(?User $user): Builder
    {
        return $this->scope->byCourse(Discussion::query(), $user);
    }

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
