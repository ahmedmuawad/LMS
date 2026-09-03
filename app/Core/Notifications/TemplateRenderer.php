<?php

declare(strict_types=1);

namespace App\Core\Notifications;

use App\Core\Notifications\Models\NotificationTemplate;

/**
 * يستبدل `{{ متغيّر }}` بقيمته.
 *
 * ليس Blade عمداً: القالب يحرّره صاحب المنصّة من اللوحة، وتمريره
 * إلى مُصرِّف يُنفِّذ PHP يعني تنفيذ كود من قاعدة البيانات. هنا
 * استبدال نصّي على قائمة متغيّرات يعلنها الحدث، وما عداها يُمحى.
 */
final class TemplateRenderer
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{subject:string, body:string}
     */
    public function render(NotificationEvent $event, ?NotificationTemplate $template, array $data, string $locale): array
    {
        $subject = $template?->getTranslation('subject', $locale) ?? '';
        $body = $template?->getTranslation('body', $locale) ?? '';

        if ($subject === '' && $body === '') {
            [$subject, $body] = $this->fallback($event, $locale);
        }

        return [
            'subject' => $this->substitute($subject, $event, $data),
            'body' => $this->substitute($body, $event, $data),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function substitute(string $text, NotificationEvent $event, array $data): string
    {
        // المتغيّر المعلَن وحده يُستبدل؛ وما ليس معلَناً يُمحى ولا يُترك
        // ظاهراً للمستلم — «{{ password }}» في رسالة يفزع أكثر مما يشرح.
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            function (array $matches) use ($event, $data): string {
                $name = $matches[1];

                if (! in_array($name, $event->variables, true)) {
                    return '';
                }

                $value = $data[$name] ?? '';

                return is_scalar($value) ? (string) $value : '';
            },
            $text,
        );
    }

    /**
     * نصّ افتراضي يصف الحدث بلا تفاصيل.
     *
     * قالب فارغ يجب ألّا يعني رسالة فارغة: المشترك الذي لم يحرّر
     * قوالبه بعد يستحق رسالة مفهومة لا سطراً أبيض.
     *
     * @return array{0:string, 1:string}
     */
    private function fallback(NotificationEvent $event, string $locale): array
    {
        $label = __($event->label, [], $locale);

        return [
            $label.' — {{ site_name }}',
            $locale === 'ar'
                ? "مرحباً {{ first_name }}،\n\n".$label."\n\n{{ site_name }}"
                : "Hi {{ first_name }},\n\n".$label."\n\n{{ site_name }}",
        ];
    }
}
