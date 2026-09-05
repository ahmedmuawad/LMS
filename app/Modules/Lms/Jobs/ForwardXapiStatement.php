<?php

declare(strict_types=1);

namespace App\Modules\Lms\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * نسخُ عبارةٍ إلى مخزن xAPI خارجي.
 *
 * ## نسخٌ لا تحويل
 *
 * العبارة محفوظةٌ عندنا قبل أن تُرسَل، فانقطاعُ مخزن الجهة أو
 * إلغاؤها اشتراكه فيه لا يُضيّع نتيجة طالب.
 *
 * ## وفي الطابور لا في الطلب
 *
 * الطالب ينهي نشاطه فيُبلَّغ، ومخزنٌ بطيء يجعله ينتظر ثانيتين على
 * شيءٍ لا يعنيه. فيُردّ عليه فوراً ويُرسَل بعده.
 */
final class ForwardXapiStatement implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** تباعد متصاعد: مخزنٌ متعثّر لا يُصلحه إلحاح فوري */
    public array $backoff = [60, 600];

    /** @param array<mixed> $statement */
    public function __construct(public readonly array $statement) {}

    public function handle(): void
    {
        $endpoint = (string) (setting('integrations.lrs_endpoint') ?? '');

        if ($endpoint === '') {
            return;
        }

        $request = Http::timeout(20)
            /*
             | ترويسة النسخة إلزامية في المعيار.
             |
             | ومخازن كثيرة ترفض الطلب بلا سببٍ مفهوم حين تنقص،
             | فتضيع ساعةٌ في البحث عن خطأٍ ليس في العبارة.
             */
            ->withHeaders(['X-Experience-API-Version' => '1.0.3'])
            ->acceptJson();

        $user = setting('integrations.lrs_username');
        $password = setting('integrations.lrs_password');

        if (filled($user)) {
            $request = $request->withBasicAuth((string) $user, (string) ($password ?? ''));
        }

        $request->post($endpoint, $this->statement)->throw();
    }
}
