<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * تصغير HTML الخارج.
 *
 * ## إعدادٌ كان يَعِد ولا يفعل
 *
 * «تصغير HTML و CSS و JS» مفتاحٌ مُشغَّلٌ افتراضياً في شاشة الأداء
 * ولا سطرَ يقرؤه. وCSS وJS يصغّرهما Vite عند البناء فعلاً — أمّا
 * HTML فيخرج بمسافاته وتعليقاته كاملة.
 *
 * ## والمكسب حقيقي على شبكةٍ بطيئة
 *
 * قوالبُنا مُزاحةٌ بعمق (مكوّنات داخل مكوّنات)، وصفحةُ كورسٍ تحمل
 * كيلوبايتاتٍ من مسافاتٍ بيضاء. وgzip يبتلع أكثرها، لكن ما لا
 * يُرسَل أصلاً لا يُضغَط ولا يُفكّ.
 *
 * ## وما لا يُمَسّ
 *
 * `<pre>` و`<textarea>` و`<script>` و`<style>`: المسافة فيها معنى.
 * ونزعُها من `<pre>` يُخرّب كل مثال شيفرةٍ في المنصّة، ومن
 * `<textarea>` يغيّر ما كتبه المستخدم.
 */
final class MinifyHtml
{
    /** ما يبقى كما هو حرفاً بحرف */
    private const PROTECTED = 'pre|textarea|script|style|code';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldMinify($request, $response)) {
            return $response;
        }

        $html = (string) $response->getContent();

        $response->setContent($this->minify($html));

        return $response;
    }

    private function shouldMinify(Request $request, Response $response): bool
    {
        if (tenant() === null || ! setting('performance.minify', true)) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        // JSON وملفّات لا تُمسّ: التصغير لغةُ HTML وحدها
        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }

        /*
         | ولا يُصغَّر ما ليس صفحةً كاملة.
         |
         | ردودُ Alpine الجزئية تُدرَج في الصفحة كما هي، وقصُّ مسافةٍ
         | بين وسمين متجاورين قد يلصق كلمتين كانتا منفصلتين.
         */
        return ! $request->headers->has('X-Alpine-Request');
    }

    private function minify(string $html): string
    {
        /*
         | المحميّ يُنتزَع أولاً ويُعاد أخيراً.
         |
         | ومحاولةُ تصغير ما حوله بتعبيرٍ نمطي واحد تخطئ حتماً: وسمٌ
         | داخل نصّ `<script>` يبدو وسماً وهو نصّ.
         */
        $keep = [];

        $html = preg_replace_callback(
            '#<('.self::PROTECTED.')\b[^>]*>.*?</\1>#is',
            function (array $m) use (&$keep): string {
                $keep[] = $m[0];

                return '<!--__keep'.(count($keep) - 1).'__-->';
            },
            $html,
        ) ?? $html;

        // تعليقات HTML تُحذف — إلا التعليقات الشرطية لمتصفّحات قديمة
        $html = preg_replace('/<!--(?!\[if)(?!__keep).*?-->/s', '', $html) ?? $html;

        // المسافات بين الوسوم تُطوى، ولا تُحذف: `</b> <i>` فيها فاصلة كلمات
        $html = preg_replace('/>\s+</', '> <', $html) ?? $html;

        // وسطورٌ متتالية تصير واحداً
        $html = preg_replace('/\s{2,}/', ' ', $html) ?? $html;

        foreach ($keep as $index => $original) {
            $html = str_replace('<!--__keep'.$index.'__-->', $original, $html);
        }

        return trim($html);
    }
}
