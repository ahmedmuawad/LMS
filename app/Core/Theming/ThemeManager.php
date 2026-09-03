<?php

declare(strict_types=1);

namespace App\Core\Theming;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\File;

/**
 * ADR-013 / وثيقة 14 — ترتيب البحث عن أي عرض:
 *
 *     ثيم المشترك  ←  الموديول  ←  النواة
 *
 * الثيم مجلّد Blade فقط؛ ما لا يعرّفه يسقط تلقائياً إلى ما تحته،
 * فلا يحتاج أي ثيم أن ينسخ النظام كله ليغيّر صفحة واحدة.
 */
final class ThemeManager
{
    private ?string $active = null;

    /**
     * مسارات العرض الأصلية قبل أي ثيم.
     * بدونها تتراكم مسارات الثيمات عبر الطلبات في عملية واحدة
     * (Octane · الاختبارات) فيتسرّب ثيم مشترك إلى مشترك آخر.
     *
     * @var list<string>
     */
    private array $basePaths = [];

    public function __construct(private readonly ViewFactory $views) {}

    public function path(?string $theme = null): string
    {
        return base_path('themes/'.($theme ?? $this->active ?? $this->default()));
    }

    public function default(): string
    {
        return config('theming.default', 'solo-academy');
    }

    public function active(): string
    {
        return $this->active ?? $this->default();
    }

    public function exists(string $theme): bool
    {
        return File::exists(base_path("themes/{$theme}/theme.json"));
    }

    /**
     * يضع عروض الثيم في مقدّمة مسارات البحث — لا يستبدل شيئاً.
     * يعيد الضبط إلى المسارات الأصلية أولاً حتى لا يتراكم ثيم فوق ثيم.
     */
    public function use(string $theme): void
    {
        if (! $this->exists($theme)) {
            $theme = $this->default();
        }

        $finder = $this->views->getFinder();

        if ($this->basePaths === []) {
            $this->basePaths = $finder->getPaths();
        }

        $finder->setPaths($this->basePaths);

        $views = $this->path($theme).'/views';

        if (File::isDirectory($views)) {
            $finder->prependLocation($views);
        }

        // خريطة العروض المحلولة مُخزّنة داخل الباحث؛ بدون تفريغها
        // يظل العرض القديم مربوطاً بمساره السابق
        $finder->flush();

        $this->active = $theme;
        $this->views->share('activeTheme', $theme);
    }

    /** @return array<string, mixed> */
    public function manifest(?string $theme = null): array
    {
        $file = $this->path($theme).'/theme.json';

        return File::exists($file)
            ? (array) json_decode(File::get($file), true)
            : [];
    }

    /** @return array<string, array<string, mixed>> كل الثيمات المتاحة */
    public function all(): array
    {
        $themes = [];

        foreach (File::directories(base_path('themes')) as $dir) {
            $key = basename($dir);

            if ($this->exists($key)) {
                $themes[$key] = $this->manifest($key);
            }
        }

        return $themes;
    }

    /** الثيمات التي تناسب نمط منصة بعينه. */
    public function forMode(string $mode): array
    {
        return array_filter(
            $this->all(),
            fn (array $m): bool => blank($m['modes'] ?? null) || in_array($mode, $m['modes'], true),
        );
    }
}
