<?php

declare(strict_types=1);

namespace App\Core\Support\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * يُثبّت مُنشِئ السجلّ عند إنشائه.
 *
 * في النموذج لا في المتحكّم: السجلّ يُنشأ من شاشة ومن بذرة ومن أمر
 * سطر أوامر، وثلاثة أماكن تعني ثلاث فرص لنسيان العمود — والنسيان
 * هنا يعني سجلّاً بلا مالك يراه الجميع.
 */
trait TracksCreator
{
    public static function bootTracksCreator(): void
    {
        static::creating(function (self $model): void {
            if ($model->created_by === null && Auth::hasUser()) {
                $model->created_by = Auth::id();
            }
        });
    }
}
