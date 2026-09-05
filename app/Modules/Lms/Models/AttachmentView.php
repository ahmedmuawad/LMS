<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجلّ فتح مرفق — من فتحه ومتى ومن أين.
 *
 * بلا `updated_at`: السطر يُكتب مرة ولا يُعدَّل، وعمودٌ لا يتغيّر
 * أبداً يوهم القارئ بأن السجلّ قابل للتحرير.
 */
final class AttachmentView extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['attachment_id', 'user_id', 'action', 'ip', 'user_agent'];

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(LessonAttachment::class, 'attachment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
