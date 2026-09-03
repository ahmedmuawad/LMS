<?php

declare(strict_types=1);

namespace App\Core\Notifications\Channels;

use App\Core\Notifications\Delivery;

/**
 * قناة إرسال.
 *
 * `isReady()` تفصل «غير مُعدّة» عن «فشلت»: الأولى تُسجَّل تخطّياً
 * ولا تُقلق أحداً، والثانية عطل يستحق النظر.
 */
interface Channel
{
    public function key(): string;

    public function label(): string;

    public function isReady(): bool;

    /** عنوان المستلم على هذه القناة — null يعني لا سبيل للوصول إليه. */
    public function destinationFor(Delivery $delivery): ?string;

    /** @return string|null معرّف الرسالة عند المزوّد إن وُجد */
    public function send(Delivery $delivery): ?string;
}
