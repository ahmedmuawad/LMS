<?php

declare(strict_types=1);

namespace App\Modules\Community\Actions;

use App\Models\User;
use App\Modules\Community\Models\Discussion;
use App\Modules\Community\Models\DiscussionReply;
use App\Modules\Community\Models\DiscussionVote;
use App\Modules\Gamification\Actions\AwardPoints;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\Enrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** طرح سؤال والردّ عليه وقبول الإجابة والتصويت. */
final class PostDiscussion
{
    public function __construct(private readonly AwardPoints $points) {}

    /** @param  array<string, mixed>  $input */
    public function ask(User $user, Course $course, array $input): Discussion
    {
        $this->assertMayParticipate($user, $course);

        $discussion = Discussion::create([
            'type' => $input['type'] ?? 'question',
            'course_id' => $course->getKey(),
            'item_id' => $input['item_id'] ?? null,
            'user_id' => $user->getKey(),
            'title' => $input['title'],
            'body' => $input['body'],
            // المراجعة تُبطئ النقاش، فهي خيار لا افتراض
            'status' => (bool) setting('community.moderate_questions', false) ? 'hidden' : 'open',
        ]);

        $this->points->handle($user, 'question.asked', $discussion);
        $this->points->touchStreak($user);

        $this->tellInstructor($discussion, $course);

        return $discussion;
    }

    /** @param  array<string, mixed>  $input */
    public function reply(User $user, Discussion $discussion, array $input): DiscussionReply
    {
        if ($discussion->status === 'closed') {
            throw new RuntimeException(__('هذا النقاش مغلق.'));
        }

        $course = $discussion->course;

        if ($course !== null) {
            $this->assertMayParticipate($user, $course);
        }

        $isInstructor = $course?->instructor?->user_id === $user->getKey()
            || in_array($user->role, ['owner', 'admin'], true);

        $reply = DB::transaction(function () use ($user, $discussion, $input, $isInstructor): DiscussionReply {
            $reply = DiscussionReply::create([
                'discussion_id' => $discussion->getKey(),
                'user_id' => $user->getKey(),
                'body' => $input['body'],
                'is_instructor' => $isInstructor,
            ]);

            $discussion->forceFill([
                'replies_count' => $discussion->replies()->count(),
                'last_reply_at' => now(),
            ])->save();

            return $reply;
        });

        $this->points->handle($user, 'answer.written', $reply);
        $this->points->touchStreak($user);

        // صاحب السؤال يُخبَر بالردّ — ولا يُخبَر بردّ نفسه
        if (! $discussion->isOwnedBy($user) && $discussion->user !== null) {
            notify('community.question_answered', $discussion->user, [
                'course_title' => (string) ($course?->title ?? ''),
                'answerer_name' => (string) $user->name,
                'excerpt' => mb_substr($input['body'], 0, 200),
                'thread_url' => url('/discussions/'.$discussion->getKey()),
                'url' => url('/discussions/'.$discussion->getKey()),
            ]);
        }

        return $reply;
    }

    /**
     * قبول إجابة.
     *
     * صاحب السؤال وحده والمدرّس يقبلان: لو قبل أي أحد لصار وسماً
     * بلا معنى، ولذهبت نقاط «إجابة مقبولة» لمن لم يستحقّها.
     */
    public function accept(User $user, DiscussionReply $reply): DiscussionReply
    {
        $discussion = $reply->discussion;

        $mayAccept = $discussion->isOwnedBy($user)
            || $discussion->course?->instructor?->user_id === $user->getKey()
            || in_array($user->role, ['owner', 'admin'], true);

        if (! $mayAccept) {
            throw new RuntimeException(__('لا تملك قبول هذه الإجابة.'));
        }

        DB::transaction(function () use ($reply, $discussion): void {
            // إجابة واحدة مقبولة: القبول الجديد يرفع القديم
            DiscussionReply::where('discussion_id', $discussion->getKey())
                ->update(['is_answer' => false]);

            $reply->forceFill(['is_answer' => true])->save();
            $discussion->forceFill(['status' => 'answered'])->save();
        });

        if ($reply->user !== null) {
            $this->points->handle($reply->user, 'answer.accepted', $reply);
        }

        return $reply->refresh();
    }

    /** تصويت واحد لكل شخص — والضغط ثانيةً يسحبه. */
    public function vote(User $user, Model $votable): int
    {
        if (! (bool) setting('community.votes', true)) {
            return (int) $votable->votes_count;
        }

        return DB::transaction(function () use ($user, $votable): int {
            $existing = DiscussionVote::where('votable_type', $votable::class)
                ->where('votable_id', $votable->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            $existing === null
                ? DiscussionVote::create([
                    'votable_type' => $votable::class,
                    'votable_id' => $votable->getKey(),
                    'user_id' => $user->getKey(),
                    'value' => 1,
                ])
                : $existing->delete();

            $count = DiscussionVote::where('votable_type', $votable::class)
                ->where('votable_id', $votable->getKey())
                ->sum('value');

            $votable->forceFill(['votes_count' => (int) $count])->save();

            return (int) $count;
        });
    }

    private function assertMayParticipate(User $user, Course $course): void
    {
        if (! (bool) setting('community.discussions', true)) {
            throw new RuntimeException(__('النقاش معطّل في هذا الموقع.'));
        }

        if ((string) setting('community.who_can_ask', 'enrolled') !== 'enrolled') {
            return;
        }

        if (in_array($user->role, ['owner', 'admin', 'instructor'], true)) {
            return;
        }

        $enrolled = Enrollment::where('user_id', $user->getKey())
            ->where('course_id', $course->getKey())
            ->exists();

        if (! $enrolled) {
            throw new RuntimeException(__('النقاش لمن سجّل في هذا الكورس.'));
        }
    }

    private function tellInstructor(Discussion $discussion, Course $course): void
    {
        if (! (bool) setting('community.notify_instructor', true)) {
            return;
        }

        $instructor = $course->instructor?->user;

        if ($instructor === null || $discussion->isOwnedBy($instructor)) {
            return;
        }

        notify('community.question_asked', $instructor, [
            'student_name' => (string) ($discussion->user?->name ?? ''),
            'course_title' => (string) $course->title,
            'excerpt' => mb_substr($discussion->body, 0, 200),
            'thread_url' => url('/discussions/'.$discussion->getKey()),
            'url' => url('/discussions/'.$discussion->getKey()),
        ]);
    }
}
