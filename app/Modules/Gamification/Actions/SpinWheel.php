<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Actions;

use App\Models\User;
use App\Modules\Gamification\Models\PointEntry;
use App\Modules\Gamification\Models\WheelSpin;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

/**
 * عجلة الحظ — دورةٌ في اليوم بجائزة نقاط.
 *
 * ## وليست قماراً
 *
 * لا يدفع الطالب شيئاً ولا يخسر شيئاً؛ أسوأ ما يقع أن يربح أقلّ.
 * وفائدتها أنها تُعيده كل يوم — والعودة اليومية هي ما يبني التعلّم،
 * لا الحماسة الأولى.
 *
 * ## والقرعة في الخادم
 *
 * لو حُسبت في المتصفّح لأدارها من فتح أدوات المطوّر كما شاء. وما
 * يظهر في الشاشة من دورانٍ زينةٌ على نتيجةٍ قُضيت قبله.
 */
final class SpinWheel
{
    /**
     * القطاعات: نقاطٌ ووزن.
     *
     * الأوزان تجعل الجائزة الكبرى نادرة والصغرى شائعة — فتبقى
     * للعجلة قيمةٌ ولا تصير عدّاداً يُضاف كل يوم.
     *
     * @return list<array{points:int, label:string, weight:int}>
     */
    public function segments(): array
    {
        return [
            ['points' => 5, 'label' => __('٥ نقاط'), 'weight' => 30],
            ['points' => 10, 'label' => __('١٠ نقاط'), 'weight' => 25],
            ['points' => 15, 'label' => __('١٥ نقطة'), 'weight' => 18],
            ['points' => 25, 'label' => __('٢٥ نقطة'), 'weight' => 12],
            ['points' => 40, 'label' => __('٤٠ نقطة'), 'weight' => 8],
            ['points' => 60, 'label' => __('٦٠ نقطة'), 'weight' => 5],
            ['points' => 100, 'label' => __('١٠٠ نقطة'), 'weight' => 2],
        ];
    }

    public function canSpin(User $user): bool
    {
        return (bool) setting('gamification.wheel', true)
            && ! WheelSpin::where('user_id', $user->getKey())->whereDate('spun_on', today())->exists();
    }

    /**
     * @return array{points:int, label:string, index:int}
     *
     * @throws RuntimeException
     */
    public function handle(User $user): array
    {
        if (! setting('gamification.wheel', true)) {
            throw new RuntimeException(__('عجلة الحظ متوقّفة.'));
        }

        $segments = $this->segments();
        $index = $this->draw($segments);
        $chosen = $segments[$index];

        try {
            /*
             | المفتاح الفريد هو الحارس لا الفحص قبله.
             |
             | ضغطتان متزامنتان تمرّان من فحصٍ بالشيفرة معاً، ولا
             | تمرّان من مفتاحٍ في القاعدة.
             */
            WheelSpin::create([
                'user_id' => $user->getKey(),
                'points' => $chosen['points'],
                'label' => $chosen['label'],
                'spun_on' => today(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new RuntimeException(__('أدرتَ العجلة اليوم — عُد غداً.'));
        }

        PointEntry::create([
            'user_id' => $user->getKey(),
            'rule' => 'wheel.spin',
            'points' => $chosen['points'],
            'note' => $chosen['label'],
        ]);

        app(AwardPoints::class)->recalculate($user);

        return ['points' => $chosen['points'], 'label' => $chosen['label'], 'index' => $index];
    }

    /**
     * قرعةٌ موزونة.
     *
     * @param  list<array{points:int, label:string, weight:int}>  $segments
     */
    private function draw(array $segments): int
    {
        $total = array_sum(array_column($segments, 'weight'));
        $roll = random_int(1, max(1, $total));

        foreach ($segments as $index => $segment) {
            $roll -= $segment['weight'];

            if ($roll <= 0) {
                return $index;
            }
        }

        return 0;
    }
}
