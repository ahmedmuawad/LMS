<?php

declare(strict_types=1);

namespace App\Modules\Content\Blocks;

use Illuminate\Contracts\Container\Container;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * سجلّ الكتل. القائمة مغلقة في config: لا يصل اسم كتلة من
 * المستخدم إلى الحاوية ليُحلّ كصنف.
 */
final class BlockRegistry
{
    /** @var array<string, Block>|null */
    private ?array $blocks = null;

    public function __construct(private readonly Container $container) {}

    /** @return array<string, Block> */
    public function all(): array
    {
        return $this->blocks ??= collect(config('blocks.available', []))
            ->mapWithKeys(fn (string $class, string $key): array => [$key => $this->container->make($class)])
            ->all();
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    public function resolve(string $key): Block
    {
        return $this->all()[$key]
            ?? throw new NotFoundHttpException("كتلة غير معروفة: [{$key}]");
    }

    /** @return array<string, list<Block>> الكتل مجمّعة لعرضها في لوح الإضافة */
    public function grouped(): array
    {
        $groups = [];

        foreach ($this->all() as $block) {
            $groups[$block->group()][] = $block;
        }

        return $groups;
    }

    /**
     * تنقية بنية صفحة كاملة.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    public function sanitizeAll(array $blocks): array
    {
        $clean = [];

        foreach ($blocks as $entry) {
            $key = $entry['type'] ?? null;

            if (! is_string($key) || ! $this->has($key)) {
                continue;   // كتلة مجهولة تُسقَط بصمت ولا تُخزَّن
            }

            $clean[] = [
                'type' => $key,
                'content' => $this->resolve($key)->sanitize((array) ($entry['content'] ?? [])),
                'settings' => array_intersect_key(
                    (array) ($entry['settings'] ?? []),
                    array_flip(['background', 'spacing', 'width', 'anchor']),
                ),
            ];
        }

        return $clean;
    }
}
