<?php

declare(strict_types=1);

namespace App\Http\Controllers\Growth;

use App\Core\Notifications\EventCatalogue;
use App\Modules\Growth\Models\Campaign;
use App\Modules\Growth\Models\CampaignStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** التسلسلات التسويقية: القائمة والمحرّر. */
final class CampaignController
{
    public function __construct(private readonly EventCatalogue $catalogue) {}

    public function index(): View
    {
        return view('growth.campaigns', [
            'campaigns' => Campaign::withCount('enrolments')->orderBy('id')->get(),
        ]);
    }

    public function edit(string $id): View
    {
        return view('growth.campaign', [
            'campaign' => Campaign::with('steps')->findOrFail($id),
            'events' => $this->catalogue->available(),
            'locales' => array_keys(config('locales.supported', ['ar' => []])),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'key' => ['required', 'string', 'alpha_dash', 'max:48', 'unique:campaigns,key'],
            'trigger' => ['required', 'string', 'in:'.implode(',', array_keys(Campaign::TRIGGERS))],
        ]);

        $campaign = Campaign::create([
            'key' => $input['key'],
            'name' => [config('locales.default', 'ar') => $input['name']],
            'trigger' => $input['trigger'],
            'status' => 'draft',
        ]);

        return redirect()->route('admin.campaigns.edit', ['id' => $campaign->getKey()]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $campaign = Campaign::findOrFail($id);

        $input = $request->validate([
            'name' => ['required', 'array'],
            'name.*' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'string', 'in:draft,active,paused'],
            'steps' => ['nullable', 'array', 'max:20'],
            'steps.*.id' => ['nullable', 'integer'],
            'steps.*.delay_minutes' => ['required', 'integer', 'min:1', 'max:129600'],
            'steps.*.event' => ['required', 'string', 'max:64'],
            'steps.*.is_active' => ['nullable'],
        ]);

        $campaign->forceFill([
            'name' => array_filter($input['name']),
            'status' => $input['status'],
        ])->save();

        $kept = [];

        foreach (array_values($input['steps'] ?? []) as $position => $values) {
            // حدث خارج الكتالوج لا يُحفظ ولو وصل في الطلب
            if (! $this->catalogue->has($values['event'])) {
                continue;
            }

            $step = CampaignStep::updateOrCreate(
                ['id' => $values['id'] ?? null, 'campaign_id' => $campaign->getKey()],
                [
                    'position' => $position,
                    'delay_minutes' => (int) $values['delay_minutes'],
                    'event' => $values['event'],
                    'is_active' => (bool) ($values['is_active'] ?? false),
                ],
            );

            $kept[] = $step->getKey();
        }

        // ما حُذف من المحرّر يُحذف من القاعدة: الخطوة المتروكة تُرسل
        CampaignStep::where('campaign_id', $campaign->getKey())
            ->when($kept !== [], fn ($q) => $q->whereNotIn('id', $kept))
            ->delete();

        return back()->with('status', __('حُفظ التسلسل.'));
    }
}
