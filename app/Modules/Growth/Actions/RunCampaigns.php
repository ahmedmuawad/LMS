<?php

declare(strict_types=1);

namespace App\Modules\Growth\Actions;

use App\Models\User;
use App\Modules\Growth\Models\Campaign;
use App\Modules\Growth\Models\CampaignEnrolment;
use App\Modules\Growth\Models\CampaignSend;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * تشغيل التسلسلات التسويقية.
 *
 * الخطوة تُرسل مرة واحدة بقيد فريد لا باعتماد على ترتيب التنفيذ:
 * المهمة المجدولة قد تتأخّر فتُنفَّذ مرتين، وعميل يتلقّى «أكمل
 * شراءك» مرتين في دقيقة يُلغي اشتراكه من كل شيء.
 */
final class RunCampaigns
{
    /** إدخال مستخدم في حملة على موضوع بعينه (سلة، تسجيل، حجز). */
    public function enrol(Campaign $campaign, User $user, ?Model $subject = null): ?CampaignEnrolment
    {
        if ($campaign->status !== 'active' || $campaign->steps->isEmpty()) {
            return null;
        }

        $first = $campaign->steps->first();

        $enrolment = CampaignEnrolment::firstOrNew([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $user->getKey(),
            'subject_type' => $subject === null ? null : $subject::class,
            'subject_id' => $subject?->getKey(),
        ]);

        if ($enrolment->exists) {
            return $enrolment;
        }

        $enrolment->fill([
            'step_index' => 0,
            'status' => 'running',
            'next_step_at' => now()->addMinutes((int) $first->delay_minutes),
        ])->save();

        $campaign->increment('entered_count');

        return $enrolment;
    }

    /**
     * تنفيذ ما حان وقته.
     *
     * @return array{sent:int, finished:int}
     */
    public function tick(int $limit = 200): array
    {
        $due = CampaignEnrolment::due()
            ->with(['campaign.steps', 'user'])
            ->limit($limit)
            ->get();

        $sent = 0;
        $finished = 0;

        foreach ($due as $enrolment) {
            try {
                $this->advance($enrolment) ? $sent++ : $finished++;
            } catch (Throwable) {
                // خطوة متعثّرة لا توقف الحملة كلّها: تُؤجَّل ساعة وتُعاد
                $enrolment->forceFill(['next_step_at' => now()->addHour()])->save();
            }
        }

        return ['sent' => $sent, 'finished' => $finished];
    }

    /** خروج فوري: من حقّق الهدف لا يُلاحَق برسائل عنه. */
    public function convert(Campaign $campaign, User $user, ?Model $subject = null): int
    {
        $updated = CampaignEnrolment::where('campaign_id', $campaign->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', 'running')
            ->when($subject !== null, fn ($q) => $q->where('subject_type', $subject::class)->where('subject_id', $subject->getKey()))
            ->update(['status' => 'converted', 'converted_at' => now(), 'next_step_at' => null]);

        if ($updated > 0) {
            $campaign->increment('converted_count', $updated);
        }

        return $updated;
    }

    /** @return bool أُرسلت خطوة أم انتهى التسلسل */
    private function advance(CampaignEnrolment $enrolment): bool
    {
        $campaign = $enrolment->campaign;
        $user = $enrolment->user;

        if ($campaign === null || $user === null || $campaign->status !== 'active') {
            $enrolment->forceFill(['status' => 'stopped', 'next_step_at' => null])->save();

            return false;
        }

        $steps = $campaign->steps->where('is_active', true)->values();
        $step = $steps->get((int) $enrolment->step_index);

        if ($step === null) {
            $enrolment->forceFill(['status' => 'completed', 'next_step_at' => null])->save();

            return false;
        }

        $alreadySent = DB::transaction(function () use ($enrolment, $step): bool {
            $exists = CampaignSend::where('enrolment_id', $enrolment->getKey())
                ->where('step_id', $step->getKey())
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                return true;
            }

            CampaignSend::create([
                'enrolment_id' => $enrolment->getKey(),
                'step_id' => $step->getKey(),
                'sent_at' => now(),
            ]);

            return false;
        });

        if (! $alreadySent) {
            notify($step->event, $user, (array) ($step->payload ?? []) + [
                'url' => (string) (($step->payload['url'] ?? null) ?: url('/')),
            ]);
        }

        $next = $steps->get((int) $enrolment->step_index + 1);

        $enrolment->forceFill([
            'step_index' => (int) $enrolment->step_index + 1,
            'status' => $next === null ? 'completed' : 'running',
            'next_step_at' => $next === null ? null : now()->addMinutes((int) $next->delay_minutes),
        ])->save();

        return ! $alreadySent;
    }
}
