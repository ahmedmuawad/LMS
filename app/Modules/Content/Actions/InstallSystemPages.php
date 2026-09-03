<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Content\Models\Page;

/**
 * الصفحات الإلزامية.
 *
 * تُنشأ بمحتوى مبدئي قابل للتحرير بدل أن تُترك للمشترك: موقع بلا
 * سياسة خصوصية أو استرداد يُرفض من بوابات الدفع، وهذا يوقف البيع.
 */
final class InstallSystemPages
{
    public function handle(): int
    {
        $created = 0;

        foreach (Page::SYSTEM as $key => $title) {
            if (! (bool) setting('content.page_'.$key, true)) {
                continue;
            }

            if (Page::where('system_key', $key)->exists()) {
                continue;
            }

            Page::create([
                'slug' => $key,
                'system_key' => $key,
                'is_system' => true,
                'title' => ['ar' => __($title)],
                'status' => 'draft',
                'blocks' => [[
                    'type' => 'text',
                    'content' => [
                        'heading' => ['ar' => __($title)],
                        'body' => ['ar' => __('اكتب هنا محتوى الصفحة. هذه مسودّة لن تظهر للزوّار حتى تنشرها.')],
                    ],
                    'settings' => [],
                ]],
            ]);

            $created++;
        }

        return $created;
    }
}
