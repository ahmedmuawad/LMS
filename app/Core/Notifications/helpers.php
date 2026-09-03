<?php

declare(strict_types=1);

use App\Core\Notifications\Notifier;
use App\Models\User;
use Illuminate\Support\Collection;

if (! function_exists('notify')) {
    /**
     * notify('lms.enrolled', $student, ['course_title' => '…'])
     *
     * نقطة نداء واحدة: القرار في أي قناة تُرسل يبقى في المُرسِل،
     * فلا تُنسى قناة في أحد أماكن النداء دون غيرها.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    function notify(string $event, User|Collection|array $to, array $data = []): array
    {
        return app(Notifier::class)->send($event, $to, $data);
    }
}
