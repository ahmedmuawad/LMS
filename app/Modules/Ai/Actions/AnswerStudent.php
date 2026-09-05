<?php

declare(strict_types=1);

namespace App\Modules\Ai\Actions;

use App\Models\User;
use App\Modules\Ai\AiClient;
use App\Modules\Ai\Models\AiMessage;
use App\Modules\Lms\Models\Lesson;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * يجيب الطالب عن سؤالٍ في درسه.
 *
 * ## من مادّة الدرس لا من معرفة النموذج
 *
 * النموذج يعرف الكثير، وأكثرُه ليس منهج هذا الطالب. فلو أجاب من
 * معرفته لشرح ما لم يُشرح، وأدخل مصطلحاً لم يأخذه، وربّما خالف
 * كتابه — والطالب يصدّقه لأنه في منصّة مدرّسه.
 *
 * فالمادة تُوضع في الطلب، ويُؤمر بألّا يخرج عنها. وما ليس فيها
 * يقول عنه: اسأل مدرّسك.
 *
 * ## ولا يحلّ الواجب
 *
 * سؤالُ الطالب قد يكون سؤال امتحانه. فالمساعد يشرح ويُقرّب ويسأل
 * سؤالاً مضاداً — ولا يعطي حلّاً جاهزاً لما يبدو تكليفاً.
 */
final class AnswerStudent
{
    private const SYSTEM = <<<'TXT'
        أنت مساعد دراسي عربي تشرح لطالبٍ درسه.

        القواعد:
        - اشرح من «مادة الدرس» أدناه وحدها. ولا تضف من عندك.
        - ما لم يرد في المادة قل: «هذا ليس في مادة الدرس — اسأل مدرّسك».
        - اكتب بالعربية الواضحة القصيرة، بمستوى طالبٍ لا بمستوى كتاب.
        - إن بدا السؤال تكليفاً أو سؤال امتحان، فلا تعطِ الحلّ:
          اشرح الفكرة، واسأله سؤالاً يقوده إليها.
        - لا تخترع أرقاماً ولا تواريخ ولا أسماء.
        - ثلاث فقرات على الأكثر.
        TXT;

    /** آخر ما يُذكَر من المحادثة — أبعدُ منه لا يُغيّر الجواب ويكلّف */
    private const MEMORY = 6;

    public function __construct(private readonly AiClient $client) {}

    /**
     * @throws RuntimeException
     */
    public function handle(User $student, Lesson $lesson, string $question): AiMessage
    {
        $question = trim($question);

        if ($question === '') {
            throw new RuntimeException(__('اكتب سؤالك أولاً.'));
        }

        $material = trim((string) $lesson->content);

        if (mb_strlen($material) < 40) {
            throw new RuntimeException(__('هذا الدرس بلا مادة نصّية يقرؤها المساعد — اسأل مدرّسك.'));
        }

        AiMessage::create([
            'user_id' => $student->getKey(),
            'lesson_id' => $lesson->getKey(),
            'role' => 'student',
            'body' => mb_substr($question, 0, 2000),
        ]);

        $answer = $this->client->ask(self::SYSTEM, __(
            "الدرس: :title\n\nمادة الدرس:\n:material\n\nما سبق في المحادثة:\n:history\n\nسؤال الطالب: :question",
            [
                'title' => (string) $lesson->title,
                'material' => $material,
                'history' => $this->history($student, $lesson),
                'question' => $question,
            ],
        ));

        return AiMessage::create([
            'user_id' => $student->getKey(),
            'lesson_id' => $lesson->getKey(),
            'role' => 'assistant',
            'body' => trim($answer) !== '' ? trim($answer) : __('لم أفهم السؤال — أعد صياغته.'),
        ]);
    }

    /** @return Collection<int, AiMessage> */
    public static function threadFor(User $student, Lesson $lesson)
    {
        return AiMessage::where('user_id', $student->getKey())
            ->where('lesson_id', $lesson->getKey())
            ->orderBy('id')
            ->get();
    }

    private function history(User $student, Lesson $lesson): string
    {
        $rows = AiMessage::where('user_id', $student->getKey())
            ->where('lesson_id', $lesson->getKey())
            ->orderByDesc('id')
            ->limit(self::MEMORY)
            ->get()
            ->reverse();

        if ($rows->isEmpty()) {
            return __('(لا شيء)');
        }

        return $rows
            ->map(fn (AiMessage $m): string => ($m->role === 'student' ? __('الطالب') : __('المساعد')).': '.$m->body)
            ->implode("\n");
    }
}
